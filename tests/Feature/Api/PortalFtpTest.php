<?php

namespace Tests\Feature\Api;

use App\Livewire\Admin\ClientsManager;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientUser;
use App\Models\Delivery;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\ClientStoryNotification;
use App\Notifications\StoryNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * COS-FTP-MGMT: the client's admin portal user manages the client's single
 * FTP channel (add/edit/pause/resume/remove/test) via the portal API.
 */
class PortalFtpTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $adminUser;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->client = Client::factory()->create(['name' => 'The Daily Star', 'status' => 'active']);
        $this->adminUser = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'can_manage_delivery' => true,
            'status' => 'active',
        ]);
        $this->token = $this->adminUser->createToken('portal')->plainTextToken;
        $this->auth = ['Authorization' => 'Bearer '.$this->token];
    }

    private array $auth;

    private function putChannel(array $over = [], array $headers = []): mixed
    {
        return $this->putJson('/api/v1/portal/ftp', array_merge([
            'host' => 'ftp.thedailystar.net',
            'port' => 21,
            'username' => 'daily-star',
            'password' => 'secret-pass',
            'protocol' => 'ftp',
        ], $over), array_merge($this->auth, $headers));
    }

    public function test_admin_user_can_add_channel_immediately(): void
    {
        $resp = $this->putChannel()->assertOk();

        $channel = ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first();
        $this->assertNotNull($channel);
        $config = $channel->config;
        $this->assertEquals('ftp.thedailystar.net', $config['host']);
        $this->assertEquals(21, $config['port']);
        $this->assertNotEquals('secret-pass', $config['password'], 'password stored encrypted');
        $this->assertEquals('secret-pass', Crypt::decryptString($config['password']));
        $this->assertEquals('active', $channel->status);
        $this->assertTrue($resp->json('channel.has_password'));
    }

    public function test_update_without_password_keeps_existing(): void
    {
        $this->putChannel()->assertOk();

        $this->putChannel([
            'password' => null,
            'host' => 'ftp2.thedailystar.net',
        ])->assertOk();

        $config = ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first()->config;
        $this->assertEquals('ftp2.thedailystar.net', $config['host']);
        $this->assertNotEquals('secret-pass', $config['password']);
        $this->assertEquals('secret-pass', Crypt::decryptString($config['password']));
    }

    public function test_password_is_never_returned(): void
    {
        $this->putChannel()->assertOk();

        $resp = $this->getJson('/api/v1/portal/ftp', $this->auth)->assertOk();
        $channel = $resp->json('channel');
        $this->assertArrayNotHasKey('password', $channel);
        $this->assertArrayNotHasKey('config', $channel);
        $this->assertTrue($channel['has_password']);
    }

    public function test_validation_rejects_bad_protocol_and_missing_host(): void
    {
        $this->putChannel(['protocol' => 'gopher'])->assertStatus(422);
        $this->putChannel(['host' => null])->assertStatus(422);
        $this->putChannel(['port' => 99999])->assertStatus(422);
    }

    public function test_non_manager_user_is_403(): void
    {
        $plain = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'can_manage_delivery' => false,
            'status' => 'active',
        ]);
        $token = $plain->createToken('portal')->plainTextToken;

        $this->getJson('/api/v1/portal/ftp', ['Authorization' => 'Bearer '.$token])->assertStatus(403);
        $this->putChannel([], ['Authorization' => 'Bearer '.$token])->assertStatus(403);
    }

    public function test_unauthenticated_is_401(): void
    {
        $this->getJson('/api/v1/portal/ftp')->assertStatus(401);
    }

    public function test_pause_and_resume_update_status_and_notify_client_users(): void
    {
        Notification::fake();
        $this->putChannel()->assertOk();

        $resp = $this->postJson('/api/v1/portal/ftp/pause', [], $this->auth)->assertOk();
        $this->assertEquals('paused', ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first()->status);
        $this->assertStringContainsString('paused', $resp->json('message'));

        $resp = $this->postJson('/api/v1/portal/ftp/resume', [], $this->auth)->assertOk();
        $this->assertEquals('active', ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first()->status);

        Notification::assertSentTo(
            [$this->adminUser],
            ClientStoryNotification::class,
            fn ($n) => in_array($n->event, ['story.ftp_paused', 'story.ftp_resumed'], true)
        );
    }

    public function test_remove_disables_channel_with_history(): void
    {
        $this->putChannel()->assertOk();
        $channel = ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first();

        // deliveries.channel_id FK is RESTRICT — used channels cannot be deleted
        Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => Story::factory()->create()->id,
            'client_id' => $this->client->id,
            'channel_id' => $channel->id,
            'status' => 'sent',
            'idempotency_key' => Str::random(16),
            'payload_hash' => 'h',
            'created_at' => now(),
        ]);

        $resp = $this->deleteJson('/api/v1/portal/ftp', [], $this->auth)->assertOk();
        $channel->refresh();
        $this->assertEquals('disabled', $channel->status);
        $this->assertEmpty($channel->config['host'] ?? null, 'credentials wiped on removal');
    }

    public function test_remove_deletes_unused_channel(): void
    {
        $this->putChannel()->assertOk();

        $this->deleteJson('/api/v1/portal/ftp', [], $this->auth)->assertOk();
        $this->assertDatabaseMissing('client_channels', ['client_id' => $this->client->id, 'type' => 'ftp']);
    }

    public function test_changes_are_audited_with_client_user_actor(): void
    {
        $this->putChannel()->assertOk();
        $this->postJson('/api/v1/portal/ftp/pause', [], $this->auth)->assertOk();

        $row = DB::table('audit_logs')->where('action', 'client.ftp.added')->first();
        $this->assertNotNull($row);
        $this->assertEquals('client_user', $row->actor_type);
        $this->assertEquals($this->adminUser->id, $row->actor_id);
        $this->assertNotNull($row->ip);
        $this->assertNotNull($row->correlation_id);
    }

    public function test_changes_notify_staff_with_clients_edit(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role_id' => Role::where('name', 'Business Team')->first()->id]);
        $user = User::factory()->create(['role_id' => Role::where('name', 'Admin')->first()->id, 'is_superadmin' => true]);
        $user->update(['is_superadmin' => true]);

        $this->putChannel()->assertOk();

        Notification::assertSentTo(
            [$staff, $user],
            StoryNotification::class,
            fn ($n) => $n->event === 'client.ftp_added'
        );
    }

    public function test_exactly_one_ftp_channel_per_client(): void
    {
        $this->putChannel()->assertOk();
        $this->putChannel(['host' => 'ftp3.thedailystar.net'])->assertOk();

        $this->assertEquals(1, ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->count());
    }

    public function test_admin_clients_page_toggles_delivery_manager_flag(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'Admin')->first()->id]);
        $this->actingAs($admin);

        // Non-manager portal user gets promoted...
        $other = ClientUser::factory()->create([
            'client_id' => $this->client->id,
            'can_manage_delivery' => false,
            'status' => 'active',
        ]);
        Livewire::test(ClientsManager::class)
            ->call('toggleDeliveryManager', $other->id);

        $this->assertTrue($other->fresh()->can_manage_delivery);

        // ...and revoked again.
        Livewire::test(ClientsManager::class)
            ->call('toggleDeliveryManager', $other->id);

        $this->assertFalse($other->fresh()->can_manage_delivery);
    }
}
