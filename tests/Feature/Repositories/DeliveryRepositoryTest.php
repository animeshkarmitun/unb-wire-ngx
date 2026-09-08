<?php

namespace Tests\Feature\Repositories;

use App\Models\Delivery;
use App\Repositories\DeliveryRepository;
use Carbon\Carbon;
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

    public function test_paginateWithFilters_returns_paginated(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $result = $this->repo->paginateWithFilters('all', '');

        $this->assertEquals(2, $result->total());
    }

    public function test_paginateWithFilters_filters_by_status(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $result = $this->repo->paginateWithFilters('failed', '');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('failed', $result->items()[0]->status);
    }

    public function test_getDistributionCounts_returns_counts(): void
    {
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'delivered']);
        Delivery::factory()->create(['status' => 'failed']);

        $counts = $this->repo->getDistributionCounts();

        $this->assertEquals(3, $counts['total']);
        $this->assertEquals(2, $counts['delivered']);
        $this->assertEquals(1, $counts['failed']);
    }

    public function test_recentCount_counts_since_date(): void
    {
        Delivery::factory()->create(['created_at' => now()]);
        Delivery::factory()->create(['created_at' => now()->subDays(10)]);

        $count = $this->repo->recentCount(now()->subDays(5));

        $this->assertEquals(1, $count);
    }

    public function test_countBetween_counts_in_range(): void
    {
        Delivery::factory()->create(['created_at' => now()->subDays(3)]);
        Delivery::factory()->create(['created_at' => now()->subDays(10)]);
        Delivery::factory()->create(['created_at' => now()->subDays(20)]);

        $count = $this->repo->countBetween(now()->subDays(7), now());

        $this->assertEquals(1, $count);
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function test_createQueued_inserts_delivery(): void
    {
        // Create parent records for FK constraints
        $client = \App\Models\Client::factory()->create();
        $channel = \App\Models\ClientChannel::factory()->create(['client_id' => $client->id]);

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

    public function test_markSent_updates_status(): void
    {
        Delivery::factory()->create(['idempotency_key' => 'test-key', 'status' => 'queued']);

        $this->repo->markSent('test-key');

        $this->assertDatabaseHas('deliveries', ['idempotency_key' => 'test-key', 'status' => 'sent']);
    }

    public function test_markQueued_resets_status(): void
    {
        $delivery = Delivery::factory()->create(['status' => 'failed', 'attempt_count' => 3]);

        $this->repo->markQueued($delivery->id);

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'queued', 'attempt_count' => 0]);
    }
}
