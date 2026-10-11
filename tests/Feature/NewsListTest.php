<?php

namespace Tests\Feature;

use App\Livewire\Admin\NewsList;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\StoryNote;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $this->seed(RoleSeeder::class);

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
            'kind' => 'note',
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
            'action' => 'handover',
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

    // ─── Advanced Filters (M8-NEWS-003) ────────────────────────

    public function test_advanced_filter_by_author_narrows_results(): void
    {
        $this->actingAs($this->editor);

        Story::factory()->create([
            'headline' => 'Owned by editor',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);
        Story::factory()->create([
            'headline' => 'Owned by shohel',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->otherUser->id,
            'created_by' => $this->otherUser->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('authorId', (string) $this->editor->id);
        $cmp->assertSee('Owned by editor');
        $cmp->assertDontSee('Owned by shohel');
    }

    public function test_advanced_filter_by_tags_narrows_results(): void
    {
        $this->actingAs($this->editor);

        $economy = Tag::factory()->create(['name' => 'economy']);

        $tagged = Story::factory()->create([
            'headline' => 'Tagged story',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);
        $tagged->tags()->attach($economy->id);

        Story::factory()->create([
            'headline' => 'Untagged story',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->call('toggleTag', $economy->id);
        $cmp->assertSee('Tagged story');
        $cmp->assertDontSee('Untagged story');

        $cmp->call('toggleTag', $economy->id);
        $cmp->assertSee('Untagged story');
    }

    public function test_advanced_filter_image_presence(): void
    {
        $this->actingAs($this->editor);

        $with = Story::factory()->create([
            'headline' => 'With media',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);
        $asset = MediaAsset::factory()->create();
        DB::table('story_media')->insert([
            'story_id' => $with->id, 'asset_id' => $asset->id, 'role' => 'featured',
            'sort_order' => 0, 'caption_override' => 'President at memorial',
        ]);

        Story::factory()->create([
            'headline' => 'No media here',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('hasImage', 'with');
        $cmp->assertSee('With media');
        $cmp->assertDontSee('No media here');

        $cmp->set('hasImage', 'without');
        $cmp->assertSee('No media here');
        $cmp->assertDontSee('With media');
    }

    public function test_advanced_filter_today_computes_dhaka_day_window(): void
    {
        $this->actingAs($this->editor);

        Carbon::setTestNow(Carbon::parse('2026-10-11 14:00', 'Asia/Dhaka'));

        Story::factory()->create([
            'headline' => 'Published this morning',
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'published_at' => Carbon::parse('2026-10-11 09:00', 'Asia/Dhaka'),
        ]);
        Story::factory()->create([
            'headline' => 'Published last night',
            'language' => 'en',
            'status' => 'published',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'published_at' => Carbon::parse('2026-10-10 23:30', 'Asia/Dhaka'),
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('dateField', 'published');
        $cmp->set('dateMode', 'today');
        $cmp->assertSee('Published this morning');
        $cmp->assertDontSee('Published last night');

        // Picking Today/Range without touching the field select defaults to Published
        $cmp->set('dateField', 'any');
        $cmp->set('dateMode', 'any');
        $cmp->set('dateMode', 'today');
        $cmp->assertSet('dateField', 'published');
        $cmp->assertSee('Published this morning');
        $cmp->assertDontSee('Published last night');

        Carbon::setTestNow();
    }

    public function test_search_scope_caption_finds_story_by_media_caption(): void
    {
        $this->actingAs($this->editor);

        $with = Story::factory()->create([
            'headline' => 'Caption boat story',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);
        $asset = MediaAsset::factory()->create(['caption' => 'Riverside residences on the Bishkhali']);
        DB::table('story_media')->insert([
            'story_id' => $with->id, 'asset_id' => $asset->id, 'role' => 'featured',
            'sort_order' => 0, 'caption_override' => null,
        ]);

        Story::factory()->create([
            'headline' => 'Other unrelated',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('searchScope', 'caption');
        $cmp->set('search', 'Bishkhali');
        $cmp->assertSee('Caption boat story');
        $cmp->assertDontSee('Other unrelated');
    }

    public function test_search_scope_body_falls_back_to_sql_when_engine_unavailable(): void
    {
        $this->actingAs($this->editor);

        Story::factory()->create([
            'headline' => 'Headline one',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'brief' => 'plain brief',
            'body_text' => 'The needle phrase lives deep in the body copy.',
        ]);
        Story::factory()->create([
            'headline' => 'Headline two',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
            'brief' => 'another brief',
            'body_text' => 'Nothing relevant in here.',
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('searchScope', 'body');
        $cmp->set('search', 'needle phrase');
        $cmp->assertSee('Headline one');
        $cmp->assertDontSee('Headline two');
    }

    public function test_select_all_and_export_honor_advanced_filters(): void
    {
        $this->actingAs($this->editor);

        $mine = Story::factory()->create([
            'headline' => 'Mine for select all',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->editor->id,
            'created_by' => $this->editor->id,
        ]);
        Story::factory()->create([
            'headline' => 'Not mine for select all',
            'language' => 'en',
            'category_id' => $this->category->id,
            'owner_id' => $this->otherUser->id,
            'created_by' => $this->otherUser->id,
        ]);

        $cmp = Livewire::test(NewsList::class, ['language' => 'en']);
        $cmp->set('authorId', (string) $this->editor->id);
        $cmp->set('selectAll', true);

        $this->assertSame([(string) $mine->id], $cmp->get('selectedStories'));

        // CSV export still streams with the same filter composition applied
        $cmp->call('export')->assertFileDownloaded();
    }
}
