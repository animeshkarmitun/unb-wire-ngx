<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApPhotoManager;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApPhotoManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $worldCat;

    protected Category $sportsCat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CategorySeeder::class,
            PackageSeeder::class,
        ]);

        $adminRole = Role::where('name', 'Admin')->first();
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'name' => 'Nahar Khan',
            'email' => 'nahar@unbnews.org',
        ]);

        $this->worldCat = Category::where('slug', 'world')->first();
        $this->sportsCat = Category::where('slug', 'sports')->first();

        // Seed a few AP assets
        MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Zelenskyy speaks during a press conference in Kyiv',
            'caption' => 'Zelenskyy speaks during a press conference in Kyiv',
            'credit_line' => 'AP Photo',
            'source' => 'ap',
            'category_id' => $this->worldCat->id,
            'event_label' => 'world',
            'location_city' => 'Kyiv',
            'location_country' => 'Ukraine',
            'captured_at' => '2026-08-23 15:30:00',
            'en_tags' => ['world', 'Europe'],
            'width' => 3000,
            'height' => 2000,
            'mime' => 'image/jpeg',
            'size_bytes' => 450000,
            'checksum' => hash('sha256', 'zelenskyy-1'),
            'storage_disk' => 'public',
            'original_path' => 'media/ap/zelenskyy.jpg',
            'derivatives' => [
                'grad' => 'g1',
                'ap_category' => 'world',
                'sub_category' => 'Europe',
                'dim' => '3000 × 2000',
            ],
            'uploaded_by' => $this->admin->id,
            'download_count' => 42,
            'created_at' => '2026-08-23 15:30:00',
        ]);

        MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Bangladesh players celebrate a wicket at Mirpur',
            'caption' => 'Bangladesh players celebrate a wicket at Mirpur',
            'credit_line' => 'AP Photo',
            'source' => 'ap',
            'category_id' => $this->sportsCat->id,
            'event_label' => 'sports',
            'location_city' => 'Dhaka',
            'location_country' => 'Bangladesh',
            'captured_at' => '2026-08-23 18:00:00',
            'en_tags' => ['sports', 'Asia'],
            'width' => 3500,
            'height' => 2333,
            'mime' => 'image/jpeg',
            'size_bytes' => 450000,
            'checksum' => hash('sha256', 'bangladesh-cricket-1'),
            'storage_disk' => 'public',
            'original_path' => 'media/ap/cricket.jpg',
            'derivatives' => [
                'grad' => 'g3',
                'ap_category' => 'sports',
                'sub_category' => 'Asia',
                'dim' => '3500 × 2333',
            ],
            'uploaded_by' => $this->admin->id,
            'download_count' => 88,
            'created_at' => '2026-08-23 18:00:00',
        ]);
    }

    public function test_ap_photo_manager_page_loads_for_authorized_user(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.ap-photos'));

        $response->assertOk();
        $response->assertSee('AP Photo Manager');
        $response->assertSee('Sync log');
        $response->assertSee('Sync now');
        $response->assertSee('AP wire auto-syncs every 15 minutes');
        $response->assertSee('Zelenskyy speaks during a press conference in Kyiv');
        $response->assertSee('Bangladesh players celebrate a wicket at Mirpur');
    }

    public function test_ap_photo_manager_requires_authentication(): void
    {
        $response = $this->get(route('admin.ap-photos'));
        $response->assertRedirect(route('login'));
    }

    public function test_category_filter_and_chips_filter_photos(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->set('category', 'sports')
            ->assertSee('Bangladesh players celebrate a wicket at Mirpur')
            ->assertDontSee('Zelenskyy speaks during a press conference in Kyiv')
            ->call('setCategory', 'world')
            ->assertSee('Zelenskyy speaks during a press conference in Kyiv')
            ->assertDontSee('Bangladesh players celebrate a wicket at Mirpur')
            ->call('setCategory', 'all')
            ->assertSee('Zelenskyy speaks during a press conference in Kyiv')
            ->assertSee('Bangladesh players celebrate a wicket at Mirpur');
    }

    public function test_search_filter_filters_by_caption_or_title(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->set('search', 'Mirpur')
            ->assertSee('Bangladesh players celebrate a wicket at Mirpur')
            ->assertDontSee('Zelenskyy speaks')
            ->set('search', 'Kyiv')
            ->assertSee('Zelenskyy speaks during a press conference in Kyiv')
            ->assertDontSee('Bangladesh players');
    }

    public function test_sub_category_filter(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->set('subCategory', 'Europe')
            ->assertSee('Zelenskyy speaks during a press conference in Kyiv')
            ->assertDontSee('Bangladesh players celebrate a wicket at Mirpur')
            ->set('subCategory', 'Asia')
            ->assertSee('Bangladesh players celebrate a wicket at Mirpur')
            ->assertDontSee('Zelenskyy speaks during a press conference in Kyiv');
    }

    public function test_reset_filters_clears_all_filters(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->set('search', 'Kyiv')
            ->set('category', 'world')
            ->set('subCategory', 'Europe')
            ->set('tag', 'war')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('category', 'all')
            ->assertSet('subCategory', 'all')
            ->assertSet('tag', '')
            ->assertSet('perPage', 11);
    }

    public function test_load_more_increments_per_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->assertSet('perPage', 11)
            ->call('loadMore')
            ->assertSet('perPage', 16);
    }

    public function test_lightbox_modal_opens_and_closes(): void
    {
        $this->actingAs($this->admin);
        $asset = MediaAsset::where('source', 'ap')->first();

        Livewire::test(ApPhotoManager::class)
            ->assertSet('selectedId', null)
            ->call('openLightbox', $asset->id)
            ->assertSet('selectedId', $asset->id)
            ->assertSee('Credit')
            ->assertSee('AP Photo')
            ->assertSee('Dimensions')
            ->assertSee('Downloads')
            ->assertSee('by clients')
            ->call('closeLightbox')
            ->assertSet('selectedId', null);
    }

    public function test_attach_to_story_updates_asset_and_links_draft(): void
    {
        $this->actingAs($this->admin);
        $asset = MediaAsset::where('source', 'ap')->first();

        $story = Story::factory()->create([
            'created_by' => $this->admin->id,
            'owner_id' => $this->admin->id,
            'status' => 'draft',
            'category_id' => $this->worldCat->id,
        ]);

        Livewire::test(ApPhotoManager::class)
            ->call('attachToStory', $asset->id, $story->id)
            ->assertDispatched('toast')
            ->assertSee('✓ Attached');

        $this->assertDatabaseHas('story_media', [
            'story_id' => $story->id,
            'asset_id' => $asset->id,
            'role' => 'featured',
        ]);
    }

    public function test_download_original_increments_download_count(): void
    {
        $this->actingAs($this->admin);
        $asset = MediaAsset::where('source', 'ap')->first();
        $initialCount = $asset->download_count;

        Livewire::test(ApPhotoManager::class)
            ->call('downloadOriginal', $asset->id)
            ->assertDispatched('toast');

        $this->assertEquals($initialCount + 1, $asset->fresh()->download_count);
    }

    public function test_sync_now_dispatches_toast_and_updates_sync_state(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->call('syncNow')
            ->assertDispatched('toast')
            ->assertSet('todaySyncCount', 226);
    }

    public function test_toggle_sync_log_shows_and_hides_modal(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ApPhotoManager::class)
            ->assertSet('showSyncLog', false)
            ->call('toggleSyncLog')
            ->assertSet('showSyncLog', true)
            ->assertSee('AP Wire Sync Log')
            ->assertSee('AP Associated Press Media API v1')
            ->call('toggleSyncLog')
            ->assertSet('showSyncLog', false);
    }
}
