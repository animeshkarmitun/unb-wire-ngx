<?php

namespace Tests\Feature;

use App\Console\Scheduling\LiftEmbargoedStories;
use App\Console\Scheduling\SweepArchivedStories;
use App\Models\Category;
use App\Models\Client;
use App\Models\Story;
use App\Models\User;
use App\Repositories\StoryRepository;
use App\Services\Search\TenantTokenIssuer;
use App\Services\StoryService;
use App\Support\IndexOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StaffSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meilisearch.host' => 'http://127.0.0.1:7700',
            'services.meilisearch.key' => 'test-master-key',
        ]);
    }

    private function draftWithHeadline(string $headline): Story
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();

        return app(StoryService::class)->createDraft([
            'language' => 'en',
            'headline' => $headline,
            'brief' => 'brief text',
            'body_html' => '<p>needle body paragraph</p>',
            'category_id' => $cat->id,
        ], $user)->refresh();
    }

    public function test_staff_search_resolves_ids_from_meilisearch_including_non_published(): void
    {
        Queue::fake();
        $match = $this->draftWithHeadline('Ordinary headline');
        $other = $this->draftWithHeadline('Unrelated headline');

        Http::fake([
            '*/indexes/staff/settings' => Http::response(['taskUid' => 1]),
            '*/indexes/staff/search' => Http::response(['hits' => [['id' => $match->public_id]]]),
        ]);

        $result = app(StoryRepository::class)->filteredList('en', 'all', 'all', 'needle body', 15);

        $this->assertTrue($result->contains($match->id));
        $this->assertFalse($result->contains($other->id));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/indexes/staff/search');
        });
    }

    public function test_staff_search_sends_language_and_status_filters(): void
    {
        Queue::fake();
        $cat = Category::factory()->create(['slug' => 'politics']);
        $this->draftWithHeadline('Ordinary headline');

        Http::fake([
            '*/indexes/staff/settings' => Http::response(['taskUid' => 1]),
            '*/indexes/staff/search' => Http::response(['hits' => []]),
        ]);

        app(StoryRepository::class)->filteredList('en', 'draft', 'politics', 'needle', 15);

        Http::assertSent(function ($request) use ($cat) {
            if (! str_contains($request->url(), '/indexes/staff/search')) {
                return false;
            }
            $filter = $request->data()['filter'];

            return in_array('language = "en"', $filter)
                && in_array('status = "draft"', $filter)
                && in_array("category_id = {$cat->id}", $filter);
        });
    }

    public function test_staff_search_falls_back_to_sql_when_engine_unavailable(): void
    {
        Queue::fake();
        $match = $this->draftWithHeadline('Cyclone needle alert issued');

        Http::fake([
            '*/indexes/staff/settings' => Http::response(['taskUid' => 1]),
            '*/indexes/staff/search' => Http::response(['message' => 'boom'], 500),
        ]);

        $result = app(StoryRepository::class)->filteredList('en', 'all', 'all', 'needle alert', 15);

        $this->assertTrue($result->contains($match->id));
    }

    public function test_tenant_tokens_never_grant_staff_index_access(): void
    {
        Queue::fake();
        config(['services.meilisearch.key' => null]);
        $client = Client::factory()->create(['status' => 'active']);

        $issued = app(TenantTokenIssuer::class)->issueFor($client);

        // fallback JWT payload carries the searchRules — staff must never appear
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], explode('.', $issued['token'])[1])), true);
        $this->assertArrayNotHasKey('staff', $payload['searchRules'] ?? []);
        $this->assertArrayHasKey('main', $payload['searchRules']);
    }

    public function test_staff_outbox_enqueue_dedupes_pending_rows_across_saves(): void
    {
        Queue::fake();
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);

        $story = $svc->createDraft([
            'language' => 'en',
            'headline' => 'Dedupe headline',
            'brief' => 'b',
            'body_html' => '<p>body</p>',
            'category_id' => $cat->id,
        ], $user);

        $svc->updateDraft($story, ['headline' => 'Dedupe headline v2'], 1, $user);
        $svc->transition($story->refresh(), 'in_review', $user);

        $pending = DB::table('index_outbox')->where('index_name', 'staff')->where('status', 'pending')->pluck('document_id');
        $this->assertSame([$story->public_id], $pending->all());

        // helper skips an already-pending row for the same (index, document, op)
        IndexOutbox::enqueue('staff', $story->public_id);
        $this->assertSame(1, DB::table('index_outbox')->where('index_name', 'staff')->where('status', 'pending')->count());
    }

    public function test_embargo_lift_and_archive_sweep_enqueue_staff_rows(): void
    {
        Queue::fake();
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $lifted = Story::factory()->create([
            'status' => 'approved',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'embargo_until' => now()->subMinute(),
        ]);
        $old = Story::factory()->create([
            'status' => 'published',
            'published_at' => now()->subMonths(13),
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        app(LiftEmbargoedStories::class)->handle();
        app(SweepArchivedStories::class)->handle();

        $this->assertDatabaseHas('index_outbox', ['index_name' => 'staff', 'document_id' => $lifted->public_id]);
        $this->assertDatabaseHas('index_outbox', ['index_name' => 'staff', 'document_id' => $old->public_id]);
    }
}
