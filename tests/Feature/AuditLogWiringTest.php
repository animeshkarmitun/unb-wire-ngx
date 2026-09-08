<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogWiringTest extends TestCase
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

    public function test_publish_writes_audit_log(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $s = $svc->transition($s, 'approved', $this->actor);
        $s = $svc->transition($s, 'published', $this->actor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sent_to_review', 'entity_type' => 'Story', 'entity_id' => $s->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved', 'entity_type' => 'Story']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'published', 'entity_type' => 'Story']);
    }

    public function test_handover_writes_audit_log(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        $other = User::factory()->create();
        app(StoryService::class)->takeOver($s, $other);
        $this->assertDatabaseHas('audit_logs', ['action' => 'handover', 'entity_type' => 'Story', 'entity_id' => $s->id]);
    }

    public function test_note_writes_audit_log(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        app(\App\Services\NoteService::class)->add($s, $this->actor, 'A note');
        $this->assertDatabaseHas('audit_logs', ['action' => 'note_added', 'entity_type' => 'Story', 'entity_id' => $s->id]);
    }

    public function test_restore_writes_audit_log(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        $svc = app(StoryService::class);
        $s = $svc->updateDraft($s, ['headline' => 'Changed'], 1, $this->actor);
        app(\App\Services\RevisionService::class)->restore($s, 1, $this->actor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restored', 'entity_type' => 'Story', 'entity_id' => $s->id]);
    }

    public function test_audit_log_has_correlation_id(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $log = \App\Models\AuditLog::where('action', 'sent_to_review')->first();
        $this->assertNotNull($log->correlation_id);
    }

    public function test_audit_query_for_entity_returns_paginated(): void
    {
        $s = app(StoryService::class)->createDraft([
            'language' => 'en', 'headline' => 'Test', 'brief' => 'B', 'body_html' => '<p>X</p>', 'category_id' => $this->cat->id,
        ], $this->actor);
        $svc = app(StoryService::class);
        $s = $svc->transition($s, 'in_review', $this->actor);
        $result = app(\App\Services\AuditQueryService::class)->forEntity('Story', $s->id);
        $this->assertGreaterThan(0, $result->total());
    }
}
