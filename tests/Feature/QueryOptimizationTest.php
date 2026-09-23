<?php

namespace Tests\Feature;

use App\Jobs\ProcessIndexOutbox;
use App\Models\Story;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;
    // ─── Indexes ─────────────────────────────────────────────────

    public function test_perf_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasIndex('deliveries', 'deliveries_deliverable_index'));
        $this->assertTrue(Schema::hasIndex('client_channels', 'client_channels_client_index'));
        $this->assertTrue(Schema::hasIndex('client_api_keys', 'client_api_keys_client_index'));
        $this->assertTrue(Schema::hasIndex('client_packages', 'client_packages_package_index'));
        $this->assertTrue(Schema::hasIndex('story_media', 'story_media_asset_index'));
        $this->assertTrue(Schema::hasIndex('story_tag', 'story_tag_tag_index'));
        $this->assertTrue(Schema::hasIndex('media_tag', 'media_tag_tag_index'));
    }

    // ─── statusCounts: 5 COUNTs → 1 grouped query ────────────────

    public function test_status_counts_correct_in_single_query(): void
    {
        Story::factory()->create(['language' => 'en', 'status' => 'published']);
        Story::factory()->create(['language' => 'en', 'status' => 'published']);
        Story::factory()->create(['language' => 'en', 'status' => 'draft']);
        Story::factory()->create(['language' => 'en', 'status' => 'in_review']);
        Story::factory()->create(['language' => 'bn', 'status' => 'published']);

        DB::enableQueryLog();
        $counts = app(\App\Repositories\StoryRepository::class)->statusCounts('en');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'statusCounts must run exactly one query, ran '.$queries);
        $this->assertSame(4, $counts['all']);
        $this->assertSame(2, $counts['published']);
        $this->assertSame(1, $counts['draft']);
        $this->assertSame(1, $counts['in_review']);
        $this->assertSame(0, $counts['changes_requested']);
    }

    // ─── Date counts: day-range semantics ────────────────────────

    public function test_date_counts_respect_day_boundaries(): void
    {
        $today = Carbon::create(2026, 9, 22, 12, 0, 0);
        Carbon::setTestNow($today);

        Story::factory()->create(['language' => 'en', 'status' => 'published', 'published_at' => $today->copy()->subHours(2), 'is_breaking' => true]);
        Story::factory()->create(['language' => 'en', 'status' => 'published', 'published_at' => $today->copy()->subDay()->addHour(3)]);
        Story::factory()->create(['language' => 'en', 'status' => 'published', 'published_at' => $today->copy()->subDays(3), 'is_breaking' => true]);

        $repo = app(\App\Repositories\StoryRepository::class);

        $this->assertSame(1, $repo->countByDateAndStatus($today->copy(), 'published'));
        $this->assertSame(1, $repo->countByDateAndStatus($today->copy()->subDay(), 'published'));
        $this->assertSame(1, $repo->countByDateAndStatus($today->copy()->subDays(3), 'published'));
        $this->assertSame(1, $repo->countExclusive($today->copy()));
        $this->assertSame(0, $repo->countExclusive($today->copy()->subDay()));

        Carbon::setTestNow();
    }

    // ─── ProcessIndexOutbox: batch prefetch, queries bounded ─────

    public function test_index_outbox_batch_queries_do_not_scale_with_rows(): void
    {
        Http::fake();
        config([
            'services.meilisearch.host' => 'http://meili.test',
            'services.meilisearch.key' => 'test-key',
        ]);

        $n = 8;
        foreach (range(1, $n) as $i) {
            $story = Story::factory()->create(['language' => 'en', 'status' => 'published']);
            DB::table('index_outbox')->insert([
                'index_name' => 'main',
                'op' => 'upsert',
                'document_id' => $story->public_id,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => now(),
            ]);
        }

        DB::enableQueryLog();
        (new ProcessIndexOutbox)->handle();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($n, DB::table('index_outbox')->where('status', 'done')->count());
        $this->assertLessThanOrEqual(
            $n + 6,
            $queries,
            "batch of {$n} rows must not run per-row story fetches; ran {$queries} queries"
        );
    }

    // ─── Dashboard KPI cache ─────────────────────────────────────

    public function test_dashboard_kpis_cached_between_calls(): void
    {
        Story::factory()->create(['language' => 'en', 'status' => 'published', 'published_at' => now()]);
        Story::factory()->create(['language' => 'en', 'status' => 'draft']);

        $service = app(DashboardService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $service->getData();
        $firstQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $service->getData();
        $secondQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first['publishedToday'], $second['publishedToday']);
        $this->assertSame($first['activeClients'], $second['activeClients']);
        $this->assertSame($first['successRate'], $second['successRate']);
        $this->assertSame($first['exclusiveToday'], $second['exclusiveToday']);
        $this->assertLessThan(
            $firstQueries,
            $secondQueries,
            "second getData must hit the KPI cache ({$secondQueries} vs {$firstQueries} queries)"
        );
    }
}
