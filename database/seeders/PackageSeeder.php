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
                'stories_quota' => 2000,
                'media_quota' => 1000,
                'entitlement_filter' => json_encode([
                    'languages' => ['en', 'bn'],
                    'category_ids' => null,
                    'media_kinds' => ['photo', 'video'],
                    'is_addon' => false,
                    'grad' => 'g1',
                    'wire' => 'Full wire — all categories',
                    'quota' => '2000 stories · 1000 media downloads',
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
                'stories_quota' => 800,
                'media_quota' => 400,
                'entitlement_filter' => json_encode([
                    'languages' => ['en', 'bn'],
                    'category_ids' => null,
                    'media_kinds' => ['photo'],
                    'is_addon' => false,
                    'grad' => 'g6',
                    'wire' => 'Full wire — excluding exclusives',
                    'quota' => '800 stories · 400 media downloads',
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
                'name' => 'Basic Wire + Images',
                'kind' => 'news',
                'description' => 'Full text + story-accompanying images; no original media downloads',
                'stories_quota' => 200,
                'media_quota' => 0,
                'entitlement_filter' => json_encode([
                    'languages' => ['en'],
                    'category_ids' => null,
                    'media_kinds' => ['photo'],
                    'is_addon' => false,
                    'grad' => 'g8',
                    'wire' => 'Full text + accompanying images',
                    'quota' => '200 stories · no original-media downloads',
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
                'description' => 'Retired 2025 — replaced by Basic Wire + Images',
                'stories_quota' => 200,
                'media_quota' => 0,
                'entitlement_filter' => json_encode([
                    'languages' => ['en'],
                    'category_ids' => null,
                    'media_kinds' => [],
                    'is_addon' => false,
                    'grad' => 'g4',
                    'wire' => 'Headlines + briefs only',
                    'quota' => '200 stories · no original-media downloads',
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
                'stories_quota' => null,
                'media_quota' => null,
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
                'stories_quota' => null,
                'media_quota' => null,
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
                'stories_quota' => null,
                'media_quota' => null,
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
                    'stories_quota' => $pkg['stories_quota'],
                    'media_quota' => $pkg['media_quota'],
                    'price_monthly' => $pkg['price_monthly'],
                    'status' => $pkg['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
