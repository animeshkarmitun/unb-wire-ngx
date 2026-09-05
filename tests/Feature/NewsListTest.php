<?php

namespace Tests\Feature;

use App\Livewire\Admin\NewsList;
use App\Models\Category;
use App\Models\Role;
use App\Models\Story;
use App\Models\StoryNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsListTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;
    private User $otherUser;
    private Category $category;
    private Category $subCategory;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(true);

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $editorRole = Role::where('name', 'Editor')->firstOrFail();
        $uploaderRole = Role::where('name', 'Uploader-English')->firstOrFail();

        $this->editor = User::factory()->create([
            'role_id' => $editorRole->id,
            'email' => 'editor-test@example.com',
        ]);

        $this->otherUser = User::factory()->create([
            'role_id' => $uploaderRole->id,
            'name' => 'Shohel Ahmed',
        ]);

        $this->category = Category::factory()->create([
            'name_en' => 'Bangladesh',
            'parent_id' => null,
        ]);

        $this->subCategory = Category::factory()->create([
            'name_en' => 'Education',
            'parent_id' => $this->category->id,
        ]);
    }

    public function test_news_list_renders_en_and_bn_surfaces(): void
    {
        $this->actingAs($this->editor);

        Story::factory()->create([
            'headline' => 'Dhaka metro expansion',
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->category->id,
            'sub_category_id' => $this->subCategory->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        Story::factory()->create([
            'headline' => 'ঢাকা মেট্রোরেল সম্প্রসারণ',
            'language' => 'bn',
            'status' => 'published',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $resEn = $this->get('/admin/news/en');
        $resEn->assertOk();
        $resEn->assertSee('English News');
        $resEn->assertSee('Dhaka metro expansion');
        $resEn->assertDontSee('ঢাকা মেট্রোরেল সম্প্রসারণ');

        $resBn = $this->get('/admin/news/bn');
        $resBn->assertOk();
        $resBn->assertSee('Bangla News');
        $resBn->assertSee('ঢাকা মেট্রোরেল সম্প্রসারণ');
    }

    public function test_news_list_filters_by_status_and_category(): void
    {
        $this->actingAs($this->editor);

        Story::factory()->create([
            'headline' => 'Live Story',
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        Story::factory()->create([
            'headline' => 'Draft Story',
            'language' => 'en',
            'status' => 'draft',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('status', 'draft');
        $cmp->assertSee('Draft Story');
        $cmp->assertDontSee('Live Story');

        $cmp->set('status', 'published');
        $cmp->assertSee('Live Story');
        $cmp->assertDontSee('Draft Story');
    }

    public function test_workflow_drawer_open_and_notes_flow(): void
    {
        $this->actingAs($this->editor);

        $story = Story::factory()->create([
            'headline' => 'Under Review Article',
            'language' => 'en',
            'status' => 'in_review',
            'category_id' => $this->category->id,
            'owner_id' => $this->otherUser->id,
            'created_by' => $this->otherUser->id,
        ]);

        StoryNote::create([
            'story_id' => $story->id,
            'user_id' => $this->otherUser->id,
            'kind' => 'sub',
            'is_internal' => true,
            'body' => 'Please check the quotes from OC.',
            'created_at' => now(),
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->call('openDrawer', $story->id);
        $cmp->assertSet('selectedId', $story->id);
        $cmp->assertSee('Under Review Article');
        $cmp->assertSee('Please check the quotes from OC.');
        $cmp->assertSee('Shohel Ahmed');

        // Post a note reply
        $cmp->set('noteText', 'Looks good. Recheck para 3 before publish.');
        $cmp->call('addNote');
        $cmp->assertSet('noteText', '');
        $cmp->assertSee('Looks good. Recheck para 3 before publish.');

        $this->assertDatabaseHas('story_notes', [
            'story_id' => $story->id,
            'body' => 'Looks good. Recheck para 3 before publish.',
            'is_internal' => true,
        ]);
    }

    public function test_take_over_reassigns_ownership_and_records_audit(): void
    {
        $this->actingAs($this->editor);

        $story = Story::factory()->create([
            'headline' => 'Crime Story',
            'language' => 'en',
            'status' => 'in_review',
            'category_id' => $this->category->id,
            'owner_id' => $this->otherUser->id,
            'locked_by' => $this->otherUser->id,
            'locked_at' => now(),
            'version' => 1,
            'created_by' => $this->otherUser->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->call('openDrawer', $story->id);
        $cmp->call('takeOver');

        $fresh = $story->fresh();
        $this->assertEquals($this->editor->id, $fresh->owner_id);
        $this->assertEquals($this->editor->id, $fresh->locked_by);

        $this->assertDatabaseHas('story_notes', [
            'story_id' => $story->id,
            'kind' => 'system',
        ]);

        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'take_over',
            'actor_id' => $this->editor->id,
        ]);
    }

    public function test_bulk_publish_and_bulk_delete(): void
    {
        $this->actingAs($this->editor);

        $s1 = Story::factory()->create([
            'headline' => 'Story 1',
            'language' => 'en',
            'status' => 'draft',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $s2 = Story::factory()->create([
            'headline' => 'Story 2',
            'language' => 'en',
            'status' => 'draft',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('selectedStories', [(string) $s1->id, (string) $s2->id]);
        $cmp->call('bulkPublish');

        $this->assertEquals('published', $s1->fresh()->status);
        $this->assertEquals('published', $s2->fresh()->status);

        $cmp->set('selectedStories', [(string) $s1->id]);
        $cmp->call('bulkDelete');
        $this->assertSoftDeleted('stories', ['id' => $s1->id]);
    }

    public function test_csv_export_returns_streamed_response(): void
    {
        $this->actingAs($this->editor);

        Story::factory()->create([
            'headline' => 'Exportable Story',
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->call('export')->assertFileDownloaded();
    }
}
