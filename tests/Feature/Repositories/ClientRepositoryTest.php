<?php

namespace Tests\Feature\Repositories;

use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Repositories\ClientRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ClientRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(ClientRepository::class);
    }

    private function createClient(array $over = []): Client
    {
        return Client::factory()->create(array_merge([
            'status' => 'active',
        ], $over));
    }

    // ─── Read Methods ───────────────────────────────────────────

    public function test_findOrFail_returns_client(): void
    {
        $client = $this->createClient();

        $result = $this->repo->findOrFail($client->id);

        $this->assertEquals($client->id, $result->id);
    }

    public function test_findWithRelations_eager_loads_specified(): void
    {
        $client = $this->createClient();

        $result = $this->repo->findWithRelations($client->id, ['clientChannels']);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('clientChannels'));
    }

    public function test_findWithFullRelations_eager_loads_all(): void
    {
        $client = $this->createClient();

        $result = $this->repo->findWithFullRelations($client->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('clientChannels'));
        $this->assertTrue($result->relationLoaded('clientPackages'));
        $this->assertTrue($result->relationLoaded('clientUsers'));
    }

    public function test_findByCode_returns_client(): void
    {
        $client = $this->createClient(['code' => 'DST']);

        $result = $this->repo->findByCode('DST');

        $this->assertNotNull($result);
        $this->assertEquals($client->id, $result->id);
    }

    public function test_activeCount_returns_active_clients(): void
    {
        $this->createClient(['status' => 'active']);
        $this->createClient(['status' => 'suspended']);

        $count = $this->repo->activeCount();

        $this->assertEquals(1, $count);
    }

    public function test_count_returns_total(): void
    {
        $this->createClient();
        $this->createClient();

        $count = $this->repo->count();

        $this->assertEquals(2, $count);
    }

    public function test_activeClientsThisWeek_returns_recent(): void
    {
        $this->createClient(['created_at' => now()]);
        $this->createClient(['created_at' => now()->subMonth()]);

        $count = $this->repo->activeClientsThisWeek(now()->startOfWeek());

        $this->assertEquals(1, $count);
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function test_create_creates_client(): void
    {
        $client = $this->repo->create([
            'name' => 'Test Client',
            'code' => 'TST',
            'type' => 'newspaper',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Test Client']);
    }

    public function test_update_updates_client(): void
    {
        $client = $this->createClient();

        $updated = $this->repo->update($client, ['name' => 'Updated']);

        $this->assertEquals('Updated', $updated->name);
    }

    // ─── API Keys ──────────────────────────────────────────────

    public function test_findActiveKeyByHash_returns_key(): void
    {
        $client = $this->createClient();
        $hash = hash('sha256', 'test_key');
        $key = ClientApiKey::factory()->create([
            'client_id' => $client->id,
            'key_hash' => $hash,
        ]);

        $result = $this->repo->findActiveKeyByHash($hash);

        $this->assertNotNull($result);
        $this->assertEquals($key->id, $result->id);
    }

    public function test_findActiveKeyByHash_returns_null_for_revoked(): void
    {
        $client = $this->createClient();
        $hash = hash('sha256', 'revoked_key');
        ClientApiKey::factory()->create([
            'client_id' => $client->id,
            'key_hash' => $hash,
            'revoked_at' => now(),
        ]);

        $result = $this->repo->findActiveKeyByHash($hash);

        $this->assertNull($result);
    }

    public function test_rotateKey_creates_new_and_expires_old(): void
    {
        $client = $this->createClient();
        $old = ClientApiKey::factory()->create(['client_id' => $client->id]);
        $newHash = hash('sha256', 'new_key');

        $new = $this->repo->rotateKey($old, $newHash);

        $this->assertNotEquals($old->id, $new->id);
        $this->assertEquals($newHash, $new->key_hash);
        $this->assertNotNull($old->fresh()->expires_at);
    }

    public function test_revokeKey_sets_revoked_at(): void
    {
        $client = $this->createClient();
        $key = ClientApiKey::factory()->create(['client_id' => $client->id]);

        $this->repo->revokeKey($key);

        $this->assertNotNull($key->fresh()->revoked_at);
    }

    // ─── Channels ──────────────────────────────────────────────

    public function test_activeChannelsFor_returns_active_only(): void
    {
        $client = $this->createClient();
        ClientChannel::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        ClientChannel::factory()->create(['client_id' => $client->id, 'status' => 'disabled']);

        $channels = $this->repo->activeChannelsFor($client->id);

        $this->assertEquals(1, $channels->count());
    }

    public function test_recordChannelSuccess_updates_last_success(): void
    {
        $client = $this->createClient();
        $channel = ClientChannel::factory()->create(['client_id' => $client->id, 'failure_count' => 3]);

        $this->repo->recordChannelSuccess($channel->id);

        $this->assertEquals(0, $channel->fresh()->failure_count);
        $this->assertNotNull($channel->fresh()->last_success_at);
    }

    public function test_recordChannelFailure_increments_and_auto_pauses(): void
    {
        $client = $this->createClient();
        $channel = ClientChannel::factory()->create(['client_id' => $client->id, 'failure_count' => 4, 'status' => 'active']);

        $this->repo->recordChannelFailure($channel->id, 5);

        $this->assertEquals(5, $channel->fresh()->failure_count);
        $this->assertEquals('paused', $channel->fresh()->status);
    }

    // ─── Subscriptions ─────────────────────────────────────────

    public function test_activeSubscriptionsFor_returns_active(): void
    {
        $client = $this->createClient();
        $pkg = Package::factory()->create();
        $pkg2 = Package::factory()->create();
        ClientPackage::factory()->create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active']);
        ClientPackage::factory()->create(['client_id' => $client->id, 'package_id' => $pkg2->id, 'status' => 'expired']);

        $subs = $this->repo->activeSubscriptionsFor($client->id);

        $this->assertEquals(1, $subs->count());
    }

    public function test_updateOrCreateSubscription_creates_or_updates(): void
    {
        $client = $this->createClient();
        $pkg = Package::factory()->create();

        $sub = $this->repo->updateOrCreateSubscription($client->id, $pkg->id);

        $this->assertDatabaseHas('client_packages', ['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active']);

        // Update existing
        $pkg2 = Package::factory()->create();
        $sub2 = $this->repo->updateOrCreateSubscription($client->id, $pkg2->id);

        $this->assertEquals($sub->id, $sub2->id); // Same record updated
        $this->assertEquals($pkg2->id, $sub2->fresh()->package_id);
    }
}
