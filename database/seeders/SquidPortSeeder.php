<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SquidPortSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $basePort = 1000;

    for ($i = 0; $i < 128; $i++) {
      DB::table('squid_ports')->insert([
        'user_id' => null,
        'port' => $basePort + $i,
        'instance' => 'instance-1',
        'host' => 'proxy.bcopy.com.br',
        'expires_at' => now(),
        'in_use' => false,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }
  }
}
