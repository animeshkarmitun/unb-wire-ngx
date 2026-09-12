<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientApiKey;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $catEconomy;

    private Category $catSports;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->owner = User::factory()->create();
        $this->catEconomy = Category::factory()->create(['name_en' => 'Economy', 'slug' => 'economy']);
        $this->catSports = Category::factory()->create(['name_en' => 'Sports', 'slug' => 'sports']);
    }

    // ─── /api/v1/portal/feed ────────────────────────────────────────

    public function test_feed_returns_only_published_stories(): void
    {
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);
        Story::factory()->create([
            'status' => 'draft', 'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);
        Story::factory()->create([
            'status' => 'in_review', 'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);
        Story::factory()->create([
            'status' => 'archived', 'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);

        $resp = $this->getJson('/api/v1/portal/feed?language=en');

        $resp->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('published', $resp->json('data.0.status'));
    }

    public function test_feed_filters_by_language(): void
    {
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'English economy story',
        ]);
        Story::factory()->published()->create([
            'language' => 'bn', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Bangla economy story',
        ]);

        $en = $this->getJson('/api/v1/portal/feed?language=en');
        $en->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('en', $en->json('data.0.language'));

        $bn = $this->getJson('/api/v1/portal/feed?language=bn');
        $bn->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('bn', $bn->json('data.0.language'));
    }

    public function test_feed_filters_by_category(): void
    {
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Economy headline',
        ]);
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catSports->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Sports headline',
        ]);

        $resp = $this->getJson('/api/v1/portal/feed?language=en&category=Economy');

        $resp->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Economy headline', $resp->json('data.0.headline'));
    }

    public function test_feed_filters_by_is_breaking(): void
    {
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'is_breaking' => true, 'headline' => 'Breaking news',
        ]);
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'is_breaking' => false, 'headline' => 'Regular news',
        ]);

        $resp = $this->getJson('/api/v1/portal/feed?language=en&is_breaking=1');

        $resp->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Breaking news', $resp->json('data.0.headline'));
        $this->assertTrue($resp->json('data.0.is_breaking'));
    }

    public function test_feed_filters_by_search_query(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('ILIKE is PostgreSQL-specific; run with --env=testing or DB_CONNECTION=pgsql');
        }

        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'GDP growth surges', 'brief' => 'Economic expansion continues',
        ]);
        Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catSports->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Cricket world cup', 'brief' => 'Bangladesh wins',
        ]);

        $resp = $this->getJson('/api/v1/portal/feed?language=en&search=GDP');

        $resp->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringContainsString('GDP', $resp->json('data.0.headline'));
    }

    public function test_feed_respects_limit_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Story::factory()->published()->create([
                'language' => 'en', 'category_id' => $this->catEconomy->id,
                'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $resp = $this->getJson('/api/v1/portal/feed?language=en&limit=2');

        $resp->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_feed_enforces_max_limit_of_100(): void
    {
        $resp = $this->getJson('/api/v1/portal/feed?language=en&limit=500');

        $resp->assertOk();
        $this->assertLessThanOrEqual(100, count($resp->json('data')));
    }

    public function test_feed_returns_cache_control_header(): void
    {
        $resp = $this->getJson('/api/v1/portal/feed?language=en');

        $resp->assertOk();
        $cacheControl = $resp->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
    }

    public function test_feed_response_shape(): void
    {
        $cat = Category::factory()->create(['name_en' => 'Politics', 'slug' => 'politics']);
        $story = Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $cat->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Test headline', 'brief' => 'Test brief',
            'body_html' => '<p>Body content</p>', 'is_breaking' => true,
            'published_at' => now(),
        ]);

        $resp = $this->getJson('/api/v1/portal/feed?language=en');

        $resp->assertOk();
        $item = $resp->json('data.0');
        $this->assertEquals($story->public_id, $item['public_id']);
        $this->assertEquals('Test headline', $item['headline']);
        $this->assertEquals('Test brief', $item['brief']);
        $this->assertEquals('<p>Body content</p>', $item['body_html']);
        $this->assertEquals('Politics', $item['category']);
        $this->assertEquals('en', $item['language']);
        $this->assertTrue($item['is_breaking']);
        $this->assertArrayHasKey('tags', $item);
        $this->assertArrayHasKey('media', $item);
        $this->assertArrayHasKey('has_video', $item);
        $this->assertNotNull($item['published_at']);
    }

    // ─── /api/v1/portal/story/{publicId} ────────────────────────────

    public function test_show_returns_published_story_with_full_details(): void
    {
        $cat = Category::factory()->create(['name_en' => 'Economy']);
        $story = Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $cat->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
            'headline' => 'Full story test', 'body_html' => '<p>Full body</p>',
            'published_at' => now(),
        ]);

        $resp = $this->getJson("/api/v1/portal/story/{$story->public_id}");

        $resp->assertOk();
        $data = $resp->json('data');
        $this->assertEquals($story->public_id, $data['public_id']);
        $this->assertEquals('Full story test', $data['headline']);
        $this->assertEquals('<p>Full body</p>', $data['body_html']);
        $this->assertEquals('Economy', $data['category']);
        $this->assertIsArray($data['tags']);
        $this->assertIsArray($data['media']);
    }

    public function test_show_returns_404_for_draft_story(): void
    {
        $story = Story::factory()->create([
            'status' => 'draft', 'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);

        $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertNotFound();
    }

    public function test_show_returns_404_for_archived_story(): void
    {
        $story = Story::factory()->create([
            'status' => 'archived', 'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);

        $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertNotFound();
    }

    public function test_show_returns_404_for_invalid_public_id(): void
    {
        $this->getJson('/api/v1/portal/story/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }

    // ─── /api/v1/portal/search-token ────────────────────────────────

    public function test_search_token_guest_returns_default_public_token(): void
    {
        $resp = $this->postJson('/api/v1/portal/search-token');

        $resp->assertOk();
        $resp->assertJsonStructure(['token', 'host', 'index', 'filter', 'entitlement', 'expires_at']);
        $this->assertEquals('main', $resp->json('index'));
        $this->assertStringContainsString('en', $resp->json('filter'));
        $this->assertStringContainsString('bn', $resp->json('filter'));
    }

    public function test_search_token_with_valid_api_key_returns_client_entitlement(): void
    {
        $client = Client::factory()->create();
        $rawKey = 'unb_live_'.Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Test Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
        ]);

        $resp = $this->postJson('/api/v1/portal/search-token', [], [
            'Authorization' => 'Bearer '.$rawKey,
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['token', 'host', 'index', 'filter', 'entitlement', 'expires_at']);
        $this->assertNotNull($resp->json('entitlement'));
    }

    public function test_search_token_with_x_api_key_header(): void
    {
        $client = Client::factory()->create();
        $rawKey = 'unb_live_'.Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Test Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
        ]);

        $resp = $this->postJson('/api/v1/portal/search-token', [], [
            'X-API-Key' => $rawKey,
        ]);

        $resp->assertOk();
        $this->assertNotNull($resp->json('token'));
    }

    public function test_search_token_updates_last_used_at(): void
    {
        $client = Client::factory()->create();
        $rawKey = 'unb_live_'.Str::random(16);
        $apiKey = ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Test Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
        ]);

        $this->assertNull($apiKey->last_used_at);

        $this->postJson('/api/v1/portal/search-token', [], [
            'Authorization' => 'Bearer '.$rawKey,
        ])->assertOk();

        $this->assertNotNull($apiKey->fresh()->last_used_at);
    }

    public function test_search_token_with_revoked_key_falls_back_to_guest(): void
    {
        $client = Client::factory()->create();
        $rawKey = 'unb_live_'.Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Revoked Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
            'revoked_at' => now()->subDay(),
        ]);

        $resp = $this->postJson('/api/v1/portal/search-token', [], [
            'Authorization' => 'Bearer '.$rawKey,
        ]);

        $resp->assertOk();
        $this->assertStringContainsString('en', $resp->json('filter'));
        $this->assertStringContainsString('bn', $resp->json('filter'));
    }

    public function test_search_token_with_expired_key_falls_back_to_guest(): void
    {
        $client = Client::factory()->create();
        $rawKey = 'unb_live_'.Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Expired Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
            'expires_at' => now()->subHour(),
        ]);

        $resp = $this->postJson('/api/v1/portal/search-token', [], [
            'Authorization' => 'Bearer '.$rawKey,
        ]);

        $resp->assertOk();
        $this->assertStringContainsString('en', $resp->json('filter'));
        $this->assertStringContainsString('bn', $resp->json('filter'));
    }

    public function test_search_token_with_invalid_key_returns_guest_token(): void
    {
        $resp = $this->postJson('/api/v1/portal/search-token', [], [
            'Authorization' => 'Bearer unb_live_invalidkey123',
        ]);

        $resp->assertOk();
        $this->assertStringContainsString('en', $resp->json('filter'));
    }

    // ─── /api/v1/portal/context ─────────────────────────────────────

    public function test_context_returns_client_and_saved_searches(): void
    {
        $client = Client::factory()->create([
            'name' => 'The Daily Star',
            'notes' => json_encode([
                'tier_quotas' => ['stories_quota' => 500, 'media_quota' => 150],
                'saved_searches' => [['name' => 'Test', 'q' => 'test']],
            ]),
        ]);

        $package = \App\Models\Package::first() ?? \App\Models\Package::factory()->create(['name' => 'Premium']);
        \App\Models\ClientPackage::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $rawKey = 'unb_live_' . Str::random(16);
        ClientApiKey::create([
            'client_id' => $client->id,
            'name' => 'Test Key',
            'key_hash' => hash('sha256', $rawKey),
            'scopes' => json_encode(['feed:read']),
            'rate_limit_rpm' => 60,
        ]);

        $channel = \App\Models\ClientChannel::factory()->create(['client_id' => $client->id]);
        
        \App\Models\Delivery::create([
            'client_id' => $client->id,
            'deliverable_type' => 'story',
            'deliverable_id' => 1,
            'channel_id' => $channel->id,
            'status' => 'sent',
            'created_at' => now(),
            'idempotency_key' => Str::random(16),
            'payload_hash' => 'hash123',
        ]);
        
        \App\Models\Download::create([
            'client_id' => $client->id,
            'item_type' => 'App\Models\MediaAsset',
            'item_id' => 1,
            'created_at' => now(),
            'ip' => '127.0.0.1'
        ]);

        $resp = $this->getJson('/api/v1/portal/context', [
            'Authorization' => 'Bearer ' . $rawKey,
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'client' => ['name', 'initials', 'tier', 'renews_at', 'stories_quota', 'stories_used', 'media_quota', 'media_used'],
            'saved_searches',
        ]);
        $this->assertEquals('The Daily Star', $resp->json('client.name'));
        $this->assertEquals('TD', $resp->json('client.initials'));
        $this->assertEquals($package->name, $resp->json('client.tier'));
        $this->assertEquals(500, $resp->json('client.stories_quota'));
        $this->assertEquals(150, $resp->json('client.media_quota'));
        $this->assertEquals(1, $resp->json('client.stories_used'));
        $this->assertEquals(1, $resp->json('client.media_used'));
        $this->assertCount(1, $resp->json('saved_searches'));
    }

    public function test_context_without_auth_returns_null_client(): void
    {
        $resp = $this->getJson('/api/v1/portal/context');
        
        $resp->assertOk();
        $this->assertNull($resp->json('client'));
        $this->assertEmpty($resp->json('saved_searches'));
    }

    // ─── Rate Limiting ──────────────────────────────────────────────

    public function test_feed_rate_limit_at_60_per_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/portal/feed?language=en')->assertOk();
        }

        $this->getJson('/api/v1/portal/feed?language=en')->assertStatus(429);
    }

    public function test_search_token_rate_limit_at_60_per_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/v1/portal/search-token')->assertOk();
        }

        $this->postJson('/api/v1/portal/search-token')->assertStatus(429);
    }

    public function test_story_rate_limit_at_120_per_minute(): void
    {
        $story = Story::factory()->published()->create([
            'language' => 'en', 'category_id' => $this->catEconomy->id,
            'owner_id' => $this->owner->id, 'created_by' => $this->owner->id,
        ]);

        for ($i = 0; $i < 120; $i++) {
            $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertOk();
        }

        $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertStatus(429);
    }
}
