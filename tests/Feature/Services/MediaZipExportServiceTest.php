<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Services\Media\MediaZipExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaZipExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaZipExportService $svc;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(MediaZipExportService::class);
        $this->client = Client::factory()->create();
        Storage::fake('s3');
    }

    public function test_export_throws_on_empty_assets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->export(collect(), $this->client);
    }

    public function test_export_throws_when_quota_insufficient(): void
    {
        $this->client->update(['notes' => json_encode(['tier_quotas' => ['media_quota' => 0]])]);

        $asset = MediaAsset::factory()->create(['kind' => 'photo']);

        $this->expectException(HttpException::class);
        $this->svc->export(collect([$asset]), $this->client);
    }

    public function test_export_succeeds_with_valid_entitlement(): void
    {
        $asset = MediaAsset::factory()->create([
            'kind' => 'photo',
            'storage_disk' => 's3',
            'original_path' => 'media/test.jpg',
            'size_bytes' => 1024,
        ]);

        Storage::disk('s3')->put('media/test.jpg', 'fake-content');

        $result = $this->svc->export(collect([$asset]), $this->client);

        $this->assertArrayHasKey('download_url', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertEquals(1, $result['asset_count']);
        $this->assertStringContainsString('.zip', $result['filename']);
    }
}
