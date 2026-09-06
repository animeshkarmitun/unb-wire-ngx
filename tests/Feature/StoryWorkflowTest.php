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

class StoryWorkflowTest extends TestCase
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
            'headline' => 'Test headline',
            'brief' => 'Brief text here',
            'body_html' => '<p>Body content long enough</p>',
            'category_id' => $this->cat->id,
        ], $over), $this->actor);
    }

    public function test_create_draft_creates_version_and_event(): void
    {
        $s = $this->draft();
        $this->assertEquals('draft', $s->status);
        $this->assertEquals(1, $s->version);
        $this->assertEquals(1, $s->versions()->count());
        $this->assertEquals(1, $s->events()->count());
    }

    public function test_update_with_correct_version_increments(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $updated = $svc->updateDraft($s, ['headline' => 'Updated'], 1, $this->actor);
        $this->assertEquals(2, $updated->version);
        $this->assertEquals('Updated', $updated->headline);
        $this->assertEquals(2, $updated->versions()->count());
    }

    public function test_stale_version_returns_409(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $svc->updateDraft($s, ['headline' => 'v2'], 1, $this->actor);
        $this->expectException(ConflictHttpException::class);
        $svc->updateDraft($s->refresh(), ['headline' => 'v3'], 1, $this->actor);
    }

    public function test_valid_transitions(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $this->assertEquals('in_review', $s->status);
        $s = $svc->transition($s, 'approved', $this->actor);
        $this->assertEquals('approved', $s->status);
        $s = $svc->transition($s, 'published', $this->actor);
        $this->assertEquals('published', $s->status);
        $this->assertNotNull($s->published_at);
    }

    public function test_invalid_transition_rejected(): void
    {
        $s = $this->draft();
        $this->expectException(UnprocessableEntityHttpException::class);
        app(StoryService::class)->transition($s, 'published', $this->actor);
    }

    public function test_ai_touched_blocks_publish_unless_breaking(): void
    {
        $s = $this->draft(['ai_touched' => ['headline' => true]]);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $this->expectException(UnprocessableEntityHttpException::class);
        $svc->transition($s, 'published', $this->actor);
    }

    public function test_breaking_overrides_ai_gate(): void
    {
        $s = $this->draft(['ai_touched' => ['headline' => true], 'is_breaking' => true]);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $s = $svc->transition($s, 'published', $this->actor);
        $this->assertEquals('published', $s->status);
    }

    public function test_embargo_blocks_publish(): void
    {
        $s = $this->draft(['embargo_until' => now()->addHour()]);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $this->expectException(UnprocessableEntityHttpException::class);
        $svc->transition($s, 'published', $this->actor);
    }

    public function test_publish_creates_outbox_entry(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $svc->transition($s, 'published', $this->actor);
        $this->assertDatabaseHas('index_outbox', ['document_id' => $s->public_id, 'index_name' => 'main', 'op' => 'upsert']);
    }

    public function test_take_over_records_note_and_event(): void
    {
        $s = $this->draft();
        $other = User::factory()->create();
        $svc = app(StoryService::class);
        $svc->acquireLock($s, $this->actor);
        $svc->takeOver($s->refresh(), $other);
        $this->assertEquals($other->id, $s->refresh()->locked_by);
        $this->assertDatabaseHas('story_notes', ['kind' => 'system']);
        $this->assertDatabaseHas('story_events', ['action' => 'take_over']);
    }

    public function test_ai_touched_clears_on_human_edit(): void
    {
        $s = $this->draft(['ai_touched' => ['body' => true], 'body_html' => '<p>AI body</p>']);
        $svc = app(StoryService::class);
        $svc->updateDraft($s, ['body_html' => '<p>Human rewrite completely</p>'], 1, $this->actor);
        $this->assertNull($s->refresh()->ai_touched);
    }

    public function test_changes_requested_flow(): void
    {
        $s = $this->draft();
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'changes_requested', $this->actor);
        $this->assertEquals('changes_requested', $s->status);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $this->assertEquals('in_review', $s->status);
    }
}
