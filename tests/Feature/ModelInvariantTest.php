<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModelInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_factory_sets_ulid_public_id(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->assertNotEmpty($story->public_id);
        $this->assertSame(26, strlen($story->public_id), 'ULID public_id must be 26 chars');
    }

    public function test_client_api_key_scopes_cast_is_array(): void
    {
        $client = Client::factory()->create();
        $key = ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'k1',
            'key_hash' => hash('sha256', 'unb_test_scopes_cast_001'),
            'scopes' => ['feed:read', 'media:read'],
            'rate_limit_rpm' => 60,
        ]);

        $this->assertSame(['feed:read', 'media:read'], $key->scopes);
    }

    public function test_user_is_super_admin_follows_flag_not_role_name(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'code' => 'admin', 'type' => 'staff']);
        $userFlag = User::factory()->create(['is_superadmin' => true, 'role_id' => $adminRole->id]);
        $userNoFlag = User::factory()->create(['is_superadmin' => false, 'role_id' => $adminRole->id]);

        $this->assertTrue($userFlag->isSuperAdmin());
        $this->assertFalse($userNoFlag->isSuperAdmin());
    }

    public function test_media_asset_is_embargoed_matches_future_embargo_until(): void
    {
        $user = User::factory()->create();

        $future = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'future',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'f'),
            'storage_disk' => 'public',
            'original_path' => 'media/f.jpg',
            'uploaded_by' => $user->id,
            'embargo_until' => now()->addHour(),
        ]);

        $past = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'past',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'p'),
            'storage_disk' => 'public',
            'original_path' => 'media/p.jpg',
            'uploaded_by' => $user->id,
            'embargo_until' => now()->subHour(),
        ]);

        $this->assertTrue($future->isEmbargoed());
        $this->assertFalse($past->isEmbargoed());
    }

    public function test_media_asset_scope_client_visible_hides_embargoed(): void
    {
        $user = User::factory()->create();
        MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'visible',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'v'),
            'storage_disk' => 'public',
            'original_path' => 'media/v.jpg',
            'uploaded_by' => $user->id,
            'embargo_until' => null,
        ]);
        MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'title' => 'hidden',
            'caption' => 'c',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 10,
            'checksum' => hash('sha256', 'h'),
            'storage_disk' => 'public',
            'original_path' => 'media/h.jpg',
            'uploaded_by' => $user->id,
            'embargo_until' => now()->addHour(),
        ]);

        $visibleTitles = MediaAsset::clientVisible()->pluck('title')->all();

        $this->assertContains('visible', $visibleTitles);
        $this->assertNotContains('hidden', $visibleTitles);
    }
}
