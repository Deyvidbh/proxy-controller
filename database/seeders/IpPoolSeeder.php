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

        for ($ip = $startIp; $ip <= $endIp; $ip++) {
            $batch[] = [
                'ip_address' => long2ip($ip),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) === 1000) {
                IpPool::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            IpPool::insert($batch);
        }

        $total = $endIp - $startIp + 1;
        $this->command->info("IpPoolSeeder: $total IPs inseridos.");
    }
}
