<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Delivery;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\MediaReview;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\StoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
