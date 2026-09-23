<?php

namespace Tests\Feature;

use App\Livewire\Admin\PhotoManager;
use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\Delivery\Formats\JsonUnbV1Formatter;
use App\Services\Delivery\WebhookPayloadBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MediaEmbargoTest extends TestCase
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

    private function makeAsset(?Carbon $embargoUntil = null, string $path = 'originals/embargo.jpg'): MediaAsset
    {
        Storage::fake('s3');
        Storage::disk('s3')->put($path, 'fake-content');

        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Embargo asset',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 's3',
            'original_path' => $path,
            'derivatives' => [],
            'uploaded_by' => $this->user->id,
            'download_count' => 0,
            'embargo_until' => $embargoUntil,
        ]);
    }

    private function clientKey(): string
    {
        $client = Client::factory()->create(['status' => 'active']);
        [, $raw] = app(ApiKeyService::class)->issue($client, 'embargo-key', ['media:read']);

        return $raw;
    }

    public function test_client_download_of_embargoed_asset_returns_404(): void
    {
        $asset = $this->makeAsset(now()->addDay());
        $raw = $this->clientKey();

        $resp = $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => "Bearer {$raw}",
        ]);

        $resp->assertStatus(404);
    }

    public function test_expired_embargo_asset_is_visible_again(): void
    {
        $asset = $this->makeAsset(now()->subHour());
        $raw = $this->clientKey();

        $resp = $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => "Bearer {$raw}",
        ]);

        $resp->assertOk();
    }

    public function test_staff_download_of_embargoed_asset_still_works(): void
    {
        $asset = $this->makeAsset(now()->addDay());

        $resp = $this->actingAs($this->user)->getJson("/api/media/{$asset->public_id}/presigned");

        $resp->assertOk();
    }

    public function test_zip_export_excludes_embargoed_assets(): void
    {
        $embargoed = $this->makeAsset(now()->addDay(), 'originals/e.jpg');
        $visible = $this->makeAsset(null, 'originals/v.jpg');
        $raw = $this->clientKey();

        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$embargoed->public_id, $visible->public_id],
            'async' => false,
        ], [
            'Authorization' => "Bearer {$raw}",
        ]);

        $resp->assertOk();
        $this->assertSame(1, $resp->json('asset_count'), 'GOT: '.json_encode($resp->json()));
    }

    public function test_webhook_payload_excludes_embargoed_media(): void
    {
        $embargoed = $this->makeAsset(now()->addDay(), 'originals/e.jpg');
        $visible = $this->makeAsset(null, 'originals/v.jpg');
        $story = Story::factory()->create(['status' => 'published']);
        $story->media()->attach($embargoed->id, ['role' => 'inline']);
        $story->media()->attach($visible->id, ['role' => 'inline']);

        $payload = app(WebhookPayloadBuilder::class)->build($story->fresh(), 'story.published');

        $this->assertCount(1, $payload['data']['media']);
        $this->assertSame($visible->public_id, $payload['data']['media'][0]['public_id']);
    }

    public function test_wire_json_formatter_excludes_embargoed_media(): void
    {
        $embargoed = $this->makeAsset(now()->addDay(), 'originals/e.jpg');
        $story = Story::factory()->create(['status' => 'published']);
        $story->media()->attach($embargoed->id, ['role' => 'inline']);

        $out = app(JsonUnbV1Formatter::class)->format($story->fresh(['media']));
        $data = json_decode($out->content, true);

        $this->assertSame([], $data['media']);
        $this->assertFalse($data['has_video']);
    }

    public function test_inspector_save_sets_and_clears_embargo(): void
    {
        $asset = $this->makeAsset(null);

        $component = Livewire::actingAs($this->user)
            ->test(PhotoManager::class)
            ->call('selectAsset', $asset->id)
            ->set('inspEmbargo', '2027-01-15T10:30')
            ->call('saveAssetMetadata');

        $this->assertNotNull($asset->fresh()->embargo_until);

        $component->set('inspEmbargo', '')
            ->call('saveAssetMetadata');
        $this->assertNull($asset->fresh()->embargo_until);
    }
}
