<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Delivery;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\MediaReview;
use App\Models\Package;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\StoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$action = $argv[1] ?? 'notifications';

if ($action === 'notifications') {
    $u = User::where('email', 'test@example.com')->first();
    if ($u) {
        $u->notifications()->delete();
        $u->notifyNow(new StoryNotification('review_requested', ['story_id' => 1, 'headline' => 'Metro rail expansion phase 2 approved', 'actor_id' => 1]));
        $u->notifyNow(new StoryNotification('approved', ['story_id' => 2, 'headline' => 'Central Bank announces export incentives', 'actor_id' => 1]));
    }
    echo 'notifications seeded count: '.$u->unreadNotifications()->count()."\n";
}

if ($action === 'audit') {
    AuditLog::create([
        'actor_type' => 'user',
        'actor_id' => 1,
        'action' => 'published',
        'entity_type' => 'Story',
        'entity_id' => 42,
        'diff' => ['headline' => 'Test headline'],
        'ip' => '127.0.0.1',
        'user_agent' => 'Playwright',
        'correlation_id' => '11111111-2222-3333-4444-555555555555',
        'created_at' => now(),
    ]);
    AuditLog::create([
        'actor_type' => 'user',
        'actor_id' => 1,
        'action' => 'role.updated',
        'entity_type' => 'role',
        'entity_id' => 2,
        'diff' => ['permissions' => ['stories.view']],
        'ip' => '127.0.0.1',
        'user_agent' => 'Playwright',
        'correlation_id' => '22222222-3333-4444-5555-666666666666',
        'created_at' => now(),
    ]);
    echo "audit seeded\n";
}

if ($action === 'story_versions') {
    $u = User::where('email', 'test@example.com')->first();
    $cat = Category::first();
    $s = Story::where('headline', 'E2E Diff Test v2')->first();
    if (! $s && $u && $cat) {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en',
            'headline' => 'E2E Diff Test v1',
            'brief' => 'Brief v1',
            'body_html' => '<p>Original paragraph v1</p>',
            'category_id' => $cat->id,
        ], $u);
        $s = app(StoryService::class)->updateDraft($s, [
            'headline' => 'E2E Diff Test v2',
            'brief' => 'Brief v2',
            'body_html' => '<p>Updated paragraph v2</p>',
        ], 1, $u);
    }
    echo $s ? ($s->id.':'.$s->public_id) : '';
}

if ($action === 'deliveries') {
    $client = Client::first();
    $channel = ClientChannel::first() ?? ($client ? ClientChannel::create([
        'client_id' => $client->id,
        'type' => 'email',
        'config' => ['email' => 'test@example.com'],
        'status' => 'active',
    ]) : null);
    if ($client && $channel) {
        Delivery::firstOrCreate(
            ['idempotency_key' => 'e2e-failed-delivery-1'],
            [
                'client_id' => $client->id,
                'channel_id' => $channel->id,
                'deliverable_type' => 'story',
                'deliverable_id' => 1,
                'status' => 'failed',
                'attempt_count' => 3,
                'payload_hash' => str_repeat('a', 64),
                'response_code' => 500,
                'created_at' => now(),
            ]
        );
        Delivery::firstOrCreate(
            ['idempotency_key' => 'e2e-delivered-delivery-2'],
            [
                'client_id' => $client->id,
                'channel_id' => $channel->id,
                'deliverable_type' => 'story',
                'deliverable_id' => 2,
                'status' => 'delivered',
                'attempt_count' => 1,
                'payload_hash' => str_repeat('b', 64),
                'response_code' => 200,
                'created_at' => now(),
            ]
        );
    }
    echo "deliveries seeded\n";
}

if ($action === 'superadmin') {
    $adminRole = Role::where('name', 'Admin')->first();
    User::firstOrCreate(
        ['email' => 'regularadmin@unbnews.org'],
        [
            'public_id' => (string) Str::ulid(),
            'name' => 'Regular Admin',
            'password' => Hash::make('password'),
            'role_id' => $adminRole?->id,
            'desk' => 'Management',
            'status' => 'active',
            'is_superadmin' => false,
            'timezone' => 'Asia/Dhaka',
            'last_seen_at' => now(),
        ]
    );
    echo "superadmin seeded\n";
}

if ($action === 'ai') {
    DB::table('settings')->updateOrInsert(
        ['key' => 'ai.desk'],
        ['value' => json_encode([
            'preeditEn' => true,
            'preeditBn' => true,
            'preeditPhotos' => false,
            'autoPublish' => false,
            'autoCategories' => ['Weather', 'Sports results'],
            'monthlyCap' => 500000,
            'stylePrompt' => 'You are a UNB wire copy editor. Polish for wire service clarity.',
            'killed' => false,
        ])]
    );
    echo "ai settings seeded\n";
}

if ($action === 'concurrency') {
    $shohel = User::where('email', 'shohel@unbnews.org')->first();
    $cat = Category::first();
    if ($shohel && $cat) {
        $story = Story::updateOrCreate(
            ['public_id' => '01JCONCURRENCYTEST00000001'],
            [
                'language' => 'en',
                'headline' => 'Sylhet flood relief dispatch operation underway',
                'sub_head' => 'Emergency response unit mobilised',
                'brief' => 'District administration launches emergency relief distribution in flood-affected upazilas.',
                'body_html' => '<p>District administration launches emergency relief distribution in flood-affected upazilas.</p>',
                'body_text' => 'District administration launches emergency relief distribution in flood-affected upazilas.',
                'status' => 'draft',
                'category_id' => $cat->id,
                'created_by' => $shohel->id,
                'owner_id' => $shohel->id,
                'locked_by' => $shohel->id,
                'locked_at' => now(),
                'version' => 1,
                'priority' => 'routine',
                'source' => 'desk',
            ]
        );

        $story->notes()->delete();
        $story->events()->delete();

        $story->events()->create([
            'actor_id' => $shohel->id,
            'action' => 'created',
            'from_status' => 'draft',
            'to_status' => 'draft',
            'payload' => ['initial' => true],
        ]);
        $story->notes()->create([
            'user_id' => $shohel->id,
            'kind' => 'note',
            'is_internal' => true,
            'body' => 'Initial field notes received from Sylhet bureau.',
        ]);

        echo "concurrency story seeded id: {$story->id}\n";
    }
}

if ($action === 'concurrency-stale') {
    $story = Story::where('public_id', '01JCONCURRENCYTEST00000001')->first();
    if ($story) {
        $story->increment('version');
        echo "concurrency story version bumped to {$story->version}\n";
    }
}

if ($action === 'media') {
    $mim = User::where('email', 'mim@unbnews.org')->first();
    if (! $mim) {
        $role = Role::where('name', 'Uploader-English')->first() ?? Role::first();
        $mim = User::create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Mim Akter',
            'email' => 'mim@unbnews.org',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'desk' => 'Photo',
            'status' => 'active',
            'timezone' => 'Asia/Dhaka',
        ]);
    }

    // Clear specific media burst keys
    Cache::forget('notify_burst:media:01JMEDIABATCH0000000001:media_approved');
    Cache::forget('notify_burst:media:01JMEDIABATCH0000000001:media_rejected');
    Cache::forget('notify_burst:media:01JMEDIABATCH0000000001:media_reedit');

    // Clean previous test batches/assets
    $prevBatches = MediaBatch::where('event_label', 'like', '%Sylhet flood relief%')->get();
    foreach ($prevBatches as $pb) {
        MediaReview::whereIn('asset_id', $pb->assets()->pluck('id'))->delete();
        $pb->assets()->forceDelete();
        $pb->delete();
    }

    $batch = MediaBatch::create([
        'public_id' => '01JMEDIABATCH0000000001',
        'uploader_id' => $mim->id,
        'event_label' => 'Sylhet flood relief field photos',
        'urgency' => 'urgent',
        'status' => 'pending',
        'submitted_at' => now()->subMinutes(12),
    ]);

    $asset1 = MediaAsset::create([
        'public_id' => '01JMEDIAASSET0000000001',
        'batch_id' => $batch->id,
        'kind' => 'photo',
        'status' => 'field',
        'title' => 'Emergency relief boat in submerged village',
        'caption' => 'Volunteers distribute drinking water to marooned villagers in Sylhet',
        'credit_line' => 'Photo: Mim Akter / UNB',
        'photographer_id' => $mim->id,
        'uploaded_by' => $mim->id,
        'location_city' => 'Sylhet',
        'location_country' => 'Bangladesh',
        'en_tags' => ['flood', 'relief', 'sylhet'],
        'mime' => 'image/jpeg',
        'size_bytes' => 2450000,
        'width' => 1200,
        'height' => 800,
        'storage_disk' => 'public',
        'original_path' => 'media/test/relief-boat.jpg',
        'checksum' => hash('sha256', 'test-relief-boat-01'),
        'derivatives' => ['grad' => 'g2', 'r' => 1.5],
    ]);

    $asset2 = MediaAsset::create([
        'public_id' => '01JMEDIAASSET0000000002',
        'batch_id' => $batch->id,
        'kind' => 'photo',
        'status' => 'field',
        'title' => 'Temporary shelter at higher ground',
        'caption' => 'Villagers take refuge on an elevated road embankment with their belongings',
        'credit_line' => 'Photo: Mim Akter / UNB',
        'photographer_id' => $mim->id,
        'uploaded_by' => $mim->id,
        'location_city' => 'Sylhet',
        'location_country' => 'Bangladesh',
        'en_tags' => ['flood', 'shelter', 'displaced'],
        'mime' => 'image/jpeg',
        'size_bytes' => 3120000,
        'width' => 1200,
        'height' => 800,
        'storage_disk' => 'public',
        'original_path' => 'media/test/shelter.jpg',
        'checksum' => hash('sha256', 'test-shelter-02'),
        'derivatives' => ['grad' => 'g4', 'r' => 1.5],
    ]);

    $libAsset = MediaAsset::updateOrCreate(
        ['public_id' => '01JMEDIALIBASSET00000001'],
        [
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'BAPA press conference on river water quality',
            'caption' => 'BAPA press conference on Buriganga river water pollution',
            'credit_line' => 'Photo: Staff Photographer / UNB',
            'photographer_id' => $mim->id,
            'uploaded_by' => $mim->id,
            'approved_by' => 1,
            'approved_at' => now()->subDay(),
            'created_at' => now(),
            'location_city' => 'Dhaka',
            'location_country' => 'Bangladesh',
            'en_tags' => ['environment', 'pollution', 'bapa'],
            'mime' => 'image/jpeg',
            'size_bytes' => 1950000,
            'width' => 1200,
            'height' => 800,
            'storage_disk' => 'public',
            'original_path' => 'media/test/bapa.jpg',
            'checksum' => hash('sha256', 'test-bapa-01'),
            'derivatives' => ['grad' => 'g1', 'r' => 1.5],
        ]
    );

    echo "media seeded batch: {$batch->id}, asset1: {$asset1->id}, asset2: {$asset2->id}, lib: {$libAsset->id}\n";
}

if ($action === 'wire_api') {
    // 1. Categories
    $catPolitics = Category::firstOrCreate(['slug' => 'national-politics'], ['name_en' => 'National Politics', 'name_bn' => 'জাতীয় রাজনীতি', 'is_active' => true]);
    $catBusiness = Category::firstOrCreate(['slug' => 'economy-business'], ['name_en' => 'Economy & Business', 'name_bn' => 'অর্থনীতি ও বাণিজ্য', 'is_active' => true]);
    $catSports = Category::firstOrCreate(['slug' => 'sports-cricket'], ['name_en' => 'Sports', 'name_bn' => 'খেলাধুলা', 'is_active' => true]);

    // 2. Packages
    $pkgEnPol = Package::updateOrCreate(
        ['code' => 'PKG-E2E-EN-POL'],
        [
            'name' => 'English Politics Wire',
            'kind' => 'news',
            'description' => 'English language national politics wire',
            'entitlement_filter' => [
                'languages' => ['en'],
                'category_ids' => [$catPolitics->id],
                'media_kinds' => ['photo'],
            ],
            'price_monthly' => 25000,
            'status' => 'active',
        ]
    );

    $pkgBnAll = Package::updateOrCreate(
        ['code' => 'PKG-E2E-BN-ALL'],
        [
            'name' => 'Bangla Full Wire',
            'kind' => 'news',
            'description' => 'Full Bangla wire feed',
            'entitlement_filter' => [
                'languages' => ['bn'],
                'category_ids' => null,
                'media_kinds' => ['photo'],
            ],
            'price_monthly' => 30000,
            'status' => 'active',
        ]
    );

    // 3. Clients
    $clientDailyStar = Client::updateOrCreate(
        ['code' => 'DST-E2E'],
        [
            'name' => 'The Daily Star E2E',
            'type' => 'newspaper',
            'status' => 'active',
            'country' => 'BD',
            'timezone' => 'Asia/Dhaka',
            'billing_email' => 'dailystar-e2e@example.com',
        ]
    );

    $clientProthomAlo = Client::updateOrCreate(
        ['code' => 'PALO-E2E'],
        [
            'name' => 'Prothom Alo E2E',
            'type' => 'newspaper',
            'status' => 'active',
            'country' => 'BD',
            'timezone' => 'Asia/Dhaka',
            'billing_email' => 'prothomalo-e2e@example.com',
        ]
    );

    $clientSuspended = Client::updateOrCreate(
        ['code' => 'SUSP-E2E'],
        [
            'name' => 'Suspended Media E2E',
            'type' => 'online',
            'status' => 'suspended',
            'country' => 'BD',
            'timezone' => 'Asia/Dhaka',
            'billing_email' => 'suspended-e2e@example.com',
        ]
    );

    // 4. Subscriptions
    ClientPackage::updateOrCreate(
        ['client_id' => $clientDailyStar->id, 'package_id' => $pkgEnPol->id],
        [
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'auto_renew' => true,
        ]
    );

    ClientPackage::updateOrCreate(
        ['client_id' => $clientProthomAlo->id, 'package_id' => $pkgBnAll->id],
        [
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'auto_renew' => true,
        ]
    );

    // 5. API Keys
    $keyA = ClientApiKey::updateOrCreate(
        ['key_hash' => hash('sha256', 'unb_live_testkey_dailystar_001')],
        [
            'client_id' => $clientDailyStar->id,
            'name' => 'Daily Star Primary API Key',
            'scopes' => ['feed:read', 'media:read'],
            'rate_limit_rpm' => 60,
            'expires_at' => now()->addYear(),
        ]
    );

    $keyB = ClientApiKey::updateOrCreate(
        ['key_hash' => hash('sha256', 'unb_live_testkey_feedonly_002')],
        [
            'client_id' => $clientDailyStar->id,
            'name' => 'Daily Star Feed Only Key',
            'scopes' => ['feed:read'],
            'rate_limit_rpm' => 60,
            'expires_at' => now()->addYear(),
        ]
    );

    $keyC = ClientApiKey::updateOrCreate(
        ['key_hash' => hash('sha256', 'unb_live_testkey_ratelimited_003')],
        [
            'client_id' => $clientDailyStar->id,
            'name' => 'Daily Star Rate Limited Key',
            'scopes' => ['feed:read'],
            'rate_limit_rpm' => 2,
            'expires_at' => now()->addYear(),
        ]
    );

    $keyD = ClientApiKey::updateOrCreate(
        ['key_hash' => hash('sha256', 'unb_live_testkey_suspended_004')],
        [
            'client_id' => $clientSuspended->id,
            'name' => 'Suspended Key',
            'scopes' => ['feed:read', 'media:read'],
            'rate_limit_rpm' => 60,
            'expires_at' => now()->addYear(),
        ]
    );

    $keyE = ClientApiKey::updateOrCreate(
        ['key_hash' => hash('sha256', 'unb_live_testkey_prothomalo_005')],
        [
            'client_id' => $clientProthomAlo->id,
            'name' => 'Prothom Alo Primary Key',
            'scopes' => ['feed:read', 'media:read'],
            'rate_limit_rpm' => 60,
            'expires_at' => now()->addYear(),
        ]
    );

    RateLimiter::clear('api-key:'.$keyC->id);

    // 6. Test Stories
    $author = User::first();

    $storyEnPol = Story::updateOrCreate(
        ['public_id' => '01JWIREAPITESTENPOL0000001'],
        [
            'language' => 'en',
            'headline' => 'Parliament approves National Budget for FY2026-27',
            'sub_head' => 'Focus on macro-economic stability',
            'brief' => 'Lawmakers pass national budget for the upcoming financial year with priority on inflation control.',
            'body_html' => '<p>Lawmakers pass national budget for the upcoming financial year with priority on inflation control.</p>',
            'body_text' => 'Lawmakers pass national budget for the upcoming financial year with priority on inflation control.',
            'category_id' => $catPolitics->id,
            'status' => 'published',
            'published_at' => now()->subHours(2),
            'created_by' => $author->id,
            'owner_id' => $author->id,
            'version' => 1,
            'source' => 'desk',
        ]
    );

    $storyEnBiz = Story::updateOrCreate(
        ['public_id' => '01JWIREAPITESTENBIZ0000002'],
        [
            'language' => 'en',
            'headline' => 'Central Bank raises policy repo rate by 25 basis points',
            'sub_head' => 'Monetary tightening to contain inflation',
            'brief' => 'Bangladesh Bank raises policy repo rate to tighten money market liquidity.',
            'body_html' => '<p>Bangladesh Bank raises policy repo rate to tighten money market liquidity.</p>',
            'body_text' => 'Bangladesh Bank raises policy repo rate to tighten money market liquidity.',
            'category_id' => $catBusiness->id,
            'status' => 'published',
            'published_at' => now()->subHours(3),
            'created_by' => $author->id,
            'owner_id' => $author->id,
            'version' => 1,
            'source' => 'desk',
        ]
    );

    $storyBnSpt = Story::updateOrCreate(
        ['public_id' => '01JWIREAPITESTBNSPT0000003'],
        [
            'language' => 'bn',
            'headline' => 'এশিয়া কাপ ক্রিকেটে বাংলাদেশের শ্বাসরুদ্ধকর জয়',
            'sub_head' => 'শেষ ওভারে চার মেরে ম্যাচ জিতলেন অধিনায়ক',
            'brief' => 'মিরপুর শের-ই-বাংলা জাতীয় ক্রিকেট স্টেডিয়ামে নাটকীয় ম্যাচে জয় তুলে নিল বাংলাদেশ।',
            'body_html' => '<p>মিরপুর শের-ই-বাংলা জাতীয় ক্রিকেট স্টেডিয়ামে নাটকীয় ম্যাচে জয় তুলে নিল বাংলাদেশ।</p>',
            'body_text' => 'মিরপুর শের-ই-বাংলা জাতীয় ক্রিকেট স্টেডিয়ামে নাটকীয় ম্যাচে জয় তুলে নিল বাংলাদেশ।',
            'category_id' => $catSports->id,
            'status' => 'published',
            'published_at' => now()->subHours(1),
            'created_by' => $author->id,
            'owner_id' => $author->id,
            'version' => 1,
            'source' => 'desk',
        ]
    );

    $storyKilled = Story::updateOrCreate(
        ['public_id' => '01JWIREAPITESTKILLED000004'],
        [
            'language' => 'en',
            'headline' => 'Erroneous report on highway accident casualties retracted',
            'sub_head' => 'Correction notice',
            'brief' => 'STORY KILLED / RETRACTED',
            'body_html' => '<p>This story has been killed and retracted from the UNB wire.</p>',
            'body_text' => 'This story has been killed and retracted from the UNB wire.',
            'category_id' => $catPolitics->id,
            'status' => 'killed',
            'published_at' => now()->subHours(5),
            'created_by' => $author->id,
            'owner_id' => $author->id,
            'version' => 2,
            'source' => 'desk',
        ]
    );

    $media = MediaAsset::updateOrCreate(
        ['public_id' => '01JWIREAPITESTMEDIA0000001'],
        [
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Parliament Budget Session Highlights',
            'caption' => 'Lawmakers in Parliament during the passage of FY2026-27 National Budget',
            'credit_line' => 'Photo: UNB Archive',
            'uploaded_by' => $author->id,
            'approved_by' => $author->id,
            'approved_at' => now()->subDay(),
            'storage_disk' => 'public',
            'original_path' => 'media/test/budget-session.jpg',
            'checksum' => hash('sha256', 'test-wire-media-001'),
            'size_bytes' => 1540000,
            'width' => 1200,
            'height' => 800,
            'mime' => 'image/jpeg',
            'derivatives' => ['grad' => 'g1', 'r' => 1.5],
        ]
    );

    Storage::disk('public')->put('media/test/budget-session.jpg', 'fake-jpeg-content');
    $storyEnPol->media()->syncWithoutDetaching([$media->id => ['role' => 'featured', 'sort_order' => 1]]);

    // Forget client feed cache keys
    Cache::forget('feed:v1:'.$clientDailyStar->id.':'.md5('http://localhost:8000/api/v1/feed'));
    Cache::forget('feed:v1:'.$clientDailyStar->id.':'.md5('http://127.0.0.1:8000/api/v1/feed'));
    Cache::forget('feed:v1:'.$clientProthomAlo->id.':'.md5('http://localhost:8000/api/v1/feed'));
    Cache::forget('feed:v1:'.$clientProthomAlo->id.':'.md5('http://127.0.0.1:8000/api/v1/feed'));

    echo "wire_api seeded: stories=[{$storyEnPol->id}, {$storyEnBiz->id}, {$storyBnSpt->id}, {$storyKilled->id}], media={$media->id}\n";
}

if ($action === 'download_count') {
    $itemType = $argv[2] ?? null;
    $q = DB::table('downloads');
    if ($itemType) {
        $q->where('item_type', $itemType);
    }
    echo (int) $q->count();
    exit(0);
}
