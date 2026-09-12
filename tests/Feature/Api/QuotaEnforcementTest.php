<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\Download;
use App\Models\MediaAsset;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->cat = Category::factory()->create();
    }

    private function createClient(array $quotas): array
    {
        $client = Client::factory()->create([
            'status' => 'active',
            'notes' => json_encode(['tier_quotas' => $quotas]),
        ]);

        $pkg = Package::factory()->create(['status' => 'active', 'entitlement_filter' => ['languages' => ['en', 'bn'], 'category_ids' => null]]);
        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        [$key, $rawKey] = app(ApiKeyService::class)->issue($client, 'test-key', ['feed:read', 'media:read']);

        return [$client, $rawKey];
    }

    public function test_media_download_blocked_when_quota_exceeded(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/quota.jpg', 'content');

        // Client with media quota of 1
        [$client, $key] = $this->createClient(['media_quota' => 1]);

        $asset1 = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Photo 1',
            'caption' => 'Caption 1',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'p1'),
            'storage_disk' => 's3',
            'original_path' => 'originals/quota.jpg',
            'derivatives' => [],
            'uploaded_by' => $this->author->id,
        ]);

        $asset2 = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Photo 2',
            'caption' => 'Caption 2',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'p2'),
            'storage_disk' => 's3',
            'original_path' => 'originals/quota.jpg',
            'derivatives' => [],
            'uploaded_by' => $this->author->id,
        ]);

        // First download succeeds (1/1 used)
        $this->getJson("/api/v1/media/{$asset1->public_id}/download", [
            'Authorization' => 'Bearer '.$key,
        ])->assertOk();

        // Second download blocked with 429
        $resp = $this->getJson("/api/v1/media/{$asset2->public_id}/download", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(429);
        $this->assertStringContainsString('quota exceeded', strtolower($resp->json('message') ?? ''));
    }

    public function test_story_download_blocked_when_quota_exceeded(): void
    {
        // Client with story quota of 1
        [$client, $key] = $this->createClient(['stories_quota' => 1]);

        $s1 = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->cat->id,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        $s2 = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->cat->id,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        // First download succeeds
        $this->get("/api/v1/portal/story/{$s1->public_id}/download?format=json", [
            'Authorization' => 'Bearer '.$key,
        ])->assertOk();

        // Second download blocked with 429
        $resp = $this->get("/api/v1/portal/story/{$s2->public_id}/download?format=json", [
            'Authorization' => 'Bearer '.$key,
        ]);

        $resp->assertStatus(429);
    }

    public function test_unlimited_quota_allows_unrestricted_downloads(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('originals/free.jpg', 'content');

        // Client with null (unlimited) quotas
        [$client, $key] = $this->createClient(['stories_quota' => null, 'media_quota' => null]);

        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Unlimited Photo',
            'caption' => 'Caption Free',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'checksum' => hash('sha256', 'free'),
            'storage_disk' => 's3',
            'original_path' => 'originals/free.jpg',
            'derivatives' => [],
            'uploaded_by' => $this->author->id,
        ]);

        // Multiple downloads succeed
        for ($i = 0; $i < 3; $i++) {
            $this->getJson("/api/v1/media/{$asset->public_id}/download", [
                'Authorization' => 'Bearer '.$key,
            ])->assertOk();
        }
    }
}
