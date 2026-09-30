<?php

namespace Tests\Feature;

use App\Jobs\GenerateDerivatives;
use App\Livewire\Admin\PhotoManager;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Services\Media\IntakeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GenerateDerivativesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $editorRole = Role::where('name', 'Editor')->first();
        $this->user = User::factory()->create(['role_id' => $editorRole?->id]);
    }

    private function jpegBytes(int $w = 1200, int $h = 800): string
    {
        $im = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($im, 200, 40, 40);
        imagefilledrectangle($im, 0, 0, $w, $h, $color);
        ob_start();
        imagejpeg($im, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    private function makeAssetWithBytes(string $bytes, string $path = 'originals/src.jpg'): MediaAsset
    {
        Storage::fake('s3');
        Storage::disk('s3')->put($path, $bytes);

        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Derivatives test',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'storage_disk' => 's3',
            'original_path' => $path,
            'derivatives' => [],
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_generates_webp_variants_from_real_image(): void
    {
        $asset = $this->makeAssetWithBytes($this->jpegBytes(1200, 800));

        (new GenerateDerivatives($asset->id))->handle();

        $d = $asset->fresh()->derivatives;
        foreach (['thumb', 'small', 'medium', 'large'] as $variant) {
            $this->assertArrayHasKey($variant, $d);
            $this->assertTrue(Storage::disk('s3')->exists($d[$variant]['path']));
            $info = getimagesize(Storage::disk('s3')->path($d[$variant]['path']));
            $this->assertSame(IMAGETYPE_WEBP, $info[2], "{$variant} must be WebP");
        }

        $this->assertSame(400, $d['thumb']['width']);
        $this->assertSame(267, $d['thumb']['height']);
        $this->assertSame(1200, $d['large']['width'], 'no upscaling past the source width');
        $this->assertSame('originals/src.jpg', $asset->original_path);
    }

    public function test_small_source_is_not_upscaled(): void
    {
        $asset = $this->makeAssetWithBytes($this->jpegBytes(200, 100), 'originals/small.jpg');

        (new GenerateDerivatives($asset->id))->handle();

        $d = $asset->fresh()->derivatives;
        $this->assertSame(200, $d['thumb']['width']);
        $this->assertSame(200, $d['large']['width']);
    }

    public function test_non_image_bytes_leave_derivatives_empty(): void
    {
        $asset = $this->makeAssetWithBytes('not-an-image-at-all', 'originals/bogus.jpg');

        (new GenerateDerivatives($asset->id))->handle();

        $this->assertEmpty($asset->fresh()->derivatives);
    }

    public function test_handle_uploads_dispatches_derivatives_job(): void
    {
        Storage::fake('public');
        Queue::fake();

        Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->set('uploads', [UploadedFile::fake()->image('field.jpg', 600, 400)])
            ->call('handleUploads');

        Queue::assertPushed(GenerateDerivatives::class);
    }

    public function test_intake_dispatches_derivatives_job(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();

        $bytes = $this->jpegBytes(300, 200);
        $id = (string) Str::uuid();
        DB::table('upload_sessions')->insert([
            'id' => $id,
            'user_id' => $this->user->id,
            'kind' => 'photo',
            'filename' => 'intake.jpg',
            'size_bytes' => strlen($bytes),
            'offset_bytes' => 0,
            'status' => 'active',
            'meta' => json_encode(['filename' => 'intake.jpg']),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $full = Storage::disk('local')->path("uploads/{$id}");
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $bytes);
        DB::table('upload_sessions')->where('id', $id)->update(['status' => 'completed']);

        app(IntakeService::class)->ingest($id);

        Queue::assertPushed(GenerateDerivatives::class);
    }
}
