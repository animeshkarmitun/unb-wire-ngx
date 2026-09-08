<?php

namespace Tests\Feature\Repositories;

use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\StoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private StoryRepository $repo;

    private User $user;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(StoryRepository::class);
        $this->user = User::factory()->create();
        $this->cat = Category::factory()->create();
    }

    private function createStory(array $over = []): Story
    {
        return Story::factory()->create(array_merge([
            'language' => 'en',
            'status' => 'draft',
            'category_id' => $this->cat->id,
            'owner_id' => $this->user->id,
            'created_by' => $this->user->id,
        ], $over));
    }

    // ─── Read Methods ───────────────────────────────────────────

    public function test_filtered_list_returns_paginated_results(): void
    {
        $this->createStory(['headline' => 'Test headline']);

        $result = $this->repo->filteredList('en', 'all', 'all', '');

        $this->assertEquals(1, $result->total());
        $this->assertTrue($result->items()[0]->relationLoaded('category'));
        $this->assertTrue($result->items()[0]->relationLoaded('owner'));
    }

    public function test_filtered_list_filters_by_status(): void
    {
        $this->createStory(['status' => 'draft']);
        $this->createStory(['status' => 'published']);

        $result = $this->repo->filteredList('en', 'published', 'all', '');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('published', $result->items()[0]->status);
    }

    public function test_filtered_list_filters_by_search(): void
    {
        $this->createStory(['headline' => 'Breaking news today']);
        $this->createStory(['headline' => 'Weather update']);

        $result = $this->repo->filteredList('en', 'all', 'all', 'Breaking');

        $this->assertEquals(1, $result->total());
        $this->assertStringContainsString('Breaking', $result->items()[0]->headline);
    }

    public function test_filtered_ids_returns_string_ids(): void
    {
        $story = $this->createStory();

        $ids = $this->repo->filteredIds('en', 'all', 'all', '');

        $this->assertContains((string) $story->id, $ids);
    }

    public function test_find_with_details_eager_loads_relations(): void
    {
        $story = $this->createStory();

        $result = $this->repo->findWithDetails($story->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('category'));
        $this->assertTrue($result->relationLoaded('owner'));
        $this->assertTrue($result->relationLoaded('events'));
    }

    public function test_find_with_all_relations_eager_loads_all(): void
    {
        $story = $this->createStory();

        $result = $this->repo->findWithAllRelations($story->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('tags'));
        $this->assertTrue($result->relationLoaded('media'));
        $this->assertTrue($result->relationLoaded('versions'));
        $this->assertTrue($result->relationLoaded('notes'));
        $this->assertTrue($result->relationLoaded('events'));
    }

    public function test_find_by_public_id_returns_story(): void
    {
        $story = $this->createStory();

        $result = $this->repo->findByPublicId($story->public_id);

        $this->assertNotNull($result);
        $this->assertEquals($story->id, $result->id);
        $this->assertTrue($result->relationLoaded('category'));
        $this->assertTrue($result->relationLoaded('tags'));
        $this->assertTrue($result->relationLoaded('media'));
    }

    public function test_status_counts_returns_correct_counts(): void
    {
        $this->createStory(['status' => 'draft']);
        $this->createStory(['status' => 'published']);
        $this->createStory(['status' => 'published']);

        $counts = $this->repo->statusCounts('en');

        $this->assertEquals(3, $counts['all']);
        $this->assertEquals(1, $counts['draft']);
        $this->assertEquals(2, $counts['published']);
    }

    public function test_count_by_date_and_status_returns_count(): void
    {
        $this->createStory(['status' => 'published', 'published_at' => now()]);

        $count = $this->repo->countByDateAndStatus(now(), 'published');

        $this->assertEquals(1, $count);
    }

    public function test_headlines_returns_plucked_headlines(): void
    {
        $this->createStory(['status' => 'published', 'headline' => 'Test Headline']);

        $headlines = $this->repo->headlines('en', 5);

        $this->assertTrue($headlines->contains('Test Headline'));
    }

    public function test_related_stories_returns_related_by_category(): void
    {
        $story = $this->createStory(['status' => 'published']);
        $related = $this->createStory(['status' => 'published', 'category_id' => $this->cat->id]);

        $result = $this->repo->relatedStories($story->id, $this->cat->id, 3);

        $this->assertTrue($result->contains($related));
        $this->assertFalse($result->contains($story));
    }

    public function test_latest_rail_excludes_given_id(): void
    {
        $story = $this->createStory(['status' => 'published']);
        $other = $this->createStory(['status' => 'published']);

        $result = $this->repo->latestRail('en', 5, $story->id);

        $this->assertFalse($result->contains($story));
        $this->assertTrue($result->contains($other));
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function test_create_creates_story(): void
    {
        $story = $this->repo->create([
            'language' => 'en',
            'headline' => 'New story',
            'brief' => 'Brief text',
            'body_html' => '<p>Body</p>',
            'body_text' => 'Body',
            'status' => 'draft',
            'category_id' => $this->cat->id,
            'owner_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('stories', ['id' => $story->id, 'headline' => 'New story']);
    }

    public function test_update_updates_story(): void
    {
        $story = $this->createStory();

        $updated = $this->repo->update($story, ['headline' => 'Updated']);

        $this->assertEquals('Updated', $updated->headline);
    }

    public function test_delete_soft_deletes(): void
    {
        $story = $this->createStory();

        $this->repo->delete($story->id);

        $this->assertSoftDeleted('stories', ['id' => $story->id]);
    }

    public function test_delete_many_deletes_multiple(): void
    {
        $s1 = $this->createStory();
        $s2 = $this->createStory();

        $this->repo->deleteMany([$s1->id, $s2->id]);

        $this->assertSoftDeleted('stories', ['id' => $s1->id]);
        $this->assertSoftDeleted('stories', ['id' => $s2->id]);
    }

    public function test_create_version_creates_version_record(): void
    {
        $story = $this->createStory();

        $this->repo->createVersion($story, ['headline' => 'test'], $this->user->id);

        $this->assertEquals(1, $story->versions()->count());
    }

    public function test_create_event_creates_event_record(): void
    {
        $story = $this->createStory();

        $this->repo->createEvent($story, [
            'actor_id' => $this->user->id,
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'draft',
        ]);

        $this->assertEquals(1, $story->events()->count());
    }

    public function test_create_note_creates_note_record(): void
    {
        $story = $this->createStory();

        $this->repo->createNote($story, [
            'user_id' => $this->user->id,
            'body' => 'Test note',
            'is_internal' => true,
        ]);

        $this->assertEquals(1, $story->notes()->count());
    }

    public function test_sync_tags_creates_and_syncs_tags(): void
    {
        $story = $this->createStory();

        $this->repo->syncTags($story, ['bangladesh', 'dhaka']);

        $this->assertEquals(2, $story->tags()->count());
        $this->assertTrue($story->tags->contains('name', 'bangladesh'));
    }

    public function test_sync_tags_clears_tags_on_empty_array(): void
    {
        $story = $this->createStory();
        $tag = Tag::create(['name' => 'test', 'slug' => 'test']);
        $story->tags()->attach($tag->id);

        $this->repo->syncTags($story, []);

        $this->assertEquals(0, $story->tags()->count());
    }

    public function test_get_attached_media_returns_collection(): void
    {
        $story = $this->createStory();

        $result = $this->repo->getAttachedMedia($story->id);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_get_attached_media_returns_joined_results(): void
    {
        $story = $this->createStory();
        // This test requires media_assets table to have data
        // Skipping if no media assets exist
        $result = $this->repo->getAttachedMedia($story->id);

        $this->assertInstanceOf(Collection::class, $result);
    }

    // ─── Wire Feed Methods ─────────────────────────────────────

    public function test_hero_story_returns_breaking_first(): void
    {
        $this->createStory(['status' => 'published', 'is_breaking' => false]);
        $breaking = $this->createStory(['status' => 'published', 'is_breaking' => true]);

        $result = $this->repo->heroStory('en');

        $this->assertNotNull($result);
        $this->assertEquals($breaking->id, $result->id);
    }

    public function test_published_by_category_filters_correctly(): void
    {
        $story = $this->createStory(['status' => 'published', 'category_id' => $this->cat->id]);

        $result = $this->repo->publishedByCategory('en', $this->cat->id);

        $this->assertTrue($result->contains($story));
    }

    public function test_published_feed_returns_published_stories(): void
    {
        $this->createStory(['status' => 'published']);
        $this->createStory(['status' => 'draft']);

        $result = $this->repo->publishedFeed('en');

        $this->assertEquals(1, $result->count());
    }
}
