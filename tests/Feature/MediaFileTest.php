<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * COS-74: media bytes are only reachable through the signed, controller-served
 * media file route — never a raw /storage path.
 */
class MediaFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeAsset(array $overrides = []): MediaAsset
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/uploads/test.jpg', 'jpeg-bytes');

        return MediaAsset::create(array_merge([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Test asset',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 'local',
            'original_path' => 'media/uploads/test.jpg',
            'derivatives' => [],
            'uploaded_by' => User::factory()->create()->id,
        ], $overrides));
    }

    private function signedUrl(MediaAsset $asset, string $variant = 'original'): string
    {
        return URL::temporarySignedRoute('media.file', now()->addMinutes(5), [
            'asset' => $asset->public_id,
            'variant' => $variant,
        ]);
    }

    public function test_signed_media_file_url_serves_bytes(): void
    {
        $asset = $this->makeAsset();

        $resp = $this->get($this->signedUrl($asset));

        $resp->assertOk();
        $this->assertSame('image/jpeg', $resp->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $resp->headers->get('Content-Disposition'));
    }

    public function test_media_file_url_without_signature_is_403(): void
    {
        $asset = $this->makeAsset();

        $this->get('/api/v1/media/'.$asset->public_id.'/file')->assertStatus(403);
    }

    public function test_media_file_url_with_tampered_signature_is_403(): void
    {
        $asset = $this->makeAsset();

        $url = $this->signedUrl($asset).'x';

        $this->get($url)->assertStatus(403);
    }

    public function test_embargoed_asset_is_not_served_via_media_file_route(): void
    {
        $asset = $this->makeAsset(['embargo_until' => Carbon::parse(now()->addDay())]);

        $this->get($this->signedUrl($asset))->assertStatus(404);
    }

    public function test_missing_asset_returns_404(): void
    {
        $url = URL::temporarySignedRoute('media.file', now()->addMinutes(5), [
            'asset' => (string) Str::ulid(),
            'variant' => 'original',
        ]);

        $this->get($url)->assertStatus(404);
    }

    public function test_missing_variant_file_returns_404(): void
    {
        $asset = $this->makeAsset();

        $this->get($this->signedUrl($asset, 'thumb'))->assertStatus(404);
    }

    public function test_client_presigned_download_returns_signed_media_file_url(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        [, $raw] = app(ApiKeyService::class)->issue($client, 'key', ['feed:read', 'media:read']);
        $asset = $this->makeAsset();

        $resp = $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => 'Bearer '.$raw,
        ]);

        $resp->assertOk();
        $url = $resp->json('url');
        $this->assertStringContainsString('/api/v1/media/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $this->get($url)->assertOk();
    }

    public function test_staff_route_requires_auth(): void
    {
        $asset = $this->makeAsset();

        $this->get('/admin/media/'.$asset->public_id.'/file')->assertRedirect();
    }

    public function test_staff_with_media_view_permission_can_fetch_file(): void
    {
        $editorRole = \App\Models\Role::where('name', 'Editor')->first();
        $user = User::factory()->create(['role_id' => $editorRole?->id]);
        $asset = $this->makeAsset();

        $resp = $this->actingAs($user)->get('/admin/media/'.$asset->public_id.'/file');

        $resp->assertOk();
        $this->assertSame('image/jpeg', $resp->headers->get('Content-Type'));
    }

    public function test_thumb_url_returns_staff_route_for_local_disk(): void
    {
        $asset = $this->makeAsset();

        $asset->update(['derivatives' => ['thumb' => ['path' => 'media/derivatives/'.$asset->public_id.'/thumb.webp', 'width' => 400, 'height' => 300]]]);

        $url = $asset->thumbUrl();

        $this->assertStringContainsString('/admin/media/'.$asset->public_id.'/file', $url);
        $this->assertStringContainsString('variant=thumb', $url);
    }
}
