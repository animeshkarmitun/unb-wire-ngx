<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Delivery;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\StoryService;
use Illuminate\Contracts\Console\Kernel;
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
