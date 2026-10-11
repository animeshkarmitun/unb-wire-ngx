<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use App\Services\StoryService;
use App\Support\FeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function publishedStory(string $headline): \App\Models\Story
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);
        $story = $svc->createDraft([
            'language' => 'en',
            'headline' => $headline,
            'brief' => 'b',
            'body_html' => '<p>body</p>',
            'category_id' => $cat->id,
        ], $user);
        $svc->transition($story, 'in_review', $user);
        $story = $story->refresh();
        $svc->transition($story, 'approved', $user);
        $story = $story->refresh();
        $svc->transition($story, 'published', $user);

        return $story->refresh();
    }

    public function test_publish_is_visible_on_next_feed_request_without_waiting_for_ttl(): void
    {
        Queue::fake();
        $headline = 'Epoch invalidate publish headline';

        // Warm the guest feed cache — story does not exist yet
        $this->getJson('/api/v1/portal/feed')
            ->assertOk()
            ->assertJsonMissing(['headline' => $headline]);

        $this->publishedStory($headline);

        // Same URL, within the old entry's TTL — epoch bump must bypass the stale entry
        $this->getJson('/api/v1/portal/feed')
            ->assertOk()
            ->assertJsonFragment(['headline' => $headline]);
    }

    public function test_kill_is_visible_on_next_feed_request_without_waiting_for_ttl(): void
    {
        Queue::fake();
        $headline = 'Epoch invalidate kill headline';
        $story = $this->publishedStory($headline);

        $this->getJson('/api/v1/portal/feed')
            ->assertOk()
            ->assertJsonFragment(['headline' => $headline]);

        app(StoryService::class)->transition($story, 'killed', User::first());

        $this->getJson('/api/v1/portal/feed')
            ->assertOk()
            ->assertJsonMissing(['headline' => $headline]);
    }

    public function test_published_re_edit_bumps_epoch(): void
    {
        Queue::fake();
        $story = $this->publishedStory('Epoch re-edit headline');

        $epoch = FeedCache::epoch();
        app(StoryService::class)->updateDraft($story, ['headline' => 'Epoch re-edit headline v2'], (int) $story->version, User::first());

        $this->assertGreaterThan($epoch, FeedCache::epoch());
    }

    public function test_non_client_visible_transitions_do_not_bump_epoch(): void
    {
        Queue::fake();
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);
        $story = $svc->createDraft([
            'language' => 'en',
            'headline' => 'No bump draft headline',
            'brief' => 'b',
            'body_html' => '<p>body</p>',
            'category_id' => $cat->id,
        ], $user);

        $epoch = FeedCache::epoch();
        $svc->transition($story, 'in_review', $user);

        $this->assertSame($epoch, FeedCache::epoch());
    }
}
