<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedTombstoneTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $catNational;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->catNational = Category::factory()->create(['name_en' => 'National', 'slug' => 'national']);
    }

    private function createClientWithPackage(array $filter): array
    {
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::factory()->create(['status' => 'active', 'entitlement_filter' => $filter]);

        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        [$key, $rawKey] = app(ApiKeyService::class)->issue($client, 'wire-key', ['feed:read']);

        return [$client, $rawKey];
    }

    public function test_feed_includes_published_story_with_normal_status(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'Active Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$key]);

        $resp->assertOk();
        $item = $resp->json('data.0');
        $this->assertEquals($story->public_id, $item['public_id']);
        $this->assertEquals('published', $item['status']);
        $this->assertFalse($item['is_killed']);
        $this->assertNull($item['killed_at']);
    }

    public function test_feed_includes_killed_tombstone_for_previously_published_story(): void
    {
        $story = Story::factory()->create([
            'status' => 'killed',
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'Retracted Breaking News',
            'published_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(5),
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$key]);

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $item = $resp->json('data.0');
        $this->assertEquals($story->public_id, $item['public_id']);
        $this->assertEquals('killed', $item['status']);
        $this->assertTrue($item['is_killed']);
        $this->assertEquals('STORY KILLED / RETRACTED', $item['brief']);
        $this->assertNotNull($item['killed_at']);
    }

    public function test_killed_story_that_was_never_published_is_excluded(): void
    {
        // Story killed from draft before ever being published
        Story::factory()->create([
            'status' => 'killed',
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'Never Published Draft Killed',
            'published_at' => null,
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$key]);

        $resp->assertOk();
        $this->assertCount(0, $resp->json('data'));
    }

    public function test_entitlement_filtering_applies_to_tombstones(): void
    {
        // Bangla story killed
        Story::factory()->create([
            'status' => 'killed',
            'language' => 'bn',
            'category_id' => $this->catNational->id,
            'headline' => 'Bangla Retracted Story',
            'published_at' => now()->subHour(),
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        // Client only has English package
        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        $resp = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$key]);

        $resp->assertOk();
        $this->assertCount(0, $resp->json('data'));
    }
}
