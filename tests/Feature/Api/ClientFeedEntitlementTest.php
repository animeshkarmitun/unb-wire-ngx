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

class ClientFeedEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $catNational;

    private Category $catSports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->author = User::factory()->create();
        $this->catNational = Category::factory()->create(['name_en' => 'National', 'slug' => 'national']);
        $this->catSports = Category::factory()->create(['name_en' => 'Sports', 'slug' => 'sports']);
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

        [$key, $rawKey] = app(ApiKeyService::class)->issue($client, 'feed-key', ['feed:read']);

        return [$client, $rawKey];
    }

    public function test_client_with_english_only_package_only_receives_english_stories(): void
    {
        // 1 English story, 1 Bangla story
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'English National News',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);
        Story::factory()->published()->create([
            'language' => 'bn',
            'category_id' => $this->catNational->id,
            'headline' => 'Bangla National News',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$clientEn, $keyEn] = $this->createClientWithPackage([
            'languages' => ['en'],
            'category_ids' => null,
        ]);

        $resp = $this->getJson('/api/v1/feed', [
            'Authorization' => 'Bearer '.$keyEn,
        ]);

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('English National News', $resp->json('data.0.headline'));
        $this->assertEquals('en', $resp->json('data.0.language'));
    }

    public function test_client_with_category_restriction_only_receives_entitled_categories(): void
    {
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'National Headline',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catSports->id,
            'headline' => 'Sports Headline',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$clientSports, $keySports] = $this->createClientWithPackage([
            'languages' => ['en'],
            'category_ids' => [$this->catSports->id],
        ]);

        $resp = $this->getJson('/api/v1/feed', [
            'Authorization' => 'Bearer '.$keySports,
        ]);

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('Sports Headline', $resp->json('data.0.headline'));
    }

    public function test_cache_is_isolated_between_clients_with_different_entitlements(): void
    {
        Story::factory()->published()->create([
            'language' => 'en',
            'category_id' => $this->catNational->id,
            'headline' => 'English Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);
        Story::factory()->published()->create([
            'language' => 'bn',
            'category_id' => $this->catNational->id,
            'headline' => 'Bangla Story',
            'owner_id' => $this->author->id,
            'created_by' => $this->author->id,
        ]);

        [$clientEn, $keyEn] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);
        [$clientBn, $keyBn] = $this->createClientWithPackage(['languages' => ['bn'], 'category_ids' => null]);

        // Client EN queries first (populates cache)
        $respEn = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$keyEn]);
        $respEn->assertOk();
        $this->assertEquals('English Story', $respEn->json('data.0.headline'));

        // Client BN queries exact same URL — must NOT get cached English story
        $respBn = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$keyBn]);
        $respBn->assertOk();
        $this->assertEquals('Bangla Story', $respBn->json('data.0.headline'));
    }

    public function test_cursor_pagination_works_with_entitlement_filters(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Story::factory()->published()->create([
                'language' => 'en',
                'category_id' => $this->catNational->id,
                'headline' => "Story {$i}",
                'owner_id' => $this->author->id,
                'created_by' => $this->author->id,
                'published_at' => now()->subMinutes(10 - $i),
            ]);
        }

        [$client, $key] = $this->createClientWithPackage(['languages' => ['en'], 'category_ids' => null]);

        // Page 1 with limit 2
        $page1 = $this->getJson('/api/v1/feed?limit=2', ['Authorization' => 'Bearer '.$key]);
        $page1->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $cursor = $page1->json('cursor');
        $this->assertNotNull($cursor);

        // Page 2 using cursor
        $page2 = $this->getJson('/api/v1/feed?limit=2&since='.urlencode($cursor), ['Authorization' => 'Bearer '.$key]);
        $page2->assertOk();
        $this->assertCount(2, $page2->json('data'));
        $this->assertNotEquals($page1->json('data.0.public_id'), $page2->json('data.0.public_id'));
    }
}
