<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivatizeMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    private function asset(string $path, array $derivatives = []): MediaAsset
    {
        Storage::disk('public')->put($path, 'bytes-'.Str::random(4));

        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 't',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 'public',
            'original_path' => $path,
            'derivatives' => $derivatives,
            'uploaded_by' => User::factory()->create()->id,
        ]);
    }

    public function test_moves_public_disk_assets_and_rows_to_local_disk(): void
    {
        $asset = $this->asset('media/ap/photo.jpg', [
            'thumb' => ['path' => 'media/derivatives/d1/thumb.webp', 'width' => 400, 'height' => 300],
        ]);
        Storage::disk('public')->put('media/derivatives/d1/thumb.webp', 'thumb-bytes');

        $this->artisan('media:privatize')->assertSuccessful();

        $this->assertSame('local', $asset->fresh()->storage_disk);
        $this->assertTrue(Storage::disk('local')->exists('media/ap/photo.jpg'));
        $this->assertTrue(Storage::disk('local')->exists('media/derivatives/d1/thumb.webp'));
        $this->assertFalse(Storage::disk('public')->exists('media/ap/photo.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('media/derivatives/d1/thumb.webp'));
    }

    public function test_sweeps_orphan_files_under_media_on_public_disk(): void
    {
        Storage::disk('public')->put('media/ap/manual-drop.jpg', 'orphan-bytes');

        $this->artisan('media:privatize')->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('media/ap/manual-drop.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('media/ap/manual-drop.jpg'));
    }

    public function test_dry_run_moves_nothing(): void
    {
        $asset = $this->asset('media/ap/keep.jpg');

        $this->artisan('media:privatize', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('public', $asset->fresh()->storage_disk);
        $this->assertTrue(Storage::disk('public')->exists('media/ap/keep.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('media/ap/keep.jpg'));
    }

    public function test_touches_only_public_disk_rows(): void
    {
        $local = $this->asset('media/ap/already.jpg');
        $local->forceFill(['storage_disk' => 'local'])->saveQuietly();

        $this->artisan('media:privatize')->assertSuccessful();

        $this->assertSame('local', $local->fresh()->storage_disk);
    }
}
