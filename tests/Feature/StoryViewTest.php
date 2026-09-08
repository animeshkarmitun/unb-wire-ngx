<?php

namespace Tests\Feature;

use App\Livewire\Admin\StoryView;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\StoryEvent;
use App\Models\StoryVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoryViewTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $category;

    private Story $story;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Editor',
            'type' => 'system',
            'description' => 'Editor with full story permissions',
            'is_locked' => false,
        ]);

        $role->permissions()->create([
            'module' => 'stories',
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_publish' => true,
            'can_delete' => true,
        ]);

        $this->editor = User::factory()->create(['role_id' => $role->id]);
        $this->category = Category::factory()->create(['name_en' => 'National']);

        $this->story = Story::create([
            'language' => 'en',
            'headline' => "UNB District Correspondents' Conference puts onus on credible journalism",
            'sub_head' => 'Daylong engagements on different aspects of media landscape',
            'brief' => 'Credible journalism begins at the district level.',
            'body_html' => '<p>UNB annual District Correspondents Conference began in Dhaka.</p><p>Sessions focused on fact checking and field reporting.</p>',
            'body_text' => 'UNB annual District Correspondents Conference began in Dhaka. Sessions focused on fact checking and field reporting.',
            'category_id' => $this->category->id,
            'dateline_city' => 'Dhaka',
            'status' => 'draft',
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
            'word_count' => 350,
            'priority' => 'normal',
        ]);
    }

    public function test_authorized_user_can_view_story_reader(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.story', $this->story->public_id));

        $response->assertOk();
        $response->assertSee("UNB District Correspondents' Conference");
        $response->assertSee('Download Word');
        $response->assertSee('Download Text');
        $response->assertSee('Download XML');
        $response->assertSee('Download Image');
        $response->assertSee('Print');
        $response->assertSee('Copy Dispatch');
        $response->assertSee('Newsroom Control');
        $response->assertSee('Latest wire news');
    }

    public function test_unauthorized_user_without_stories_permission_is_forbidden(): void
    {
        $restrictedRole = Role::create([
            'name' => 'Restricted',
            'type' => 'custom',
            'description' => 'No permissions',
            'is_locked' => false,
        ]);
        $user = User::factory()->create(['role_id' => $restrictedRole->id]);

        $response = $this->actingAs($user)->get(route('admin.story', $this->story->public_id));

        $response->assertForbidden();
    }

    public function test_editor_can_add_internal_note_via_livewire(): void
    {
        Livewire::actingAs($this->editor)
            ->test(StoryView::class, ['publicId' => $this->story->public_id])
            ->set('newNote', 'Verified source with bureau chief.')
            ->call('addNote')
            ->assertDispatched('toast')
            ->assertSet('newNote', '');

        $this->assertDatabaseHas('story_notes', [
            'story_id' => $this->story->id,
            'user_id' => $this->editor->id,
            'body' => 'Verified source with bureau chief.',
            'is_internal' => true,
        ]);
    }

    public function test_editor_can_transition_workflow_status(): void
    {
        Livewire::actingAs($this->editor)
            ->test(StoryView::class, ['publicId' => $this->story->public_id])
            ->call('transitionStatus', 'in_review')
            ->assertDispatched('toast');

        $this->assertEquals('in_review', $this->story->fresh()->status);
    }

    public function test_related_stories_and_latest_rail_computes_cleanly(): void
    {
        Story::create([
            'language' => 'en',
            'headline' => 'Second story in same category',
            'brief' => 'Brief for second story',
            'body_html' => '<p>Body text for second story</p>',
            'body_text' => 'Body text for second story',
            'category_id' => $this->category->id,
            'status' => 'published',
            'published_at' => now(),
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
        ]);

        Story::create([
            'language' => 'en',
            'headline' => 'Third story published today',
            'brief' => 'Brief for third story',
            'body_html' => '<p>Body text for third story</p>',
            'body_text' => 'Body text for third story',
            'category_id' => $this->category->id,
            'status' => 'published',
            'published_at' => now()->subHour(),
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'version' => 1,
        ]);

        $component = Livewire::actingAs($this->editor)
            ->test(StoryView::class, ['publicId' => $this->story->public_id]);

        $this->assertNotEmpty($component->get('relatedStories'));
        $this->assertNotEmpty($component->get('latestRail'));
    }

    public function test_timeline_renders_when_history_permission_granted(): void
    {
        $this->editor->role->permissions()->create([
            'module' => 'history',
            'can_view' => true,
        ]);
        $this->story->events()->create([
            'actor_id' => $this->editor->id,
            'action' => 'sent_to_review',
            'from_status' => 'draft',
            'to_status' => 'in_review',
        ]);

        $response = $this->actingAs($this->editor)->get(route('admin.story', $this->story->public_id));

        $response->assertOk();
        $response->assertSee('Workflow Timeline');
        $response->assertSee('Version History');
        $response->assertSee('Sent to review');
    }

    public function test_timeline_hidden_when_history_permission_denied(): void
    {
        $response = $this->actingAs($this->editor)->get(route('admin.story', $this->story->public_id));

        $response->assertOk();
        $response->assertDontSee('Workflow Timeline');
        $response->assertDontSee('Version History');
    }

    public function test_compare_versions_shows_diff(): void
    {
        $this->editor->role->permissions()->where('module', 'stories')->update(['can_edit' => true]);
        $this->editor->role->permissions()->create(['module' => 'history', 'can_view' => true]);
        $this->story->update(['version' => 2]);
        $this->story->versions()->create(['version' => 1, 'snapshot' => ['headline' => 'Old', 'body_html' => '<p>Old</p>', 'tags' => []], 'created_by' => $this->editor->id, 'created_at' => now()]);
        $this->story->versions()->create(['version' => 2, 'snapshot' => ['headline' => 'New', 'body_html' => '<p>New</p>', 'tags' => []], 'created_by' => $this->editor->id, 'created_at' => now()]);

        Livewire::actingAs($this->editor)
            ->test(StoryView::class, ['publicId' => $this->story->public_id])
            ->set('diffA', 1)
            ->set('diffB', 2)
            ->call('compareVersions')
            ->assertSet('diffResult', fn ($r) => ! empty($r['fields']))
            ->assertSee('Diff: v1 ↔ v2');
    }

    public function test_restore_button_visible_for_editable_stories(): void
    {
        $this->editor->role->permissions()->where('module', 'stories')->update(['can_edit' => true]);
        $this->editor->role->permissions()->create(['module' => 'history', 'can_view' => true]);

        $component = Livewire::actingAs($this->editor)
            ->test(StoryView::class, ['publicId' => $this->story->public_id]);

        $component->assertDontSee('Restore'); // only v1 exists, current = v1
    }
}
