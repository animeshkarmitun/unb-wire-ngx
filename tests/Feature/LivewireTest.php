<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Livewire\Admin\NewsList;
use App\Livewire\Admin\PhotoManager;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;
    private User $uploader;
    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $editorRole = Role::where('name','Editor')->first();
        $uploaderRole = Role::where('name','Uploader-English')->first();
        $this->editor = User::factory()->create(['role_id'=>$editorRole->id]);
        $this->uploader = User::factory()->create(['role_id'=>$uploaderRole->id]);
        $this->cat = Category::first() ?? Category::factory()->create();
    }

    public function test_addnews_autosave_creates_draft_and_version(): void
    {
        $this->actingAs($this->editor);
        $cmp = Livewire::test(AddNews::class);
        $cmp->set('headline','Hello world')->set('brief','A brief that is long enough')->set('categoryId',(string)$this->cat->id)->set('bodyHtml','<p>Body content long enough for validation</p>');
        $cmp->call('autosave');
        $this->assertDatabaseHas('stories', ['headline'=>'Hello world','status'=>'draft']);
        $this->assertDatabaseHas('story_versions', ['version'=>1]);
        $this->assertNotNull($cmp->get('storyId'));
    }

    public function test_addnews_sanitizes_xss_on_autosave(): void
    {
        $this->actingAs($this->editor);
        $cmp = Livewire::test(AddNews::class);
        $cmp->set('headline','XSS test')->set('brief','brief')->set('categoryId',(string)$this->cat->id)->set('bodyHtml','<p>hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>');
        $cmp->call('autosave');
        $story = Story::first();
        $this->assertStringNotContainsString('<script', $story->body_html);
        $this->assertStringNotContainsString('javascript:', $story->body_html);
    }

    public function test_addnews_embargo_dhaka_to_utc(): void
    {
        $this->actingAs($this->editor);
        $cmp = Livewire::test(AddNews::class);
        $cmp->set('headline','Embargo')->set('brief','brief')->set('categoryId',(string)$this->cat->id)->set('bodyHtml','<p>body long enough here ok</p>')->set('embargoUntil','2026-08-28T15:00');
        $cmp->call('autosave');
        $story = Story::first();
        $this->assertNotNull($story->embargo_until);
        $expected = \Carbon\Carbon::parse('2026-08-28T15:00', 'Asia/Dhaka')->utc()->toDateTimeString();
        $this->assertEquals($expected, $story->embargo_until->toDateTimeString());
    }

    public function test_addnews_publish_requires_rbac(): void
    {
        $this->actingAs($this->uploader);
        $story = Story::factory()->create(['status'=>'draft','owner_id'=>$this->uploader->id,'created_by'=>$this->uploader->id,'category_id'=>$this->cat->id]);
        $cmp = Livewire::test(AddNews::class, ['id'=>$story->id]);
        $cmp->call('publish');
        $this->assertEquals('draft', $story->refresh()->status);
    }

    public function test_addnews_send_to_review_transitions(): void
    {
        $this->actingAs($this->editor);
        $cmp = Livewire::test(AddNews::class);
        $cmp->set('headline','Review me')->set('brief','Need review brief')->set('categoryId',(string)$this->cat->id)->set('bodyHtml','<p>Body long enough for review validation</p>');
        $cmp->call('autosave');
        $cmp->call('sendToReview');
        $story = Story::first();
        $this->assertEquals('in_review', $story->status);
        $this->assertDatabaseHas('story_events', ['action'=>'in_review']);
    }

    public function test_newslist_filters_and_pagination(): void
    {
        $this->actingAs($this->editor);
        Story::factory()->create(['headline'=>'Alpha story','status'=>'published','language'=>'en','category_id'=>$this->cat->id,'owner_id'=>$this->editor->id,'created_by'=>$this->editor->id]);
        Story::factory()->create(['headline'=>'Beta draft','status'=>'draft','language'=>'en','category_id'=>$this->cat->id,'owner_id'=>$this->editor->id,'created_by'=>$this->editor->id]);
        $cmp = Livewire::test(NewsList::class, ['language'=>'en']);
        $cmp->assertViewHas('stories');
        $cmp->set('status','published');
        $cmp->assertViewHas('stories', fn($p)=> $p->total()===1);
        $cmp->set('status','all')->set('search','Alpha');
        $cmp->assertViewHas('stories', fn($p)=> $p->total()===1);
        $cmp->call('select', $cmp->viewData('stories')->first()->id);
        $this->assertNotNull($cmp->get('selectedId'));
        $cmp->call('closeDrawer');
        $this->assertNull($cmp->get('selectedId'));
    }

    public function test_photomanager_approve_and_search(): void
    {
        $this->actingAs($this->editor);
        $a1 = MediaAsset::create(['public_id'=>(string)\Illuminate\Support\Str::ulid(),'kind'=>'photo','status'=>'field','title'=>'Sunset photo','caption'=>'cap','credit_line'=>'UNB','mime'=>'image/jpeg','size_bytes'=>100,'checksum'=>hash('sha256','a'),'storage_disk'=>'s3','original_path'=>'a.jpg','uploaded_by'=>$this->editor->id]);
        $a2 = MediaAsset::create(['public_id'=>(string)\Illuminate\Support\Str::ulid(),'kind'=>'photo','status'=>'library','title'=>'City library','caption'=>'cap','credit_line'=>'UNB','mime'=>'image/jpeg','size_bytes'=>100,'checksum'=>hash('sha256','b'),'storage_disk'=>'s3','original_path'=>'b.jpg','uploaded_by'=>$this->editor->id]);
        $cmp = Livewire::test(PhotoManager::class);
        $cmp->set('search','Sunset');
        $cmp->assertViewHas('assets');
        $cmp->set('search','');
        $cmp->call('approve', $a1->id);
        $this->assertEquals('library', $a1->refresh()->status);
        $cmp->call('toggleSelect', $a2->id);
        $this->assertContains($a2->id, $cmp->get('selectedIds'));
        $cmp->call('clearSelection');
        $this->assertEmpty($cmp->get('selectedIds'));
    }
}
