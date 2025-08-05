<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\Squid\RemoteSquidService;

class CacheSquidBandwidthUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'squid:cache-bandwidth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula o uso de banda dos usuários do Squid e armazena no Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new RemoteSquidService();
        $report = $service->calculateBandwidthUsageAndCache();
        $this->info("Uso de banda cacheado para " . count($report) . " usuários.");
    }
}
