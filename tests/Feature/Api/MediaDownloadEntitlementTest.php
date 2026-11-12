<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ClientUser;
use App\Models\MediaAsset;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaDownloadEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->category = Category::factory()->create();
        Storage::fake('s3');
    }

    public function test_text_only_client_cannot_download_media_but_can_read_stories(): void
    {
        Storage::disk('s3')->put('originals/test-photo.jpg', 'fake-image-content');

        // Create text-only package (no photos)
        $textPkg = Package::factory()->create([
            'name' => 'Text Only Wire',
            'status' => 'active',
            'entitlement_filter' => [
                'languages' => ['en', 'bn'],
                'category_ids' => null,
                'media_kinds' => [], // NO MEDIA
            ],
        ]);

        $client = Client::factory()->create([
            'status' => 'active',
            'notes' => json_encode([
                'tier_quotas' => [
                    'stories_quota' => 500,
                    'media_quota' => 0,
                ],
            ]),
        ]);

        ClientPackage::create([
            'client_id' => $client->id,
            'package_id' => $textPkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $clientUser = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $token = $clientUser->createToken('portal')->plainTextToken;

        // 1. Create a published story
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->category->id,
            'headline' => 'Accessible Text Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        // 2. Client CAN read stories
        $feedResp = $this->getJson('/api/v1/portal/feed', [
            'Authorization' => "Bearer {$token}",
        ]);
        $feedResp->assertOk();
        $this->assertNotEmpty($feedResp->json('data'));

        $storyResp = $this->getJson("/api/v1/portal/story/{$story->public_id}", [
            'Authorization' => "Bearer {$token}",
        ]);
        $storyResp->assertOk();
        $this->assertEquals('Accessible Text Story', $storyResp->json('data.headline'));

        // 3. Create a media asset
        $photo = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'Sample Photo',
            'caption' => 'Photo caption',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', 'photo-bytes'),
            'storage_disk' => 's3',
            'original_path' => 'originals/test-photo.jpg',
            'derivatives' => [],
            'uploaded_by' => $this->author->id,
        ]);

        // 4. Client CANNOT download photo -> Blocked (429 or 403)
        $dlResp = $this->getJson("/api/v1/media/{$photo->public_id}/download", [
            'Authorization' => "Bearer {$token}",
        ]);
        $this->assertTrue(in_array($dlResp->status(), [403, 429], true), 'Expected 403 or 429, got: '.$dlResp->status());
    }
}
