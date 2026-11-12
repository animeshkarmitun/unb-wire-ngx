<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Support\FeedCache;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "==> Seeding Canary Pipeline Fixtures...\n";

// 1. Dedicated Canary Category
$canaryCat = Category::updateOrCreate(
    ['slug' => 'canary-system-health'],
    [
        'name_en' => 'Canary System Health',
        'name_bn' => 'ক্যানারি সিস্টেম হেলথ',
        'sort_order' => 999,
    ]
);
echo "Category: {$canaryCat->name_en} (id: {$canaryCat->id})\n";

// 2. Dedicated Canary Package (Strictly restricted to canary-system-health)
$canaryPkg = Package::updateOrCreate(
    ['code' => 'CANARY-PROBE-PKG'],
    [
        'name' => 'Canary Health Probe Package',
        'kind' => 'bundle',
        'description' => 'Dedicated package for automated canary health probes',
        'entitlement_filter' => [
            'languages' => ['en'],
            'category_ids' => [$canaryCat->id],
            'media_kinds' => ['photo'],
        ],
        'status' => 'active',
    ]
);
echo "Package: {$canaryPkg->name} (id: {$canaryPkg->id})\n";

// 3. Dedicated Canary Client
$canaryClient = Client::updateOrCreate(
    ['code' => 'CNB'],
    [
        'name' => 'UNB Canary Quality Bot',
        'type' => 'online',
        'status' => 'active',
        'billing_email' => 'quality-bot@unb.com.bd',
    ]
);
echo "Client: {$canaryClient->name} (id: {$canaryClient->id})\n";

// 4. Attach Subscription
ClientPackage::updateOrCreate(
    ['client_id' => $canaryClient->id, 'package_id' => $canaryPkg->id],
    [
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addYears(5),
        'status' => 'active',
    ]
);

// 5. Client API Key (Raw: unb_live_canary_probe_key_99999)
$rawKey = 'unb_live_canary_probe_key_99999';
$keyHash = hash('sha256', $rawKey);

ClientApiKey::updateOrCreate(
    ['key_hash' => $keyHash],
    [
        'client_id' => $canaryClient->id,
        'name' => 'Canary Probe Key',
        'scopes' => ['feed:read', 'media:read'],
        'rate_limit_rpm' => 120,
        'expires_at' => now()->addYears(5),
    ]
);
echo "API Key seeded for client {$canaryClient->name}\n";

// 6. Bump epoch feed cache
FeedCache::bump();
echo "FeedCache bumped. CANARY_SEED_OK\n";
