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

    public function test_happy_path_returns_url_with_expiry_query_for_public_disk(): void
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

        $this->assertStringContainsString('media/test.jpg', $url);
        // Laravel public disk returns ?expiration=…; AWS S3 returns ?X-Amz-Expires. Accept either as proof of TTL.
        $this->assertMatchesRegularExpression('/(expires|expiration|X-Amz-Expires)=/', $url);
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
