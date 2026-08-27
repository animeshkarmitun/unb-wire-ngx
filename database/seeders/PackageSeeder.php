<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'code' => 'PREMIUM-BUNDLE',
                'name' => 'Premium Wire + Media',
                'kind' => 'bundle',
                'description' => 'Full-service wire for national outlets',
                'entitlement_filter' => json_encode(['languages' => ['en', 'bn'], 'category_ids' => null, 'media_kinds' => ['photo', 'video']]),
                'price_monthly' => 85000,
                'status' => 'active',
            ],
            [
                'code' => 'STANDARD-NEWS',
                'name' => 'Standard Wire',
                'kind' => 'news',
                'description' => 'The core text wire with a solid photo allowance',
                'entitlement_filter' => json_encode(['languages' => ['en', 'bn'], 'category_ids' => null, 'media_kinds' => ['photo']]),
                'price_monthly' => 45000,
                'status' => 'active',
            ],
            [
                'code' => 'BASIC-NEWS',
                'name' => 'Basic Headlines',
                'kind' => 'news',
                'description' => 'Headlines + briefs for small portals and blogs',
                'entitlement_filter' => json_encode(['languages' => ['en'], 'category_ids' => null, 'media_kinds' => []]),
                'price_monthly' => 18000,
                'status' => 'active',
            ],
            [
                'code' => 'DISTRICT-NEWS',
                'name' => 'District Wire',
                'kind' => 'news',
                'description' => 'Retired 2025 — replaced by Basic Headlines',
                'entitlement_filter' => json_encode(['languages' => ['en'], 'category_ids' => null, 'media_kinds' => []]),
                'price_monthly' => 12000,
                'status' => 'archived',
            ],
        ];

        foreach ($packages as $pkg) {
            DB::table('packages')->insert([
                'code' => $pkg['code'],
                'name' => $pkg['name'],
                'kind' => $pkg['kind'],
                'description' => $pkg['description'],
                'entitlement_filter' => $pkg['entitlement_filter'],
                'price_monthly' => $pkg['price_monthly'],
                'status' => $pkg['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
