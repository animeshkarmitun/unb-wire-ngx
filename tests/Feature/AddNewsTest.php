<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Livewire\Admin\AddNews;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AddNewsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $uploader;

    private User $admin;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);

        $editorRole = Role::where('name', 'Editor')->first();
        $uploaderRole = Role::where('name', 'Uploader-English')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        $this->editor = User::factory()->create(['role_id' => $editorRole->id]);
        $this->uploader = User::factory()->create(['role_id' => $uploaderRole->id]);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->cat = Category::where('name_en', 'Bangladesh')->first()
            ?? Category::first()
            ?? Category::factory()->create();
    }

    private function makeMediaAsset(string $title = 'Test photo', string $kind = 'photo'): MediaAsset
    {
        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => $kind,
            'status' => 'library',
            'title' => $title,
            'caption' => $title.' caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 's3',
            'original_path' => Str::random().'.jpg',
            'uploaded_by' => $this->editor->id,
        ]);
    }

    private function createDraftStory(?User $owner = null): Story
    {
        $owner = $owner ?? $this->editor;

        return Story::factory()->create([
            'status' => 'draft',
            'language' => 'en',
            'headline' => 'Existing draft',
            'brief' => 'Draft brief',
            'body_html' => '<p>Draft body</p>',
            'category_id' => $this->cat->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);
    }

    // ─── Stepper & Validation ───────────────────────────────────────

    public function test_step1_validation_blocks_without_headline_and_brief(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->call('next')
            ->assertHasErrors(['headline', 'brief'])
            ->assertSet('step', 1);
    }

    public function test_step1_validation_passes_with_required_fields(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->set('headline', 'Test headline for step 1')
            ->set('brief', 'Test brief content')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }

    public function test_stepper_navigates_through_all_steps(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Full stepper test')
            ->set('brief', 'Brief for stepper')
            ->set('bodyHtml', '<p>Body content</p>')
            ->set('categoryId', (string) $this->cat->id);

        $cmp->call('next')->assertSet('step', 2);
        $cmp->call('next')->assertSet('step', 3);
        $cmp->call('next')->assertSet('step', 4);

        $cmp->call('prev')->assertSet('step', 3);
        $cmp->call('prev')->assertSet('step', 2);
    }

    public function test_go_to_specific_step(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Go test')
            ->set('brief', 'Go brief')
            ->call('next')
            ->assertSet('step', 2);

        $cmp->call('go', 4)->assertSet('step', 4);
        $cmp->call('go', 1)->assertSet('step', 1);
    }

    public function test_go_forward_validates_current_step(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->call('go', 3)
            ->assertHasErrors(['headline'])
            ->assertSet('step', 1);
    }

    // ─── Language Switch ────────────────────────────────────────────

    public function test_language_switch_dispatches_event(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->call('setLanguage', 'bn')
            ->assertSet('language', 'bn')
            ->assertDispatched('language-changed');
    }

    public function test_language_switch_rejects_invalid(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->call('setLanguage', 'fr')
            ->assertSet('language', 'en');
    }

    // ─── Tag Management ─────────────────────────────────────────────

    public function test_add_tag_creates_and_syncs(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Tag test')
            ->set('brief', 'Tag brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('addTag', 'bangladesh')
            ->assertSet('tags', ['bangladesh']);

        $cmp->call('addTag', 'dhaka')->assertSet('tags', ['bangladesh', 'dhaka']);

        $story = Story::find($cmp->get('storyId'));
        $this->assertTrue($story->tags->pluck('name')->contains('bangladesh'));
        $this->assertTrue($story->tags->pluck('name')->contains('dhaka'));
    }

    public function test_add_tag_rejects_duplicate(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Dup tag')
            ->set('brief', 'Dup brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('addTag', 'politics')
            ->call('addTag', 'politics')
            ->assertSet('tags', ['politics']);
    }

    public function test_add_tag_normalizes_and_strips_hash(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->call('addTag', '#Bangladesh ')
            ->assertSet('tags', ['bangladesh']);
    }

    public function test_remove_tag(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Remove tag')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('addTag', 'dhaka')
            ->call('addTag', 'economy')
            ->assertSet('tags', ['dhaka', 'economy']);

        $cmp->call('removeTag', 'dhaka')->assertSet('tags', ['economy']);
    }

    // ─── Media Management ──────────────────────────────────────────

    public function test_set_featured_image(): void
    {
        $this->actingAs($this->editor);
        $asset = $this->makeMediaAsset('Featured photo');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Media test')
            ->set('brief', 'Media brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('setFeatured', $asset->id, 'Featured caption')
            ->assertSet('featuredMediaId', $asset->id)
            ->assertSet('featuredCaption', 'Featured caption')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('story_media', [
            'story_id' => $cmp->get('storyId'),
            'asset_id' => $asset->id,
            'role' => 'featured',
        ]);
    }

    public function test_toggle_media_attaches_and_detaches(): void
    {
        $this->actingAs($this->editor);
        $asset = $this->makeMediaAsset('Toggle photo');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Toggle media')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id);

        // Attach
        $cmp->call('toggleMedia', $asset->id, 'My caption', 'photo')
            ->assertSet('selectedMediaIds', fn ($ids) => in_array($asset->id, $ids));

        $this->assertDatabaseHas('story_media', [
            'story_id' => $cmp->get('storyId'),
            'asset_id' => $asset->id,
            'role' => 'inline',
        ]);

        // Detach
        $cmp->call('toggleMedia', $asset->id)
            ->assertSet('selectedMediaIds', fn ($ids) => ! in_array($asset->id, $ids));

        $this->assertDatabaseMissing('story_media', [
            'story_id' => $cmp->get('storyId'),
            'asset_id' => $asset->id,
        ]);
    }

    public function test_clear_media_removes_all(): void
    {
        $this->actingAs($this->editor);
        $a1 = $this->makeMediaAsset('Photo 1');
        $a2 = $this->makeMediaAsset('Photo 2');

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Clear media')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('setFeatured', $a1->id, 'Featured')
            ->call('toggleMedia', $a2->id, 'Inline')
            ->call('clearMedia')
            ->assertSet('selectedMediaIds', [])
            ->assertSet('attachedMedia', [])
            ->assertSet('featuredMediaId', null);
    }

    // ─── Takeover ──────────────────────────────────────────────────

    public function test_take_over_transfers_ownership(): void
    {
        $originalOwner = User::factory()->create(['role_id' => $this->editor->role_id]);
        $story = $this->createDraftStory($originalOwner);

        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class, ['id' => $story->id])
            ->call('takeOver')
            ->assertSet('ownerId', $this->editor->id)
            ->assertSet('ownerName', $this->editor->name)
            ->assertDispatched('toast');

        $story->refresh();
        $this->assertEquals($this->editor->id, $story->owner_id);

        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'take_over',
        ]);
    }

    // ─── Internal Notes ────────────────────────────────────────────

    public function test_add_note_creates_note_and_event(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Note test')
            ->set('brief', 'Note brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $storyId = $cmp->get('storyId');

        $cmp->set('noteBody', 'This is an internal note for the desk')
            ->call('addNote')
            ->assertSet('noteBody', '')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('story_notes', [
            'story_id' => $storyId,
            'user_id' => $this->editor->id,
            'body' => 'This is an internal note for the desk',
            'is_internal' => true,
        ]);

        $this->assertDatabaseHas('story_events', [
            'story_id' => $storyId,
            'action' => 'note_added',
        ]);
    }

    public function test_add_note_validates_minimum_length(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->set('noteBody', 'x')
            ->call('addNote')
            ->assertHasErrors(['noteBody']);
    }

    // ─── Document Import ───────────────────────────────────────────

    public function test_sync_from_doc_imports_headline_and_body(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->call('syncFromDoc', 'Imported headline', '<p>Imported body content from document</p>', 'Imported brief text')
            ->assertSet('headline', 'Imported headline')
            ->assertSet('brief', 'Imported brief text')
            ->assertDispatched('toast');

        $this->assertStringContainsString('Imported body', $cmp->get('bodyHtml'));
    }

    public function test_sync_from_doc_auto_generates_brief_if_missing(): void
    {
        $this->actingAs($this->editor);

        $longBody = '<p>'.str_repeat('This is a long body text that should be truncated. ', 20).'</p>';

        $cmp = Livewire::test(AddNews::class)
            ->call('syncFromDoc', 'Headline', $longBody)
            ->assertSet('headline', 'Headline');

        $this->assertNotEmpty($cmp->get('brief'));
        $this->assertLessThanOrEqual(280, mb_strlen($cmp->get('brief')));
    }

    // ─── AI Touched & Guardrails ───────────────────────────────────

    public function test_apply_ai_sets_touched_flags(): void
    {
        $this->actingAs($this->editor);

        // Ensure AI budget is available
        DB::table('ai_token_usage_daily')->delete();

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Original headline')
            ->set('brief', 'Original brief')
            ->set('bodyHtml', '<p>Original body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('callAi', 'preedit')
            ->assertSet('aiLoading', false);

        $pack = $cmp->get('aiPack');
        $this->assertNotNull($pack, 'AI pack should not be null');
        $this->assertArrayNotHasKey('error', $pack, 'AI returned error: '.json_encode($pack));
        $this->assertArrayHasKey('headline', $pack);

        $cmp->call('applyAi', 'headline')
            ->assertSet('aiTouched.headline', true);

        $cmp->call('applyAi', 'brief')
            ->assertSet('aiTouched.brief', true);

        $cmp->call('applyAi', 'body')
            ->assertSet('aiTouched.body', true);
    }

    public function test_manual_edit_clears_ai_touched_flag(): void
    {
        $this->actingAs($this->editor);
        DB::table('ai_token_usage_daily')->delete();

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'AI headline')
            ->set('brief', 'AI brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('callAi', 'preedit')
            ->call('applyAi', 'headline')
            ->assertSet('aiTouched.headline', true);

        // Manual edit should clear the flag
        $cmp->set('headline', 'Manually edited headline');
        $this->assertArrayNotHasKey('headline', $cmp->get('aiTouched'));
    }

    public function test_ai_touched_body_cleared_on_sync_body(): void
    {
        $this->actingAs($this->editor);
        DB::table('ai_token_usage_daily')->delete();

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Test')
            ->set('brief', 'Test brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('callAi', 'preedit')
            ->call('applyAi', 'body')
            ->assertSet('aiTouched.body', true);

        $cmp->call('syncBody', '<p>Manually rewritten body</p>');
        $this->assertArrayNotHasKey('body', $cmp->get('aiTouched'));
    }

    public function test_ai_touched_non_breaking_blocked_from_publish(): void
    {
        $this->actingAs($this->editor);

        // Ensure AI budget is available
        DB::table('ai_token_usage_daily')->delete();

        DB::table('settings')->where('key', 'ai.desk')->update([
            'value' => json_encode(['autoPublish' => false, 'autoCats' => [], 'killed' => false, 'monthlyCap' => 500000, 'preeditEn' => true]),
        ]);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'AI story')
            ->set('brief', 'AI brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $storyId = $cmp->get('storyId');
        $this->assertNotNull($storyId);

        $cmp->call('callAi', 'preedit');

        $pack = $cmp->get('aiPack');
        $this->assertNotNull($pack, 'AI pack should not be null');
        $this->assertArrayNotHasKey('error', $pack, 'AI returned error: '.json_encode($pack));

        $cmp->call('applyAi', 'headline');

        $cmp->call('publish');

        $story = Story::find($storyId);
        $this->assertNotEquals('published', $story->status);
    }

    // ─── Publish Lifecycle ─────────────────────────────────────────

    public function test_full_publish_flow_transitions_to_published(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Publish test story')
            ->set('brief', 'Publish brief')
            ->set('bodyHtml', '<p>Publish body content</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $story = Story::find($cmp->get('storyId'));
        $this->assertEquals('published', $story->status);
        $this->assertNotNull($story->published_at);
        $this->assertEquals('published', $cmp->get('successState'));
    }

    public function test_publish_creates_full_event_chain(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Event chain test')
            ->set('brief', 'Event brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $storyId = $cmp->get('storyId');

        $this->assertDatabaseHas('story_events', ['story_id' => $storyId, 'action' => 'in_review']);
        $this->assertDatabaseHas('story_events', ['story_id' => $storyId, 'action' => 'approved']);
        $this->assertDatabaseHas('story_events', ['story_id' => $storyId, 'action' => 'published']);
    }

    public function test_publish_dispatches_fanout_and_index_jobs(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Job dispatch test')
            ->set('brief', 'Job brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        Queue::assertPushed(FanoutStory::class);
        Queue::assertPushed(ProcessIndexOutbox::class);
    }

    public function test_publish_inserts_index_outbox_row(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Outbox test')
            ->set('brief', 'Outbox brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $story = Story::find($cmp->get('storyId'));
        $this->assertDatabaseHas('index_outbox', [
            'document_id' => $story->public_id,
            'status' => 'pending',
        ]);
    }

    // ─── RBAC Enforcement ──────────────────────────────────────────

    public function test_uploader_cannot_publish(): void
    {
        $this->actingAs($this->uploader);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'RBAC test')
            ->set('brief', 'RBAC brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $storyId = $cmp->get('storyId');
        $this->assertNotNull($storyId);

        $cmp->call('publish');

        $story = Story::find($storyId);
        $this->assertNotEquals('published', $story->status);
    }

    public function test_editor_can_publish(): void
    {
        Queue::fake();
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Editor publish')
            ->set('brief', 'Editor brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('publish');

        $story = Story::find($cmp->get('storyId'));
        $this->assertEquals('published', $story->status);
    }

    public function test_send_to_review_transitions_to_in_review(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Review test')
            ->set('brief', 'Review brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('sendToReview')
            ->assertSet('status', 'in_review')
            ->assertSet('successState', 'sent')
            ->assertDispatched('toast');

        $story = Story::find($cmp->get('storyId'));
        $this->assertEquals('in_review', $story->status);
        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'in_review',
        ]);
    }

    // ─── Edit Existing Story ───────────────────────────────────────

    public function test_mount_with_existing_story_loads_state(): void
    {
        $this->actingAs($this->editor);
        $tag = Tag::create(['name' => 'politics', 'slug' => 'politics']);
        $story = $this->createDraftStory();
        $story->tags()->attach($tag->id);

        $cmp = Livewire::test(AddNews::class, ['id' => $story->id])
            ->assertSet('storyId', $story->id)
            ->assertSet('headline', 'Existing draft')
            ->assertSet('brief', 'Draft brief')
            ->assertSet('status', 'draft')
            ->assertSet('tags', ['politics']);
    }

    // ─── Autosave & Versioning ─────────────────────────────────────

    public function test_autosave_increments_version(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Version test')
            ->set('brief', 'Version brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $story = Story::find($cmp->get('storyId'));
        $this->assertEquals(1, $story->version);

        $cmp->set('headline', 'Updated headline')->call('autosave');
        $story->refresh();
        $this->assertEquals(2, $story->version);
    }

    public function test_autosave_creates_version_snapshot(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'Snapshot test')
            ->set('brief', 'Snapshot brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('autosave');

        $this->assertDatabaseHas('story_versions', [
            'story_id' => $cmp->get('storyId'),
            'version' => 1,
        ]);
    }

    public function test_updated_headline_clears_ai_flag_and_dispatches(): void
    {
        $this->actingAs($this->editor);
        DB::table('ai_token_usage_daily')->delete();

        $cmp = Livewire::test(AddNews::class)
            ->set('headline', 'AI test')
            ->set('brief', 'Brief')
            ->set('bodyHtml', '<p>Body</p>')
            ->set('categoryId', (string) $this->cat->id)
            ->call('callAi', 'preedit')
            ->call('applyAi', 'headline')
            ->assertSet('aiTouched.headline', true);

        $cmp->set('headline', 'Manual edit');
        $this->assertArrayNotHasKey('headline', $cmp->get('aiTouched'));
        $cmp->assertDispatched('story-updated');
    }
}
