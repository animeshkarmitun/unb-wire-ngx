<?php

namespace Tests\Feature;

use App\Jobs\GenerateDerivatives;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\Media\PresignedUrlService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_presigned_url_service_generates_url(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/test.jpg', 'fake-content');
        $user = User::factory()->create();
        $a = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Test photo',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 12345,
            'checksum' => hash('sha256', 'test'),
            'storage_disk' => 's3',
            'original_path' => 'originals/test.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);
        $svc = app(PresignedUrlService::class);
        $url = $svc->forAsset($a, 'original', 5);
        $this->assertNotEmpty($url);
    }

    public function test_generate_derivatives_job(): void
    {
        $user = User::factory()->create();
        $a = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'T',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'x'),
            'storage_disk' => 's3',
            'original_path' => 'originals/x.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);
        (new GenerateDerivatives($a->id))->handle();
        $this->assertNotEmpty($a->refresh()->derivatives['thumb']);
    }

    public function test_tus_create_and_patch(): void
    {
        $editorRole = Role::where('name', 'Editor')->first();
        $user = User::factory()->create(['role_id' => $editorRole->id]);
        $this->actingAs($user);
        $resp = $this->postJson('/api/uploads', ['upload_length' => 11, 'filename' => 'test.bin']);
        $resp->assertCreated();
        $loc = $resp->headers->get('Location');
        $id = basename($loc);
        $patch = $this->call('PATCH', '/api/uploads/'.$id, [], [], [], ['HTTP_Upload-Offset' => '0', 'CONTENT_TYPE' => 'application/offset+octet-stream'], 'hello world');
        $patch->assertStatus(204);
        $this->assertDatabaseHas('upload_sessions', ['id' => $id, 'status' => 'completed']);
    }

    public function test_client_presigned_download_records_download_and_returns_url(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/client-dl.jpg', 'fake-content');

        $user = User::factory()->create();
        $client = Client::factory()->create(['status' => 'active']);
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Client Download Test',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 54321,
            'checksum' => hash('sha256', 'client-dl'),
            'storage_disk' => 's3',
            'original_path' => 'originals/client-dl.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
            'download_count' => 0,
        ]);

        [$key, $raw] = app(ApiKeyService::class)->issue($client, 'client-key', ['media:read']);

        $resp = $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => "Bearer {$raw}",
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['url', 'expires_in'])
            ->assertJson(['expires_in' => 300]);

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_type' => 'media',
            'item_id' => $asset->id,
            'format' => 'original',
            'size_bytes' => 54321,
        ]);

        $this->assertEquals(1, $asset->fresh()->download_count);
    }

    public function test_client_presigned_download_works_with_numeric_id(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/client-numeric.jpg', 'fake-content');

        $user = User::factory()->create();
        $client = Client::factory()->create(['status' => 'active']);
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Client Numeric Download Test',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 12000,
            'checksum' => hash('sha256', 'numeric-dl'),
            'storage_disk' => 's3',
            'original_path' => 'originals/client-numeric.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
            'download_count' => 0,
        ]);

        [$key, $raw] = app(ApiKeyService::class)->issue($client, 'client-key', ['media:read']);

        $resp = $this->getJson("/api/v1/media/{$asset->id}/download", [
            'Authorization' => "Bearer {$raw}",
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['url', 'expires_in']);

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'item_id' => $asset->id,
        ]);
        $this->assertEquals(1, $asset->fresh()->download_count);
    }

    public function test_client_presigned_download_scope_and_auth_enforced(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        [$key, $raw] = app(ApiKeyService::class)->issue($client, 'feed-only', ['feed:read']);

        $user = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Scope test',
            'caption' => 'C',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'scope'),
            'storage_disk' => 's3',
            'original_path' => 'originals/scope.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
        ]);

        // Missing API key -> 401
        $this->getJson("/api/v1/media/{$asset->public_id}/download")->assertStatus(401);

        // Wrong scope (feed:read without media:read) -> 403
        $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => "Bearer {$raw}",
        ])->assertStatus(403);
    }

    public function test_staff_presigned_download_succeeds_without_client_ledgering(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/staff-dl.jpg', 'fake-content');

        $editorRole = Role::where('name', 'Editor')->first();
        $user = User::factory()->create(['role_id' => $editorRole->id]);

        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Staff Download Test',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 3000,
            'checksum' => hash('sha256', 'staff-dl'),
            'storage_disk' => 's3',
            'original_path' => 'originals/staff-dl.jpg',
            'derivatives' => [],
            'uploaded_by' => $user->id,
            'download_count' => 0,
        ]);

        $resp = $this->actingAs($user)->getJson("/api/media/{$asset->public_id}/presigned");
        $resp->assertOk()
            ->assertJsonStructure(['url', 'expires_in'])
            ->assertJson(['expires_in' => 300]);

        $this->assertDatabaseMissing('downloads', [
            'item_id' => $asset->id,
        ]);
        $this->assertEquals(0, $asset->fresh()->download_count);
    }

    public function test_portal_user_can_download_media_via_sanctum_token(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/portal-dl.jpg', 'fake-content');

        $client = Client::factory()->create(['status' => 'active']);
        $clientUser = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $token = $clientUser->createToken('portal')->plainTextToken;

        $uploader = User::factory()->create();
        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Portal User Download Test',
            'caption' => 'Caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 5000,
            'checksum' => hash('sha256', 'portal-dl'),
            'storage_disk' => 's3',
            'original_path' => 'originals/portal-dl.jpg',
            'derivatives' => [],
            'uploaded_by' => $uploader->id,
            'download_count' => 0,
        ]);

        $resp = $this->getJson("/api/v1/media/{$asset->public_id}/download", [
            'Authorization' => "Bearer {$token}",
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['url', 'expires_in'])
            ->assertJson(['expires_in' => 300]);

        $this->assertDatabaseHas('downloads', [
            'client_id' => $client->id,
            'client_user_id' => $clientUser->id,
            'item_type' => 'media',
            'item_id' => $asset->id,
        ]);
        $this->assertEquals(1, $asset->fresh()->download_count);
    }
}
