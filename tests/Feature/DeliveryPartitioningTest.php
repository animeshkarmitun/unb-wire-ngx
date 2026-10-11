<?php

namespace Tests\Feature;

use App\Console\Scheduling\SweepDeliveryPartitions;
use App\Models\Client;
use App\Support\DeliveryPartitions;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliveryPartitioningTest extends TestCase
{
    use RefreshDatabase;

    private int $clientId;

    private int $channelId;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only partition behavior');
        }

        $client = Client::factory()->create(['status' => 'active']);
        $this->clientId = $client->id;
        $this->channelId = DB::table('client_channels')->insertGetId([
            'client_id' => $client->id,
            'type' => 'webhook',
            'config' => json_encode(['url' => 'https://example.test/hook']),
            'status' => 'active',
            'failure_count' => 0,
        ]);
    }

    private function insertDelivery(string $key, DateTimeImmutable|string $createdAt): void
    {
        DB::table('deliveries')->insert([
            'deliverable_type' => 'story',
            'deliverable_id' => 1,
            'client_id' => $this->clientId,
            'channel_id' => $this->channelId,
            'status' => 'queued',
            'attempt_count' => 0,
            'idempotency_key' => $key,
            'payload_hash' => hash('sha256', $key),
            'created_at' => $createdAt instanceof DateTimeImmutable ? $createdAt->format('Y-m-d H:i:s') : $createdAt,
        ]);
    }

    public function test_deliveries_is_monthly_range_partitioned_with_current_next_and_default(): void
    {
        $this->assertSame('r', DB::selectOne(
            "SELECT partstrat FROM pg_partitioned_table WHERE partrelid = 'deliveries'::regclass"
        )->partstrat);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->assertNotNull(DB::selectOne('SELECT to_regclass(\'public.'.DeliveryPartitions::name($now).'\') AS r')->r);
        $this->assertNotNull(DB::selectOne('SELECT to_regclass(\'public.deliveries_default\') AS r')->r);
    }

    public function test_rows_route_by_created_at(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $current = DeliveryPartitions::name($now);

        $this->insertDelivery('route-current', $now);
        $this->assertTrue(DB::table($current)->where('idempotency_key', 'route-current')->exists());

        $old = $now->sub(new DateInterval('P3M'));
        $this->insertDelivery('route-default', $old);
        $this->assertTrue(DB::table('deliveries_default')->where('idempotency_key', 'route-default')->exists());

        $this->assertSame(2, DB::table('deliveries')->whereIn('idempotency_key', ['route-current', 'route-default'])->count());
    }

    public function test_same_month_duplicate_idempotency_key_is_blocked(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->insertDelivery('dup-key', $now);

        $this->expectException(QueryException::class);
        $this->insertDelivery('dup-key', $now);
    }

    public function test_partitions_ensure_creates_future_months_and_is_idempotent(): void
    {
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->add(new DateInterval('P3M'));
        $ym = $future->format('Ym');
        $this->assertFalse(DeliveryPartitions::partitionExists($ym));

        $this->artisan('partitions:ensure', ['--months' => 3])->assertSuccessful();
        $this->assertTrue(DeliveryPartitions::partitionExists($ym));

        // attached partitions inherit the parent's partitioned indexes
        $this->assertGreaterThan(
            0,
            DB::table('pg_indexes')->where('tablename', DeliveryPartitions::name($future))->count()
        );

        $this->artisan('partitions:ensure', ['--months' => 3])->assertSuccessful();
    }

    public function test_partitions_ensure_relocates_default_strays(): void
    {
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->add(new DateInterval('P3M'));

        $this->insertDelivery('stray-future', $future->format('Y-m-d H:i:s'));
        $this->assertTrue(DB::table('deliveries_default')->where('idempotency_key', 'stray-future')->exists());

        $this->artisan('partitions:ensure', ['--months' => 3])->assertSuccessful();

        $this->assertFalse(DB::table('deliveries_default')->where('idempotency_key', 'stray-future')->exists());
        $this->assertTrue(DB::table(DeliveryPartitions::name($future))->where('idempotency_key', 'stray-future')->exists());
        $this->assertSame(1, DB::table('deliveries')->where('idempotency_key', 'stray-future')->count());
    }

    public function test_sweep_detaches_old_partitions_to_archive_schema(): void
    {
        $old = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('first day of this month')
            ->modify('-14 months');
        DeliveryPartitions::ensureMonth($old);
        $oldName = DeliveryPartitions::name($old);

        $this->insertDelivery('sweep-me', $old->format('Y-m-d H:i:s'));
        $this->assertSame(1, DB::table('deliveries')->where('idempotency_key', 'sweep-me')->count());

        $swept = app(SweepDeliveryPartitions::class)->handle(12);

        $this->assertContains($oldName, $swept);
        $this->assertSame(0, DB::table('deliveries')->where('idempotency_key', 'sweep-me')->count());
        $this->assertNotNull(DB::selectOne('SELECT to_regclass(\'archive.'.$oldName.'\') AS r')->r);
    }
}
