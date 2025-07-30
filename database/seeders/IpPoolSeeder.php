<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IpPool;

class IpPoolSeeder extends Seeder
{
    public function run(): void
    {
        $startIp = ip2long('100.64.0.2');
        $endIp   = ip2long('100.64.2.199');

        $batch = [];
        $totalInserted = 0;

        for ($ip = $startIp; $ip <= $endIp; $ip++) {
            $ipString = long2ip($ip);

            // Ignora IPs terminando com .0 ou .255
            $lastOctet = (int) explode('.', $ipString)[3];
            if ($lastOctet === 0 || $lastOctet === 255) {
                continue;
            }

            $batch[] = [
                'ip_address' => $ipString,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $totalInserted++;

            if (count($batch) === 1000) {
                IpPool::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            IpPool::insert($batch);
        }

        $this->command->info("IpPoolSeeder: $totalInserted IPs válidos inseridos.");
    }
}
