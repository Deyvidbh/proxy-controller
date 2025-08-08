<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use App\Models\IpUsageLog;
use App\Models\IpPool;
use Carbon\Carbon;

class SquidPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_active',
        'in_use',
        'output_ip_address',
        'last_update_ip',
        'ip_pool_id',
        'host',
        'port',
        'expires_at',
        'last_renovation',
    ];

    protected $casts = [
        'expires_at'      => 'datetime',
        'last_update_ip'  => 'datetime',
        'last_renovation' => 'datetime',
        'is_active'       => 'boolean',
        'in_use'          => 'boolean',
    ];

    protected $appends = [
        'active_license',
        'last_update_ip_formatted'
    ];

    #region Model Events
    protected static function booted()
    {
        static::creating(function (self $model) {
            if (is_null($model->expires_at)) {
                $model->expires_at = now();
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('user_id')) {
                $model->in_use = !is_null($model->user_id);
            }
        });

        static::updated(function (self $model) {
            // Aqui sim: após salvar, o certo é usar wasChanged
            if ($model->wasChanged('ip_pool_id')) {
                $old = $model->getOriginal('ip_pool_id');
                $new = $model->ip_pool_id;

                if ($old) {
                    IpPool::where('id', $old)->update(['in_use' => false]);
                }

                if ($new) {
                    IpPool::where('id', $new)->update(['in_use' => true]);
                }

                // Atualiza timestamp de rotação sem disparar eventos de novo
                $model->last_update_ip = now();
                $model->saveQuietly();

                // Executa o teste de IP
                $model->testAndSaveOutputIp();

                // Cria log de uso se houver user e ipPool
                if ($model->user && $model->ipPool) {
                    IpUsageLog::create([
                        'user_id'        => $model->user->id,
                        'user_name'      => $model->user->name,
                        'user_email'     => $model->user->email,
                        'squid_username' => $model->user->squid_username,
                        'ip_address'     => $model->ipPool->ip_address,
                        'port'           => $model->port,
                        'used_at'        => now(),
                    ]);
                }
            }
        });
    }
    #endregion

    #region Helpers
    public function testAndSaveOutputIp(): void
    {
        $user = $this->user;
        $host = $this->host;
        $port = $this->port;

        if (!$user || !$user->squid_username || !$user->squid_password || !$host || !$port) {
            return;
        }

        $client = new Client(['timeout' => 10.0]);

        $proxyUrl = sprintf(
            'http://%s:%s@%s:%d',
            $user->squid_username,
            $user->squid_password,
            $host,
            $port
        );

        $attempts = 3;
        $delaySec = 2;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                // pequeno atraso antes da 1ª/2ª/3ª tentativa, para dar tempo do squid reconfigurar
                if ($i > 1) {
                    sleep($delaySec);
                }

                $response = $client->get('https://ipv4.icanhazip.com', [
                    'proxy' => $proxyUrl,
                ]);

                $ip = trim($response->getBody()->getContents());

                $this->output_ip_address = $ip;
                $this->saveQuietly();

                return; // sucesso, sai
            } catch (ConnectException $e) {
                // erro típico de “não conectou” — tenta de novo
                logger()->warning(sprintf(
                    'Falha de conexão ao testar proxy porta %s (tentativa %d/%d): %s',
                    $port,
                    $i,
                    $attempts,
                    $e->getMessage()
                ));
            } catch (RequestException $e) {
                // outros erros HTTP
                logger()->warning(sprintf(
                    'RequestException ao testar proxy porta %s (tentativa %d/%d): %s',
                    $port,
                    $i,
                    $attempts,
                    $e->getMessage()
                ));
            } catch (\Throwable $e) {
                // qualquer outra coisa
                logger()->warning(sprintf(
                    'Erro inesperado ao testar proxy porta %s (tentativa %d/%d): %s',
                    $port,
                    $i,
                    $attempts,
                    $e->getMessage()
                ));
            }
        }

        // se chegou aqui, falhou todas — não lança exceção, só loga
        logger()->warning('Não foi possível obter IP de saída após rotação (porta ' . $port . ').');
    }

    public function assignNewIpForUser(): bool
    {
        if (!$this->user) {
            return false;
        }

        $userId = $this->user->id;

        $usedIps = IpUsageLog::where('user_id', $userId)
            ->pluck('ip_address')
            ->toArray();

        $ip = IpPool::where('in_use', false)
            ->whereNotIn('ip_address', $usedIps)
            ->first();

        if (!$ip && count($usedIps) > 0) {
            $oldestUsedIp = IpUsageLog::where('user_id', $userId)
                ->whereIn('ip_address', function ($query) {
                    $query->select('ip_address')
                        ->from('ip_pools')
                        ->where('in_use', false);
                })
                ->orderBy('used_at', 'asc')
                ->value('ip_address');

            if ($oldestUsedIp) {
                $ip = IpPool::where('ip_address', $oldestUsedIp)
                    ->where('in_use', false)
                    ->first();
            }
        }

        if (!$ip) {
            return false;
        }

        try {
            $remote = app(\App\Services\Squid\RemoteSquidService::class);
            $remote->applyIpRotationForPort($this, $ip);
            return true;
        } catch (\Exception $e) {
            logger()->error('Falha ao aplicar IP no Squid via SSH para porta ' . $this->port, [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    #endregion

    public function getLastUpdateIpFormattedAttribute()
    {
        return $this->last_update_ip
            ? $this->last_update_ip->timezone('America/Sao_Paulo')->format('d/m/Y H:i')
            : null;
    }

    #region Accessors / Scopes / Relations
    public function getActiveLicenseAttribute(): bool
    {
        // Ativa enquanto expires_at > agora
        return $this->expires_at instanceof Carbon
            ? $this->expires_at->gt(now())
            : false;
    }

    // “Pode renovar no dia do vencimento” => compara apenas a data
    public function scopeRenewableToday($query)
    {
        $today = now()->toDateString();
        return $query->whereDate('expires_at', '<=', $today);
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ipPool()
    {
        return $this->belongsTo(IpPool::class);
    }
    #endregion
}
