<?php

namespace Tests\Feature\Api;

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Notifications\ClientStoryNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createUserAndToken(string $clientName = 'The Daily Star'): array
    {
        $client = Client::factory()->create(['name' => $clientName, 'status' => 'active']);
        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'name' => 'Sub Editor',
            'email' => Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $loginResp = $this->postJson('/api/v1/portal/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $token = $loginResp->json('token');

        return [$user, $client, $token];
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/portal/notifications')->assertStatus(401);
        $this->patchJson('/api/v1/portal/notifications/fake-id/read')->assertStatus(401);
        $this->postJson('/api/v1/portal/notifications/mark-all-read')->assertStatus(401);
        $this->deleteJson('/api/v1/portal/notifications/fake-id')->assertStatus(401);
        $this->deleteJson('/api/v1/portal/notifications')->assertStatus(401);
    }

    public function test_get_notifications_returns_client_user_notifications_and_unread_count(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        // Send two notifications to this user
        $user->notify(new ClientStoryNotification('story.breaking', [
            'title' => '🚨 [BREAKING] Major Event Occurred',
            'message' => 'Details about the major event',
            'public_id' => '01MTESTPUBLICID1',
            'urgency' => 'breaking',
        ]));

        $user->notify(new ClientStoryNotification('story.published', [
            'title' => 'New Regular Story',
            'message' => 'Details about regular story',
            'public_id' => '01MTESTPUBLICID2',
            'urgency' => 'regular',
        ]));

        $resp = $this->getJson('/api/v1/portal/notifications', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'data' => [
                '*' => ['id', 'event', 'title', 'message', 'public_id', 'urgency', 'read_at', 'created_at'],
            ],
            'unread_count',
            'meta' => ['current_page', 'last_page', 'total', 'per_page'],
        ]);

        $this->assertEquals(2, $resp->json('unread_count'));
        $this->assertCount(2, $resp->json('data'));
        $this->assertStringContainsString('BREAKING', $resp->json('data.0.title'));
    }

    public function test_get_notifications_filters_unread(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $user->notify(new ClientStoryNotification('story.breaking', [
            'title' => 'Unread notification',
            'message' => 'Summary',
        ]));

        $user->notify(new ClientStoryNotification('story.published', [
            'title' => 'Read notification',
            'message' => 'Summary',
        ]));

        // Mark second one as read
        $notif = $user->notifications()->first();
        $notif->markAsRead();

        $resp = $this->getJson('/api/v1/portal/notifications?filter=unread', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals(1, $resp->json('unread_count'));
        $this->assertCount(1, $resp->json('data'));
        $this->assertNull($resp->json('data.0.read_at'));
    }

    public function test_mark_as_read_updates_read_at_and_unread_count(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $user->notify(new ClientStoryNotification('story.breaking', [
            'title' => 'Alert',
            'message' => 'Test message',
        ]));

        $notif = $user->notifications()->first();
        $this->assertNull($notif->read_at);

        $resp = $this->patchJson('/api/v1/portal/notifications/'.$notif->id.'/read', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals(0, $resp->json('unread_count'));
        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $user->notify(new ClientStoryNotification('story.breaking', ['title' => 'One']));
        $user->notify(new ClientStoryNotification('story.published', ['title' => 'Two']));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        $resp = $this->postJson('/api/v1/portal/notifications/mark-all-read', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals(0, $resp->json('unread_count'));
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_delete_notification_removes_item(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $user->notify(new ClientStoryNotification('story.breaking', ['title' => 'To be deleted']));
        $notif = $user->notifications()->first();

        $resp = $this->deleteJson('/api/v1/portal/notifications/'.$notif->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals(0, $user->notifications()->count());
    }

    public function test_delete_all_removes_notifications(): void
    {
        [$user, $client, $token] = $this->createUserAndToken();

        $user->notify(new ClientStoryNotification('story.breaking', ['title' => 'One']));
        $user->notify(new ClientStoryNotification('story.published', ['title' => 'Two']));

        $resp = $this->deleteJson('/api/v1/portal/notifications', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertEquals(0, $user->notifications()->count());
    }

    public function test_client_user_cannot_access_or_modify_other_user_notifications(): void
    {
        [$userA, $clientA, $tokenA] = $this->createUserAndToken('Client A');
        [$userB, $clientB, $tokenB] = $this->createUserAndToken('Client B');

        $userB->notify(new ClientStoryNotification('story.breaking', ['title' => 'Secret for B']));
        $notifB = $userB->notifications()->first();

        // User A attempts to mark user B's notification as read
        $this->patchJson('/api/v1/portal/notifications/'.$notifB->id.'/read', [], [
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertStatus(404);

        // User A attempts to delete user B's notification
        $this->deleteJson('/api/v1/portal/notifications/'.$notifB->id, [], [
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertStatus(404);

        // Notification for B should remain intact
        $this->assertDatabaseHas('notifications', [
            'id' => $notifB->id,
            'notifiable_id' => $userB->id,
        ]);
    }

    public function test_fanout_creates_notifications_for_entitled_client_users(): void
    {
        $cat = Category::factory()->create();
        $author = User::factory()->create();

        $client = Client::factory()->create(['name' => 'Prothom Alo', 'status' => 'active']);
        $clientUser = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['en']]]);
        ClientPackage::create([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $story = Story::factory()->create([
            'category_id' => $cat->id,
            'owner_id' => $author->id,
            'created_by' => $author->id,
            'status' => 'published',
            'language' => 'en',
            'headline' => 'Cyclone Alert in Bay of Bengal',
            'brief' => 'Meteorological department issues cautionary signal.',
            'is_breaking' => true,
            'priority' => 'urgent',
            'published_at' => now(),
        ]);

        (new FanoutStory($story->id))->handle();

        $this->assertEquals(1, $clientUser->notifications()->count());
        $notif = $clientUser->notifications()->first();
        $this->assertEquals('story.breaking', $notif->data['event']);
        $this->assertStringContainsString('Cyclone Alert', $notif->data['title']);
        $this->assertEquals((string) $story->public_id, $notif->data['public_id']);
    }
}
