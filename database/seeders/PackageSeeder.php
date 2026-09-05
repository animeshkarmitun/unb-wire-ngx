<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // Clean up test packages
        DB::table('packages')->whereNotIn('code', [
            'PREMIUM-BUNDLE',
            'STANDARD-NEWS',
            'BASIC-NEWS',
            'DISTRICT-NEWS',
            'ADDON-AP-WORLD',
            'ADDON-BANGLA',
            'ADDON-SPORTS',
        ])->delete();

        $packages = [
            // Core subscription packages
            [
                'code' => 'PREMIUM-BUNDLE',
                'name' => 'Premium Wire + Media',
                'kind' => 'bundle',
                'description' => 'Full-service wire for national outlets',
                'entitlement_filter' => json_encode([
                    'languages' => ['en', 'bn'],
                    'category_ids' => null,
                    'media_kinds' => ['photo', 'video'],
                    'is_addon' => false,
                    'grad' => 'g1',
                    'wire' => 'Full wire — all categories',
                    'quota' => 'Unlimited',
                    'video' => true,
                    'excl' => true,
                    'api' => true,
                    'support' => true,
                    'ui_status' => 'live',
                ]),
                'price_monthly' => 85000,
                'status' => 'active',
            ],
            [
                'code' => 'STANDARD-NEWS',
                'name' => 'Standard Wire',
                'kind' => 'news',
                'description' => 'The core text wire with a solid photo allowance',
                'entitlement_filter' => json_encode([
                    'languages' => ['en', 'bn'],
                    'category_ids' => null,
                    'media_kinds' => ['photo'],
                    'is_addon' => false,
                    'grad' => 'g6',
                    'wire' => 'Full wire — excluding exclusives',
                    'quota' => '800 photos',
                    'video' => false,
                    'excl' => false,
                    'api' => true,
                    'support' => false,
                    'ui_status' => 'live',
                ]),
                'price_monthly' => 45000,
                'status' => 'active',
            ],
            [
                'code' => 'BASIC-NEWS',
                'name' => 'Basic Headlines',
                'kind' => 'news',
                'description' => 'Headlines + briefs for small portals and blogs',
                'entitlement_filter' => json_encode([
                    'languages' => ['en'],
                    'category_ids' => null,
                    'media_kinds' => [],
                    'is_addon' => false,
                    'grad' => 'g8',
                    'wire' => 'Headlines + briefs only',
                    'quota' => 'No photos',
                    'video' => false,
                    'excl' => false,
                    'api' => false,
                    'support' => false,
                    'ui_status' => 'live',
                ]),
                'price_monthly' => 18000,
                'status' => 'active',
            ],
            [
                'code' => 'DISTRICT-NEWS',
                'name' => 'District Wire',
                'kind' => 'news',
                'description' => 'Retired 2025 — replaced by Basic Headlines',
                'entitlement_filter' => json_encode([
                    'languages' => ['en'],
                    'category_ids' => null,
                    'media_kinds' => [],
                    'is_addon' => false,
                    'grad' => 'g4',
                    'wire' => 'Headlines + briefs only',
                    'quota' => '200 photos',
                    'video' => false,
                    'excl' => false,
                    'api' => false,
                    'support' => false,
                    'ui_status' => 'archived',
                ]),
                'price_monthly' => 12000,
                'status' => 'archived',
            ],

            // Add-ons
            [
                'code' => 'ADDON-AP-WORLD',
                'name' => 'AP World pack',
                'kind' => 'photos',
                'description' => 'AP world wire + licensed AP photo feed',
                'entitlement_filter' => json_encode([
                    'is_addon' => true,
                    'tiers' => ['Premium', 'Standard'],
                    'grad' => 'g2',
                    'languages' => ['en'],
                    'media_kinds' => ['photo'],
                    'ui_status' => 'live',
                ]),
                'price_monthly' => 30000,
                'status' => 'active',
            ],
            [
                'code' => 'ADDON-BANGLA',
                'name' => 'Bangla service',
                'kind' => 'news',
                'description' => 'Full Bangla wire + Bangla media captions',
                'entitlement_filter' => json_encode([
                    'is_addon' => true,
                    'tiers' => ['Premium', 'Standard', 'Basic'],
                    'grad' => 'g3',
                    'languages' => ['bn'],
                    'media_kinds' => ['photo'],
                    'ui_status' => 'live',
                ]),
                'price_monthly' => 20000,
                'status' => 'active',
            ],
            [
                'code' => 'ADDON-SPORTS',
                'name' => 'Sports data feed',
                'kind' => 'news',
                'description' => 'Ball-by-ball cricket + fixtures API for sports desks',
                'entitlement_filter' => json_encode([
                    'is_addon' => true,
                    'tiers' => ['Premium'],
                    'grad' => 'g5',
                    'languages' => ['en', 'bn'],
                    'media_kinds' => [],
                    'ui_status' => 'draft',
                ]),
                'price_monthly' => 12000,
                'status' => 'archived',
            ],
        ];

        foreach ($packages as $pkg) {
            DB::table('packages')->updateOrInsert(
                ['code' => $pkg['code']],
                [
                    'name' => $pkg['name'],
                    'kind' => $pkg['kind'],
                    'description' => $pkg['description'],
                    'entitlement_filter' => $pkg['entitlement_filter'],
                    'price_monthly' => $pkg['price_monthly'],
                    'status' => $pkg['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
