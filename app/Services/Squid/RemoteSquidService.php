<?php

namespace App\Services\Squid;

use phpseclib3\Net\SSH2;
use App\Models\User;
use App\Models\SquidPort;
use App\Models\IpPool;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class RemoteSquidService
{
  protected $ssh;
  protected $remotePath;

  public function __construct()
  {
    $this->ssh = new SSH2(env('SQUID_SSH_HOST'), env('SQUID_SSH_PORT', 22));
    if (!$this->ssh->login(env('SQUID_SSH_USER'), env('SQUID_SSH_PASS'))) {
      throw new \Exception('SSH login failed');
    }

    $this->remotePath = rtrim(env('SQUID_CONFIG_PATH', '/etc/squid/includes'), '/');
  }

  public function calculateBandwidthUsageAndCache(): array
  {
    $accessLog = '/var/log/squid-instance1/access.log';

    $command = "awk '{if (\$8 != \"-\" && \$8 != \"\" && \$5 ~ /^[0-9]+\$/) bytes[\$8] += \$5} END {for (u in bytes) print u, bytes[u]}' $accessLog";
    $output = $this->ssh->exec($command);

    $result = [];

    foreach (explode("\n", trim($output)) as $line) {
      $line = trim($line);
      if ($line === '') continue;

      $parts = preg_split('/\s+/', $line);
      if (count($parts) !== 2) continue;

      [$username, $bytes] = $parts;

      if (!is_numeric($bytes)) continue;

      $mb = round($bytes / 1024 / 1024, 2);
      $result[$username] = $mb;

      Redis::setex("bandwidth:{$username}", 60 * 60 * 24 * 30, $mb); // 30 dias
    }

    return $result;
  }

  public function syncPortsWithDatabase(): array
  {
    $squidData = $this->getUsersWithPortsAndIps();
    $syncReport = [
      'assigned_ports' => 0,
      'missing_users'  => [],
      'missing_ports'  => [],
      'missing_ips'    => [],
    ];

    foreach ($squidData as $squidUsername => $data) {
      $user = User::where('squid_username', $squidUsername)->first();

      if (!$user) {
        $syncReport['missing_users'][] = $squidUsername;
        continue;
      }

      foreach ($data['ports'] as $portNumber) {
        $port = SquidPort::where('port', $portNumber)->first();

        if (!$port) {
          $syncReport['missing_ports'][] = $portNumber;
          continue;
        }

        $ipEntry = collect($data['ips'])->firstWhere('port', $portNumber);
        $ip = $ipEntry['ip'] ?? null;
        $ipPool = $ip ? IpPool::where('ip_address', $ip)->first() : null;

        if ($ip && !$ipPool) {
          $syncReport['missing_ips'][] = $ip;
        }

        $port->user_id = $user->id;
        $port->ip_pool_id = $ipPool?->id;
        $port->save(); // updated hook fará o resto
        $syncReport['assigned_ports']++;
      }
    }

    return $syncReport;
  }

  public function parseUsersFromAclUsersPorts()
  {
    $aclFile = "{$this->remotePath}/acls_users_ports.conf";
    $content = $this->ssh->exec("cat $aclFile");

    $users = [];

    foreach (explode("\n", $content) as $line) {
      if (preg_match('/^acl\s+(\w+)_ports\s+myportname\s+(.+)/', trim($line), $matches)) {
        $username = $matches[1];
        $ports = preg_split('/\s+/', trim($matches[2]));
        $ports = array_map(fn($p) => (int)str_replace('port', '', $p), $ports);
        $users[$username] = [
          'ports' => $ports,
          'ips' => [],
        ];
      }
    }

    return $users;
  }

  public function attachUserIps(array $users)
  {
    foreach ($users as $username => &$data) {
      $confFile = "{$this->remotePath}/user_ports_{$username}.conf";
      $output = $this->ssh->exec("[ -f $confFile ] && cat $confFile || echo ''");

      foreach (explode("\n", $output) as $line) {
        if (preg_match('/^tcp_outgoing_address\s+([0-9\.]+)\s+user_\w+\s+port(\d+)/', trim($line), $match)) {
          $data['ips'][] = [
            'ip'   => $match[1],
            'port' => (int)$match[2],
          ];
        }
      }
    }

    return $users;
  }

  public function applyIpRotationForPort(SquidPort $port, IpPool $newIp): bool
  {
    $username     = $port->user->squid_username;
    $portNumber   = $port->port;
    $newIpAddress = $newIp->ip_address;
    $confFile     = "{$this->remotePath}/user_ports_{$username}.conf";
    $backupFile   = "{$confFile}.bak";

    // 1. Backup
    $this->ssh->exec("cp $confFile $backupFile");

    // 2. Edita o IP
    $editCommand = sprintf(
      "sed -i 's/^tcp_outgoing_address .* port%d/tcp_outgoing_address %s user_%s port%d/' %s",
      $portNumber,
      $newIpAddress,
      $username,
      $portNumber,
      $confFile
    );

    $this->ssh->exec($editCommand);

    // 3. Valida
    $validation = $this->ssh->exec("squid -k parse");
    if (str_contains($validation, 'FATAL') || str_contains($validation, 'error')) {
      // 4. Reverte
      $this->ssh->exec("mv $backupFile $confFile");

      $revalidate = $this->ssh->exec("squid -k parse");
      logger()->error("Falha ao aplicar IP novo no Squid. Configuração revertida.", [
        'port'        => $port->port,
        'username'    => $username,
        'erro_parse'  => $validation,
        'revalidacao' => $revalidate,
      ]);

      throw new \Exception("Falha ao validar nova configuração do Squid: $validation");
    }

    // 5. Aplica
    $this->ssh->exec("squid -k reconfigure");

    // 6. Remove backup
    $this->ssh->exec("rm -f $backupFile");

    // 7. Atualiza banco (dispara o hook updated que ajusta flags/log/test)
    $port->ip_pool_id = $newIp->id;
    $port->save();

    return true;
  }

  public function getBandwidthUsageForUser(string $squidUsername): float
  {
    $logPath = '/var/log/squid-instance1/access.log';
    $escapedUser = escapeshellarg("user_{$squidUsername}");
    $escapedLogPath = escapeshellarg($logPath);

    $command = "grep {$escapedUser} {$escapedLogPath} | awk '{sum += \$NF} END {print sum}'";
    $output = trim($this->ssh->exec($command));

    if (empty($output)) {
      return 0;
    }
    return round(floatval($output) / 1024 / 1024, 2);
  }

  public function getUsersWithPortsAndIps()
  {
    $users = $this->parseUsersFromAclUsersPorts();
    return $this->attachUserIps($users);
  }
}
