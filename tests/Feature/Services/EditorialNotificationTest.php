<?php

namespace Tests\Feature\Services;

use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\NoteService;
use App\Services\StoryService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EditorialNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    private function editorRole(): Role
    {
        return Role::where('name', 'Editor')->firstOrFail();
    }

    private function adminRole(): Role
    {
        return Role::where('name', 'Admin')->firstOrFail();
    }

    private function uploaderRole(): Role
    {
        return Role::where('name', 'Uploader-English')->firstOrFail();
    }

    // --- NTF-001: Transition notifications ---

    public function test_in_review_transition_notifies_editors_and_admins(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $admin = User::factory()->create(['role_id' => $this->adminRole()->id]);
        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'draft']);
        Cache::flush();

        app(StoryService::class)->transition($story, 'in_review', $uploader);

        Notification::assertSentTo([$editor, $admin], StoryNotification::class);
    }

    public function test_approved_transition_notifies_story_owner(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'in_review']);
        Cache::flush();

        app(StoryService::class)->transition($story, 'approved', $editor);

        Notification::assertSentTo($uploader, StoryNotification::class, function ($notification) {
            return $notification->event === 'approved';
        });
    }

    public function test_changes_requested_transition_notifies_story_owner(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'in_review']);
        Cache::flush();

        app(StoryService::class)->transition($story, 'changes_requested', $editor);

        Notification::assertSentTo($uploader, StoryNotification::class, function ($notification) {
            return $notification->event === 'changes_requested';
        });
    }

    public function test_killed_transition_notifies_story_owner(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'approved']);
        Cache::flush();

        app(StoryService::class)->transition($story, 'killed', $editor);

        Notification::assertSentTo($uploader, StoryNotification::class, function ($notification) {
            return $notification->event === 'killed';
        });
    }

    // --- NTF-002: Handover notifications ---

    public function test_takeover_notifies_previous_owner(): void
    {
        Notification::fake();

        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);
        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);

        $story = Story::factory()->create([
            'owner_id' => $uploader->id,
            'locked_by' => $uploader->id,
            'locked_at' => now(),
            'status' => 'draft',
        ]);
        Cache::flush();

        app(StoryService::class)->takeOver($story, $editor);

        Notification::assertSentTo($uploader, StoryNotification::class, function ($notification) {
            return $notification->event === 'handover';
        });
    }

    public function test_takeover_does_not_self_notify(): void
    {
        Notification::fake();

        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create([
            'owner_id' => $uploader->id,
            'locked_by' => $uploader->id,
            'locked_at' => now(),
            'status' => 'draft',
        ]);
        Cache::flush();

        app(StoryService::class)->takeOver($story, $uploader);

        Notification::assertNothingSent();
    }

    // --- NTF-003: Note notifications ---

    public function test_note_notifies_story_owner(): void
    {
        Notification::fake();

        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'in_review']);
        Cache::flush();

        app(NoteService::class)->add($story, $editor, 'Please fix the lead paragraph.');

        Notification::assertSentTo($uploader, StoryNotification::class, function ($notification) {
            return $notification->event === 'note_added';
        });
    }

    public function test_note_does_not_notify_author(): void
    {
        Notification::fake();

        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'draft']);
        Cache::flush();

        // Uploader adding a note to their own story (no other participants)
        app(NoteService::class)->add($story, $uploader, 'Adding context for the editor.');

        Notification::assertNothingSent();
    }

    public function test_note_notifies_prior_participants(): void
    {
        Notification::fake();

        $uploader = User::factory()->create(['role_id' => $this->uploaderRole()->id]);
        $editor = User::factory()->create(['role_id' => $this->editorRole()->id]);
        $editor2 = User::factory()->create(['role_id' => $this->editorRole()->id]);

        $story = Story::factory()->create(['owner_id' => $uploader->id, 'status' => 'in_review']);

        // Editor adds first note (creates a prior participant)
        $story->notes()->create(['user_id' => $editor->id, 'kind' => 'note', 'is_internal' => true, 'body' => 'First note']);
        Cache::flush();

        // Editor2 adds a note — should notify both uploader (owner) and editor (prior participant)
        app(NoteService::class)->add($story, $editor2, 'Follow-up note.');

        Notification::assertSentTo($uploader, StoryNotification::class);
        Notification::assertSentTo($editor, StoryNotification::class);
        Notification::assertNotSentTo($editor2, StoryNotification::class);
    }
}
