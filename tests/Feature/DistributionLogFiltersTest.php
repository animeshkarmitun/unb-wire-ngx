<?php

namespace Tests\Feature;

use App\Livewire\Admin\DistributionLog;
use App\Mail\ChannelPaused;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class DistributionLogFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->clientA = Client::factory()->create(['name' => 'Alpha News', 'status' => 'active']);
        $this->clientB = Client::factory()->create(['name' => 'Beta Wire', 'status' => 'active']);
    }

    private function channelFor(Client $client, string $type = 'webhook'): int
    {
        return DB::table('client_channels')->insertGetId([
            'client_id' => $client->id,
            'type' => $type,
            'config' => json_encode(['url' => 'https://example.test/hook']),
            'status' => 'active',
            'failure_count' => 0,
        ]);
    }

    private function deliveryFor(Client $client, int $channelId, string $status, string $createdAt): void
    {
        static $n = 0;
        $n++;
        DB::table('deliveries')->insert([
            'deliverable_type' => 'story',
            'deliverable_id' => $n,
            'client_id' => $client->id,
            'channel_id' => $channelId,
            'status' => $status,
            'attempt_count' => 1,
            'idempotency_key' => "key-{$n}",
            'payload_hash' => str_pad((string) $n, 64, '0'),
            'created_at' => $createdAt,
        ]);
    }

    public function test_repository_filters_by_client_channel_type_and_date_range(): void
    {
        $chA = $this->channelFor($this->clientA, 'webhook');
        $chB = $this->channelFor($this->clientB, 'ftp');
        $this->deliveryFor($this->clientA, $chA, 'delivered', '2026-09-01 10:00:00');
        $this->deliveryFor($this->clientA, $chA, 'failed', '2026-09-10 10:00:00');
        $this->deliveryFor($this->clientB, $chB, 'delivered', '2026-09-10 11:00:00');

        $repo = app(DeliveryRepository::class);

        $this->assertCount(2, $repo->paginateWithFilters('all', '', $this->clientA->id)->items());
        $this->assertCount(1, $repo->paginateWithFilters('all', '', null, 'ftp')->items());
        $this->assertCount(1, $repo->paginateWithFilters('failed', '')->items());
        $this->assertCount(2, $repo->paginateWithFilters('all', '', null, null, '2026-09-10 00:00:00')->items());
        $this->assertCount(1, $repo->paginateWithFilters('all', '', $this->clientA->id, 'webhook', '2026-09-05 00:00:00', '2026-09-30 23:59:59')->items());
    }

    public function test_livewire_filters_apply_to_log(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $chA = $this->channelFor($this->clientA, 'webhook');
        $chB = $this->channelFor($this->clientB, 'ftp');
        $this->deliveryFor($this->clientA, $chA, 'delivered', '2026-09-01 10:00:00');
        $this->deliveryFor($this->clientB, $chB, 'delivered', '2026-09-10 11:00:00');

        $onlyB = fn ($d) => collect($d->items())->every(fn ($row) => $row->client_id === $this->clientB->id) && count($d->items()) === 1;

        $component = Livewire::actingAs($admin)
            ->test(DistributionLog::class)
            ->set('clientId', (string) $this->clientB->id)
            ->assertViewHas('deliveries', $onlyB);

        $component->set('clientId', '')
            ->set('channelType', 'ftp')
            ->assertViewHas('deliveries', $onlyB);

        $component->set('channelType', 'all')
            ->set('dateFrom', '2026-09-05')
            ->set('dateTo', '2026-09-30')
            ->assertViewHas('deliveries', $onlyB);

        $component->set('dateFrom', '2026-08-01')
            ->set('dateTo', '2026-08-31')
            ->assertViewHas('deliveries', fn ($d) => count($d->items()) === 0);
    }

    public function test_auto_pause_notifies_client_contact(): void
    {
        Mail::fake();
        $this->clientA->update(['billing_email' => 'desk@alpha.test']);
        $channelId = $this->channelFor($this->clientA, 'webhook');

        $repo = app(ClientRepository::class);
        for ($i = 0; $i < 5; $i++) {
            $repo->recordChannelFailure($channelId, 5);
        }

        $this->assertSame('paused', DB::table('client_channels')->where('id', $channelId)->value('status'));
        Mail::assertQueued(ChannelPaused::class, fn ($m) => $m->hasTo('desk@alpha.test') && $m->clientName === 'Alpha News');
    }

    public function test_auto_pause_notification_not_repeated_for_paused_channel(): void
    {
        Mail::fake();
        $this->clientA->update(['billing_email' => 'desk@alpha.test']);
        $channelId = $this->channelFor($this->clientA, 'webhook');

        $repo = app(ClientRepository::class);
        for ($i = 0; $i < 8; $i++) {
            $repo->recordChannelFailure($channelId, 5);
        }

        Mail::assertQueued(ChannelPaused::class, 1);
    }
}
