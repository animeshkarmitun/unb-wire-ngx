<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Notifications\ClientStoryNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

echo "=== [TEST 1]: Client User Notification Invariants ===\n";

$client = Client::where('name', 'The Daily Star')->first() ?? Client::first();
if (!$client) {
    echo "FAIL: No client found\n";
    exit(1);
}

// Ensure active client user
$clientUser = ClientUser::where('client_id', $client->id)->first();
if (!$clientUser) {
    $clientUser = ClientUser::create([
        'client_id' => $client->id,
        'name' => 'Daily Star Desk',
        'email' => 'desk@dailystar-test.com',
        'password' => Hash::make('password123'),
        'status' => 'active',
    ]);
}

echo "Client: {$client->name} (ID: {$client->id})\n";
echo "Client User: {$clientUser->name} <{$clientUser->email}>\n";

// Clear existing notifications for test cleanliness
$clientUser->notifications()->delete();
echo "Cleared prior notifications. Count: " . $clientUser->notifications()->count() . "\n";

// Seed 3 test notifications: Breaking, Updated, Killed
$clientUser->notify(new ClientStoryNotification('story.breaking', [
    'title' => '🚨 [BREAKING] Major Flash Floods Reported in Sylhet',
    'message' => 'Surma and Kushiyara rivers flowing above danger marks.',
    'public_id' => '01MFLOODPROBE000001',
    'urgency' => 'breaking',
    'category' => 'National',
]));

$clientUser->notify(new ClientStoryNotification('story.updated', [
    'title' => '🔄 [UPDATE] Highway Traffic Restored at Meghna Bridge',
    'message' => 'Vehicular movement resumes after four hours of gridlock.',
    'public_id' => '01MTRAFFICPROBE002',
    'urgency' => 'routine',
    'category' => 'National',
]));

$clientUser->notify(new ClientStoryNotification('story.killed', [
    'title' => '⚠️ [KILLED] Advisory: Withhold UNB Story Ref 01MERR003',
    'message' => 'Advisory issued to withhold story due to conflicting facts.',
    'public_id' => '01MERR003KILLED003',
    'urgency' => 'routine',
    'category' => 'Advisory',
]));

$initialCount = $clientUser->notifications()->count();
$initialUnread = $clientUser->unreadNotifications()->count();
echo "Total notifications seeded: {$initialCount}, Unread: {$initialUnread}\n";

if ($initialCount !== 3 || $initialUnread !== 3) {
    echo "FAIL: Notification seeding mismatch!\n";
    exit(1);
}

echo "=== [TEST 2]: Authenticate & Test API via HTTP ===\n";

// Generate personal access token for client user
$token = $clientUser->createToken('portal_test_token')->plainTextToken;

// 1. GET /api/v1/portal/notifications
$res = Http::withToken($token)->get(url('/api/v1/portal/notifications'));
echo "GET /api/v1/portal/notifications => HTTP " . $res->status() . "\n";
if ($res->status() !== 200) {
    echo "FAIL: Expected 200, got " . $res->status() . "\n";
    echo $res->body() . "\n";
    exit(1);
}

$json = $res->json();
$items = $json['data'] ?? [];
$unreadCount = $json['unread_count'] ?? -1;
echo "Fetched " . count($items) . " items. Unread count reported: {$unreadCount}\n";
if (count($items) !== 3 || $unreadCount !== 3) {
    echo "FAIL: Expected 3 items and 3 unread count\n";
    exit(1);
}

// 2. PATCH /api/v1/portal/notifications/{id}/read
$targetId = $items[0]['id'];
echo "Marking item {$targetId} ('{$items[0]['title']}') as read...\n";
$resRead = Http::withToken($token)->patch(url("/api/v1/portal/notifications/{$targetId}/read"));
echo "PATCH /api/v1/portal/notifications/{id}/read => HTTP " . $resRead->status() . "\n";
if ($resRead->status() !== 200 || $resRead->json('unread_count') !== 2) {
    echo "FAIL: Expected unread_count 2, got: " . json_encode($resRead->json()) . "\n";
    exit(1);
}

// 3. GET with filter=unread
$resUnread = Http::withToken($token)->get(url('/api/v1/portal/notifications?filter=unread'));
if ($resUnread->status() !== 200 || count($resUnread->json('data')) !== 2) {
    echo "FAIL: Expected 2 unread items in filtered query\n";
    exit(1);
}
echo "GET ?filter=unread returned exactly 2 unread items.\n";

// 4. POST /api/v1/portal/notifications/mark-all-read
$resMarkAll = Http::withToken($token)->post(url('/api/v1/portal/notifications/mark-all-read'));
echo "POST /api/v1/portal/notifications/mark-all-read => HTTP " . $resMarkAll->status() . "\n";
if ($resMarkAll->status() !== 200 || $resMarkAll->json('unread_count') !== 0) {
    echo "FAIL: Expected 0 unread count after mark-all-read\n";
    exit(1);
}

// 5. DELETE /api/v1/portal/notifications/{id}
echo "Deleting item {$targetId}...\n";
$resDel = Http::withToken($token)->delete(url("/api/v1/portal/notifications/{$targetId}"));
echo "DELETE /api/v1/portal/notifications/{id} => HTTP " . $resDel->status() . "\n";
if ($resDel->status() !== 200) {
    echo "FAIL: Delete notification failed\n";
    exit(1);
}

// 6. Verification after delete
$resFinal = Http::withToken($token)->get(url('/api/v1/portal/notifications'));
$remainingCount = count($resFinal->json('data') ?? []);
echo "Remaining notifications count: {$remainingCount}\n";
if ($remainingCount !== 2) {
    echo "FAIL: Expected 2 remaining notifications, got {$remainingCount}\n";
    exit(1);
}

echo "\n>>> ALL CLIENT NOTIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
