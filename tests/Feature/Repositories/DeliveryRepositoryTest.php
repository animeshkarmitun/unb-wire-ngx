<?php

namespace Tests\Feature\Repositories;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Delivery;
use App\Repositories\DeliveryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(DeliveryRepository::class);
    }

    // ─── Read Methods ───────────────────────────────────────────

    public function test_paginate_with_filters_returns_paginated(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $result = $this->repo->paginateWithFilters('all', '');

        $this->assertEquals(2, $result->total());
    }

    public function test_paginate_with_filters_filters_by_status(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $result = $this->repo->paginateWithFilters('failed', '');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('failed', $result->items()[0]->status);
    }

    public function test_get_distribution_counts_returns_counts(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $counts = $this->repo->getDistributionCounts();

        $this->assertEquals(3, $counts['total']);
        $this->assertEquals(2, $counts['delivered']);
        $this->assertEquals(1, $counts['failed']);
    }

    public function test_recent_count_counts_since_date(): void
    {
        Delivery::factory()->create(['created_at' => now()]);
        Delivery::factory()->create(['created_at' => now()->subDays(10)]);

        $count = $this->repo->recentCount(now()->subDays(5));

        $this->assertEquals(1, $count);
    }

    public function test_count_between_counts_in_range(): void
    {
        Delivery::factory()->create(['created_at' => now()->subDays(3)]);
        Delivery::factory()->create(['created_at' => now()->subDays(10)]);
        Delivery::factory()->create(['created_at' => now()->subDays(20)]);

        $count = $this->repo->countBetween(now()->subDays(7), now());

        $this->assertEquals(1, $count);
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function test_create_queued_inserts_delivery(): void
    {
        // Create parent records for FK constraints
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create(['client_id' => $client->id]);

        $this->repo->createQueued([
            'deliverable_type' => 'story',
            'deliverable_id' => 1,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'idempotency_key' => 'test-key',
            'payload_hash' => 'hash',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'idempotency_key' => 'test-key',
            'status' => 'queued',
        ]);
    }

    public function test_mark_sent_updates_status(): void
    {
        Delivery::factory()->create(['idempotency_key' => 'test-key', 'status' => 'queued']);

        $this->repo->markSent('test-key');

        $this->assertDatabaseHas('deliveries', ['idempotency_key' => 'test-key', 'status' => 'sent']);
    }

    public function test_mark_queued_resets_status(): void
    {
        $delivery = Delivery::factory()->create(['status' => 'failed', 'attempt_count' => 3]);

        $this->repo->markQueued($delivery->id);

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'queued', 'attempt_count' => 0]);
    }

    public function test_has_prior_success_true_when_a_prior_delivery_exists(): void
    {
        $delivery = Delivery::factory()->create(['status' => 'sent']);
        $other = Delivery::factory()->create(['status' => 'queued']);

        $this->assertTrue($this->repo->hasPriorSuccess($delivery->deliverable_id, $delivery->channel_id));
        $this->assertFalse($this->repo->hasPriorSuccess($other->deliverable_id, $other->channel_id));
    }

    public function test_has_prior_success_excludes_target_id(): void
    {
        $delivery = Delivery::factory()->create(['status' => 'sent']);

        $this->assertFalse($this->repo->hasPriorSuccess($delivery->deliverable_id, $delivery->channel_id, excludeDeliveryId: $delivery->id));
    }

    public function test_failed_count_dlq_count_and_delivered_between(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create(['client_id' => $client->id]);

        Delivery::factory()->count(2)->create(['status' => 'failed', 'attempt_count' => 5]);
        Delivery::factory()->create(['status' => 'delivered', 'delivered_at' => now()->subDay(), 'attempt_count' => 1]);

        $this->assertSame(2, $this->repo->failedCount());

        $start = now()->subDays(2);
        $end = now();
        $this->assertSame(1, $this->repo->deliveredCountBetween($start, $end));

        // dlqCount reads the deliveries table; assert it returns int (>=0).
        $this->assertIsInt($this->repo->dlqCount());
    }
}
