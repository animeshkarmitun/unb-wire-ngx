<?php

namespace Tests\Feature\Api;

use App\Jobs\ExportMediaZipJob;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\MediaAsset;
use App\Models\Package;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaZipExportTest extends TestCase
{
    use RefreshDatabase;

    private User $uploader;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);
        Storage::fake('s3');

        $this->uploader = User::factory()->create();
        $this->category = Category::factory()->create();
    }

    private function createClient(array $packageFilter = ['languages' => ['en'], 'media_kinds' => ['photo']], array $quotas = []): array
    {
        $client = Client::factory()->create([
            'status' => 'active',
            'notes' => json_encode(['tier_quotas' => $quotas]),
        ]);

        $pkg = Package::factory()->create([
            'status' => 'active',
            'entitlement_filter' => $packageFilter,
        ]);

        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        [$key, $rawKey] = app(ApiKeyService::class)->issue($client, 'export-key', ['media:read', 'feed:read']);

        return [$client, $rawKey];
    }

    private function createAsset(string $kind = 'photo', string $filename = 'test.jpg'): MediaAsset
    {
        Storage::disk('s3')->put("originals/{$filename}", "dummy-image-data-{$filename}");

        return MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => $kind,
            'status' => 'library',
            'title' => "Asset {$filename}",
            'caption' => "Caption for {$filename}",
            'credit_line' => 'UNB',
            'mime' => $kind === 'video' ? 'video/mp4' : 'image/jpeg',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', $filename),
            'storage_disk' => 's3',
            'original_path' => "originals/{$filename}",
            'derivatives' => [],
            'uploaded_by' => $this->uploader->id,
            'download_count' => 0,
        ]);
    }

    public function test_export_requires_authentication(): void
    {
        $this->postJson('/api/v1/media/export', [
            'asset_ids' => ['01M2TEST'],
        ])->assertStatus(401);
    }

    public function test_export_validates_asset_ids_required_and_max_50(): void
    {
        [$client, $key] = $this->createClient();

        $resp = $this->postJson('/api/v1/media/export', [], [
            'Authorization' => 'Bearer '.$key,
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['asset_ids']);

        // Exceeding 50 items
        $tooMany = array_fill(0, 51, '01M2TEST');
        $resp2 = $this->postJson('/api/v1/media/export', ['asset_ids' => $tooMany], [
            'Authorization' => 'Bearer '.$key,
        ]);
        $resp2->assertStatus(422);
        $resp2->assertJsonValidationErrors(['asset_ids']);
    }

    public function test_synchronous_export_creates_zip_and_ledgers_downloads(): void
    {
        [$client, $key] = $this->createClient();

        $a1 = $this->createAsset('photo', 'photo1.jpg');
        $a2 = $this->createAsset('photo', 'photo2.jpg');

        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$a1->public_id, $a2->public_id],
            'variant' => 'original',
        ], [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['download_url', 'expires_in', 'filename', 'asset_count', 'size_bytes']);
        $this->assertEquals(2, $resp->json('asset_count'));
        $this->assertNotEmpty($resp->json('download_url'));

        // Check download ledger for both assets
        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'media',
            'item_id' => $a1->id,
        ]);
        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'media',
            'item_id' => $a2->id,
        ]);

        $this->assertEquals(1, $a1->fresh()->download_count);
        $this->assertEquals(1, $a2->fresh()->download_count);
    }

    public function test_async_export_queues_job(): void
    {
        Queue::fake();

        [$client, $key] = $this->createClient();
        $a1 = $this->createAsset('photo', 'photo1.jpg');

        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$a1->public_id],
            'async' => true,
        ], [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(202);
        $this->assertEquals('Export job queued', $resp->json('message'));

        Queue::assertPushed(ExportMediaZipJob::class, function ($job) use ($a1, $client) {
            return $job->client->id === $client->id && in_array($a1->public_id, $job->assetIds);
        });
    }

    public function test_export_blocked_when_media_kind_not_entitled(): void
    {
        // Client only entitled to photos
        [$client, $key] = $this->createClient(['languages' => ['en'], 'media_kinds' => ['photo']]);

        $video = $this->createAsset('video', 'clip.mp4');

        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$video->public_id],
        ], [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(403);
    }

    public function test_export_blocked_when_quota_insufficient_for_batch(): void
    {
        // Client with media quota of 2
        [$client, $key] = $this->createClient(['languages' => ['en'], 'media_kinds' => ['photo']], ['media_quota' => 2]);

        $a1 = $this->createAsset('photo', 'photo1.jpg');
        $a2 = $this->createAsset('photo', 'photo2.jpg');
        $a3 = $this->createAsset('photo', 'photo3.jpg');

        // Requesting 3 items when quota only has 2
        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$a1->public_id, $a2->public_id, $a3->public_id],
        ], [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(429);
    }

    public function test_portal_user_can_export_zip_with_client_user_id_ledging(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::factory()->create(['status' => 'active', 'entitlement_filter' => ['languages' => ['en'], 'media_kinds' => ['photo']]]);
        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        $user = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $token = $user->createToken('portal')->plainTextToken;

        $a1 = $this->createAsset('photo', 'portal1.jpg');

        $resp = $this->postJson('/api/v1/media/export', [
            'asset_ids' => [$a1->public_id],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resp->assertOk();
        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'client_user_id' => $user->id,
            'item_type' => 'media',
            'item_id' => $a1->id,
        ]);
    }
}
