<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

class RevisionRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->cat = Category::factory()->create();
    }

    private function draft(array $over = []): Story
    {
        return app(StoryService::class)->createDraft(array_merge([
            'language' => 'en',
            'headline' => 'Original headline',
            'brief' => 'Original brief',
            'body_html' => '<p>Original body</p>',
            'category_id' => $this->cat->id,
        ], $over), $this->actor);
    }

    public function test_restore_creates_new_version_row(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->updateDraft($s, ['headline' => 'Changed'], 1, $this->actor);
        $this->assertEquals(2, $s->versions()->count());
        $s = app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
        $this->assertEquals(3, $s->versions()->count());
        $this->assertEquals(3, $s->version);
        $this->assertEquals('Original headline', $s->headline);
    }

    public function test_restore_preserves_old_versions(): void
    {
        $s = $this->draft();
        $before = $s->versions()->pluck('version')->sort()->values()->all();
        app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
        $after = $s->versions()->pluck('version')->sort()->values()->all();
        $this->assertEquals(array_merge($before, [2]), $after);
    }

    public function test_restore_on_published_throws(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $s = $svc->transition($s, 'published', $this->actor);
        $this->expectException(UnprocessableEntityHttpException::class);
        app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
    }

    public function test_restore_on_killed_throws(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'killed', $this->actor);
        $this->expectException(UnprocessableEntityHttpException::class);
        app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
    }

    public function test_restore_stale_version_409(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->updateDraft($s, ['headline' => 'v2'], 1, $this->actor);
        $s = $svc->updateDraft($s->refresh(), ['headline' => 'v3'], 2, $this->actor);
        $this->expectException(ConflictHttpException::class);
        app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor, 1);
    }

    public function test_restore_emits_event_with_payload(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->updateDraft($s, ['headline' => 'Changed'], 1, $this->actor);
        $s = app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
        $event = $s->events()->where('action', 'restored')->first();
        $this->assertNotNull($event);
        $this->assertEquals(2, $event->payload['from_version']); // story was at v2 when restore ran
        $this->assertEquals(1, $event->payload['to_version']);   // restored from v1
    }

    public function test_restore_reattaches_tags(): void
    {
        $s = $this->draft();
        $tag = \App\Models\Tag::create(['name' => 'politics', 'slug' => 'politics']);
        $s->tags()->attach($tag->id);
        $svc = app(StoryService::class);
        $s = $svc->updateDraft($s, ['headline' => 'Changed'], 1, $this->actor);
        $this->assertTrue($s->tags->contains('name', 'politics'));
        $s->tags()->detach();
        $s = app(\App\Services\RevisionService::class)->restore($s, 2, $this->actor);
        $this->assertTrue($s->tags->contains('name', 'politics'));
    }
}
