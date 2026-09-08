<?php

namespace Tests\Feature\Repositories;

use App\Repositories\IndexOutboxRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexOutboxRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private IndexOutboxRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(IndexOutboxRepository::class);
    }

    private function insertOutbox(array $over = []): object
    {
        $data = array_merge([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => 'test-doc-1',
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ], $over);

        $id = DB::table('index_outbox')->insertGetId($data);

        return (object) array_merge($data, ['id' => $id]);
    }

    // ─── Read Methods ───────────────────────────────────────────

    public function test_get_pending_batch_returns_pending_only(): void
    {
        $this->insertOutbox(['status' => 'pending']);
        $this->insertOutbox(['status' => 'pending', 'document_id' => 'doc-2']);
        $this->insertOutbox(['status' => 'done', 'document_id' => 'doc-3']);

        $batch = $this->repo->getPendingBatch(50);

        $this->assertEquals(2, $batch->count());
    }

    public function test_get_pending_batch_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->insertOutbox(['document_id' => "doc-{$i}"]);
        }

        $batch = $this->repo->getPendingBatch(3);

        $this->assertEquals(3, $batch->count());
    }

    public function test_get_lag_seconds_returns_lag(): void
    {
        $this->insertOutbox(['created_at' => now()->subSeconds(60)]);

        $lag = $this->repo->getLagSeconds();

        $this->assertGreaterThanOrEqual(55, $lag);
    }

    public function test_get_lag_seconds_returns_zero_when_empty(): void
    {
        $lag = $this->repo->getLagSeconds();

        $this->assertEquals(0, $lag);
    }

    public function test_get_failed_count_returns_failed(): void
    {
        $this->insertOutbox(['status' => 'failed']);
        $this->insertOutbox(['status' => 'pending', 'document_id' => 'doc-2']);

        $count = $this->repo->getFailedCount();

        $this->assertEquals(1, $count);
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function test_mark_processing_increments_attempts(): void
    {
        $row = $this->insertOutbox(['attempts' => 0]);

        $this->repo->markProcessing($row->id, 0);

        $this->assertDatabaseHas('index_outbox', ['id' => $row->id, 'attempts' => 1]);
    }

    public function test_mark_done_sets_status_and_processed_at(): void
    {
        $row = $this->insertOutbox();

        $this->repo->markDone($row->id);

        $this->assertDatabaseHas('index_outbox', ['id' => $row->id, 'status' => 'done']);
        $this->assertNotNull(DB::table('index_outbox')->where('id', $row->id)->value('processed_at'));
    }

    public function test_mark_failed_sets_status(): void
    {
        $row = $this->insertOutbox();

        $this->repo->markFailed($row->id);

        $this->assertDatabaseHas('index_outbox', ['id' => $row->id, 'status' => 'failed']);
    }

    public function test_mark_pending_resets_status(): void
    {
        $row = $this->insertOutbox(['status' => 'failed']);

        $this->repo->markPending($row->id);

        $this->assertDatabaseHas('index_outbox', ['id' => $row->id, 'status' => 'pending']);
    }

    public function test_insert_creates_record(): void
    {
        $this->repo->insert([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => 'new-doc',
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->assertDatabaseHas('index_outbox', ['document_id' => 'new-doc', 'status' => 'pending']);
    }

    public function test_purge_completed_removes_old_done(): void
    {
        $old = $this->insertOutbox(['status' => 'done', 'processed_at' => now()->subDays(10)]);
        $recent = $this->insertOutbox(['status' => 'done', 'processed_at' => now()->subDays(1), 'document_id' => 'doc-recent']);
        $pending = $this->insertOutbox(['status' => 'pending', 'document_id' => 'doc-pending']);

        $this->repo->purgeCompleted(7);

        $this->assertDatabaseMissing('index_outbox', ['id' => $old->id]);
        $this->assertDatabaseHas('index_outbox', ['id' => $recent->id]);
        $this->assertDatabaseHas('index_outbox', ['id' => $pending->id]);
    }
}
