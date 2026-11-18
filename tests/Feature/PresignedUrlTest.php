<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\PresignedUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PresignedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_disk_falls_back_to_signed_media_file_route(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/test.jpg', 'jpeg-bytes');

        $user = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 't',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'x'),
            'storage_disk' => 'public',
            'original_path' => 'media/test.jpg',
            'uploaded_by' => $user->id,
        ]);

        $url = app(PresignedUrlService::class)->forAsset($asset, 'original', 5);

        // COS-74: local disks are never web-served — the fallback must be the
        // signed, controller-served media file route, not a raw /storage link.
        $this->assertStringContainsString('/api/v1/media/'.$asset->public_id.'/file', $url);
        $this->assertStringContainsString('variant=original', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    public function test_no_config_throws_500_via_http_exception(): void
    {
        // Path that is not local/public: s3 disk without config.
        config([
            'filesystems.disks.s3' => ['driver' => 's3', 'key' => null, 'secret' => null, 'region' => null, 'bucket' => null, 'url' => null],
        ]);

        $user = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 't',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'x'),
            'storage_disk' => 's3',
            'original_path' => 'media/missing.jpg',
            'uploaded_by' => $user->id,
        ]);

        try {
            app(PresignedUrlService::class)->forAsset($asset);
            $this->fail('expected abort(500)');
        } catch (HttpException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
    }
}
