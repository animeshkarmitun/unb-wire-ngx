<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Livewire\Admin\PhotoManager;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WebpUploadConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $editorRole = Role::where('name', 'Editor')->first();
        $this->editor = User::factory()->create(['role_id' => $editorRole?->id]);
        $this->cat = Category::create([
            'name_en' => 'National',
            'name_bn' => 'জাতীয়',
            'slug' => 'national',
        ]);
    }

    public function test_photo_library_upload_stores_both_original_and_full_res_webp_version(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->editor)
            ->test(PhotoManager::class)
            ->set('uploads', [UploadedFile::fake()->image('summit-photo.jpg', 900, 600)])
            ->call('handleUploads');

        $asset = MediaAsset::latest('id')->first();
        $this->assertNotNull($asset);
        $this->assertSame('local', $asset->storage_disk);
        $this->assertSame('image/jpeg', $asset->mime);

        // Original JPEG is preserved
        $this->assertTrue(Storage::disk('local')->exists($asset->original_path));
        $origInfo = getimagesize(Storage::disk('local')->path($asset->original_path));
        $this->assertSame(IMAGETYPE_JPEG, $origInfo[2]);

        // Full-resolution WebP counterpart is also generated and stored
        $this->assertArrayHasKey('webp', $asset->derivatives ?? []);
        $webpPath = $asset->derivatives['webp']['path'];
        $this->assertTrue(Storage::disk('local')->exists($webpPath));
        $webpInfo = getimagesize(Storage::disk('local')->path($webpPath));
        $this->assertSame(IMAGETYPE_WEBP, $webpInfo[2]);
        $this->assertSame(900, $webpInfo[0]);
        $this->assertSame(600, $webpInfo[1]);

        // Both Original and WebP versions are streamable via staff media route
        $origResp = $this->actingAs($this->editor)->get($asset->staffUrl('original'));
        $origResp->assertOk();

        $webpResp = $this->actingAs($this->editor)->get($asset->staffUrl('webp'));
        $webpResp->assertOk();
        $this->assertSame('image/webp', $webpResp->headers->get('Content-Type'));
    }

    public function test_story_accompanying_image_converts_to_webp_by_default(): void
    {
        Storage::fake('local');

        $cmp = Livewire::actingAs($this->editor)
            ->test(AddNews::class)
            ->set('headline', 'Story with WebP conversion')
            ->set('brief', 'Brief')
            ->set('categoryId', (string) $this->cat->id)
            ->set('uploadMode', 'featured')
            ->set('mediaUpload', UploadedFile::fake()->image('lead-photo.jpg', 800, 500));

        $featuredId = $cmp->get('featuredMediaId');
        $this->assertNotNull($featuredId);

        $asset = MediaAsset::findOrFail($featuredId);
        $this->assertSame('image/webp', $asset->mime);
        $this->assertStringEndsWith('.webp', $asset->original_path);
        $this->assertTrue(Storage::disk('local')->exists($asset->original_path));

        $info = getimagesize(Storage::disk('local')->path($asset->original_path));
        $this->assertSame(IMAGETYPE_WEBP, $info[2]);
        $this->assertSame(800, $info[0]);
        $this->assertSame(500, $info[1]);
    }

    public function test_story_accompanying_image_preserves_original_format_when_keep_original_enabled(): void
    {
        Storage::fake('local');

        $cmp = Livewire::actingAs($this->editor)
            ->test(AddNews::class)
            ->set('headline', 'Story keeping original JPEG')
            ->set('brief', 'Brief')
            ->set('categoryId', (string) $this->cat->id)
            ->set('keepOriginalFormat', true)
            ->set('uploadMode', 'featured')
            ->set('mediaUpload', UploadedFile::fake()->image('keep-jpeg.jpg', 720, 480));

        $featuredId = $cmp->get('featuredMediaId');
        $this->assertNotNull($featuredId);

        $asset = MediaAsset::findOrFail($featuredId);
        $this->assertSame('image/jpeg', $asset->mime);
        $this->assertStringNotContainsString('.webp', $asset->original_path);
        $this->assertTrue(Storage::disk('local')->exists($asset->original_path));

        $info = getimagesize(Storage::disk('local')->path($asset->original_path));
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }
}
