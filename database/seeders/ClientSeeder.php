<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = DB::table('clients')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'The Daily Star',
            'code' => 'DST',
            'type' => 'newspaper',
            'country' => 'BD',
            'timezone' => 'Asia/Dhaka',
            'status' => 'active',
            'billing_email' => 'billing@thedailystar.net',
            'notes' => 'Demo client seeded for M2-DB-010',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientRoleId = DB::table('roles')->where('name', 'Client Bangla (Without AP)')->value('id');

        DB::table('client_users')->insert([
            'client_id' => $clientId,
            'name' => 'Daily Star Desk',
            'email' => 'desk@thedailystar.net',
            'password' => Hash::make('password'),
            'client_role_id' => $clientRoleId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_api_keys')->insert([
            'client_id' => $clientId,
            'name' => 'prod feed poller',
            'key_hash' => hash('sha256', 'demo-api-key-'.$clientId),
            'scopes' => json_encode(['feed:read', 'media:download']),
            'rate_limit_rpm' => 60,
            'created_at' => now(),
        ]);

        $channels = [
            ['type' => 'api', 'config' => json_encode(['endpoint' => 'https://thedailystar.net/api/unb-wire']), 'status' => 'active'],
            ['type' => 'ftp', 'config' => json_encode(['host' => 'ftp.thedailystar.net', 'path' => '/unb-wire', 'username' => 'unb_wire', 'credential_ref' => 'vault:ftp/dst']), 'status' => 'active'],
            ['type' => 'webhook', 'config' => json_encode(['url' => 'https://thedailystar.net/webhooks/unb', 'secret_ref' => 'vault:webhook/dst']), 'status' => 'active'],
        ];

        foreach ($channels as $ch) {
            DB::table('client_channels')->insert([
                'client_id' => $clientId,
                'type' => $ch['type'],
                'config' => $ch['config'],
                'status' => $ch['status'],
                'failure_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $packageId = DB::table('packages')->where('code', 'PREMIUM-BUNDLE')->value('id');
        if ($packageId) {
            DB::table('client_packages')->insert([
                'client_id' => $clientId,
                'package_id' => $packageId,
                'starts_at' => now(),
                'ends_at' => null,
                'status' => 'active',
                'created_at' => now(),
            ]);
        }
    }
}
