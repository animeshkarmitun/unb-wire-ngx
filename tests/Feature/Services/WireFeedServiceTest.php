<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use App\Services\WireFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WireFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    private WireFeedService $svc;

    private User $user;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(WireFeedService::class);
        $this->user = User::factory()->create();
        $this->cat = Category::factory()->create(['sort_order' => 1]);
    }

    private function createPublishedStory(array $over = []): Story
    {
        return Story::factory()->create(array_merge([
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->cat->id,
            'owner_id' => $this->user->id,
            'created_by' => $this->user->id,
            'published_at' => now(),
        ], $over));
    }

    public function test_categories_returns_sorted_with_counts(): void
    {
        $this->createPublishedStory();

        $result = $this->svc->categories('en');

        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result->first()->stories_count);
    }

    public function test_categories_returns_zero_count_for_empty(): void
    {
        $result = $this->svc->categories('en');

        $this->assertNotEmpty($result);
        $this->assertEquals(0, $result->first()->stories_count);
    }

    public function test_hero_story_returns_null_when_empty(): void
    {
        $result = $this->svc->heroStory('en');

        $this->assertNull($result);
    }

    public function test_sections_returns_empty_for_no_stories(): void
    {
        $result = $this->svc->sections('en', '', 'all', null);

        $this->assertTrue($result->isEmpty());
    }

    public function test_sections_returns_single_category_section(): void
    {
        $this->createPublishedStory();

        $result = $this->svc->sections('en', '', $this->cat->slug, null);

        $this->assertCount(1, $result);
        $this->assertEquals($this->cat->id, $result[0]['category']->id);
    }

    public function test_sections_returns_multi_category_sections(): void
    {
        $this->createPublishedStory();

        $result = $this->svc->sections('en', '', 'all', null);

        $this->assertNotEmpty($result);
    }

    public function test_sections_returns_fallback_when_no_categorized(): void
    {
        $cat = Category::factory()->create(['sort_order' => 99]);
        $this->createPublishedStory(['category_id' => $cat->id]);

        $result = $this->svc->sections('en', '', 'all', null);

        $this->assertNotEmpty($result);
    }

    public function test_latest_rail_returns_limited_stories(): void
    {
        $this->createPublishedStory();

        $result = $this->svc->latestRail('en', 5);

        $this->assertLessThanOrEqual(5, $result->count());
    }

    public function test_ticker_headlines_returns_fallback_when_empty(): void
    {
        $result = $this->svc->tickerHeadlines('en', 6);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('UNB Wire', $result->first());
    }

    public function test_ticker_headlines_returns_stories(): void
    {
        $this->createPublishedStory(['headline' => 'Test Headline']);

        $result = $this->svc->tickerHeadlines('en', 6);

        $this->assertNotEmpty($result);
    }

    public function test_bangla_ticker_returns_bangla_fallback(): void
    {
        $result = $this->svc->tickerHeadlines('bn', 6);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('ইউএনবি', $result->first());
    }
}
