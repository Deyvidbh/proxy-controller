<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use App\Models\IpUsageLog;

use Carbon\Carbon;

class SquidPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_active',
        'in_use',
        'output_ip_address',
        'last_update_ip'
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at'       => 'datetime',
    ];

    protected $appends = ['active_license'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->expires_at)) {
                $model->expires_at = now();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('user_id')) {
                $model->in_use = !is_null($model->user_id);
            }

            if ($model->isDirty('ip_pool_id')) {
                $model->last_update_ip = now();
            }
        });

        static::updated(function ($model) {
            if ($model->isDirty('ip_pool_id')) {
                $old = $model->getOriginal('ip_pool_id');
                $new = $model->ip_pool_id;

                if ($old) {
                    IpPool::where('id', $old)->update(['in_use' => false]);
                }

                if ($new) {
                    IpPool::where('id', $new)->update(['in_use' => true]);
                }

                // Executa o teste de IP
                $model->testAndSaveOutputIp();

                // Cria o log de uso
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

    public function testAndSaveOutputIp(): void
    {
        $user = $this->user;
        $host = $this->host;
        $port = $this->port;

        if (!$user || !$user->squid_username || !$user->squid_password || !$host || !$port) {
            return;
        }

        try {
            $client = new Client(['timeout' => 10.0]);

            $proxyUrl = sprintf(
                'http://%s:%s@%s:%d',
                $user->squid_username,
                $user->squid_password,
                $host,
                $port
            );

            $response = $client->get('https://ipv4.icanhazip.com', [
                'proxy' => $proxyUrl,
            ]);

            $ip = trim($response->getBody()->getContents());

            $this->output_ip_address = $ip;
            $this->saveQuietly();
        } catch (RequestException $e) {
            logger()->warning('Falha ao testar proxy na porta ' . $port, [
                'error' => $e->getMessage(),
            ]);
        }
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
                ->pluck('ip_address')
                ->first();

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

    public function getActiveLicenseAttribute()
    {
        return $this->expires_at && Carbon::now()->lt(Carbon::parse($this->expires_at));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ipPool()
    {
        return $this->belongsTo(IpPool::class);
    }
}
