<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientUser;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\StoryService;
use App\Support\FeedCache;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishToAllSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        config([
            'services.meilisearch.host' => 'http://meilisearch.test',
            'services.meilisearch.key' => 'test-key',
        ]);
    }

    private function setupSurfaces(): array
    {
        $category = Category::factory()->create([
            'slug' => 'test-national',
            'name_en' => 'National',
        ]);

        $package = Package::factory()->create([
            'status' => 'active',
            'entitlement_filter' => [
                'languages' => ['en'],
                'category_ids' => null,
            ],
        ]);

        $client = Client::factory()->create([
            'status' => 'active',
        ]);

        DB::table('client_packages')->insert([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
            'created_at' => now(),
        ]);

        $channel = ClientChannel::create([
            'client_id' => $client->id,
            'type' => 'webhook',
            'config' => [
                'url' => 'https://hook.test/notify',
                'signing_secret' => 's',
            ],
            'status' => 'active',
        ]);

        $clientUser = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $sanctumToken = $clientUser->createToken('portal')->plainTextToken;

        [$apiKey, $rawKey] = app(ApiKeyService::class)->issue($client, 'feed-key', ['feed:read']);

        $user = User::factory()->create();

        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $category->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'approved',
            'version' => 1,
            'headline' => 'National Breaking News Headline',
        ]);

        return [
            'category' => $category,
            'package' => $package,
            'client' => $client,
            'channel' => $channel,
            'clientUser' => $clientUser,
            'sanctumToken' => $sanctumToken,
            'apiKey' => $apiKey,
            'rawKey' => $rawKey,
            'user' => $user,
            'story' => $story,
        ];
    }

    public function test_published_story_appears_on_portal_feed_wire_api_and_triggers_delivery(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        config([
            'services.meilisearch.host' => 'http://meilisearch.test',
            'services.meilisearch.key' => 'test-key',
        ]);

        $setup = $this->setupSurfaces();
        $story = $setup['story'];
        $user = $setup['user'];
        $sanctumToken = $setup['sanctumToken'];
        $rawKey = $setup['rawKey'];

        $initialEpoch = FeedCache::epoch();

        // Act
        $story = app(StoryService::class)->transition($story, 'published', $user);
        (new FanoutStory($story->id))->handle();
        (new ProcessIndexOutbox)->handle();

        // Assert 1: story_events has action='published'
        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'published',
        ]);

        // Assert 2: story_versions count > 0
        $this->assertGreaterThan(0, $story->versions()->count());

        // Assert 3: index_outbox has row with status='done' for document_id=$story->public_id
        $this->assertDatabaseHas('index_outbox', [
            'document_id' => $story->public_id,
            'status' => 'done',
        ]);

        // Assert 4: deliveries table has row with status='sent' for the story
        $this->assertDatabaseHas('deliveries', [
            'deliverable_id' => $story->id,
            'status' => 'sent',
        ]);

        // Assert 5: FeedCache::epoch() > initial epoch
        $this->assertGreaterThan($initialEpoch, FeedCache::epoch());

        // Assert 6: GET /api/v1/portal/feed with Sanctum Bearer token -> 200, data contains story headline
        $portalFeedResp = $this->getJson('/api/v1/portal/feed', [
            'Authorization' => 'Bearer '.$sanctumToken,
        ]);
        $portalFeedResp->assertOk();
        $this->assertTrue(
            collect($portalFeedResp->json('data'))->pluck('headline')->contains($story->headline)
        );

        // Assert 7: GET /api/v1/portal/story/{publicId} with Sanctum Bearer token -> 200, headline matches
        $portalStoryResp = $this->getJson("/api/v1/portal/story/{$story->public_id}", [
            'Authorization' => 'Bearer '.$sanctumToken,
        ]);
        $portalStoryResp->assertOk();
        $this->assertSame($story->headline, $portalStoryResp->json('data.headline'));

        // Assert 8: GET /api/v1/feed with API key Bearer token -> 200, data contains story headline
        $wireFeedResp = $this->getJson('/api/v1/feed', [
            'Authorization' => 'Bearer '.$rawKey,
        ]);
        $wireFeedResp->assertOk();
        $this->assertTrue(
            collect($wireFeedResp->json('data'))->pluck('headline')->contains($story->headline)
        );
    }

    public function test_killed_story_disappears_from_portal_and_shows_tombstone_on_wire_api(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $setup = $this->setupSurfaces();
        $story = $setup['story'];
        $user = $setup['user'];
        $sanctumToken = $setup['sanctumToken'];
        $rawKey = $setup['rawKey'];

        // Publish the story first
        $story = app(StoryService::class)->transition($story, 'published', $user);
        (new FanoutStory($story->id))->handle();

        // Act: kill the story
        $story = app(StoryService::class)->transition($story, 'killed', $user);
        (new FanoutStory($story->id))->handle();

        // Assert 1: story_events has action='killed'
        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'killed',
        ]);

        // Assert 2: GET /api/v1/portal/feed with Sanctum token -> 200, data does NOT contain the headline
        $portalFeedResp = $this->getJson('/api/v1/portal/feed', [
            'Authorization' => 'Bearer '.$sanctumToken,
        ]);
        $portalFeedResp->assertOk();
        $this->assertFalse(
            collect($portalFeedResp->json('data'))->pluck('headline')->contains($story->headline)
        );

        // Assert 3: GET /api/v1/portal/story/{publicId} with Sanctum token -> 404
        $portalStoryResp = $this->getJson("/api/v1/portal/story/{$story->public_id}", [
            'Authorization' => 'Bearer '.$sanctumToken,
        ]);
        $portalStoryResp->assertNotFound();

        // Assert 4: GET /api/v1/feed with API key -> 200, data contains the story with status='killed' (tombstone)
        $wireFeedResp = $this->getJson('/api/v1/feed', [
            'Authorization' => 'Bearer '.$rawKey,
        ]);
        $wireFeedResp->assertOk();
        $killedStory = collect($wireFeedResp->json('data'))->firstWhere('public_id', $story->public_id);
        $this->assertNotNull($killedStory);
        $this->assertSame('killed', $killedStory['status']);
        $this->assertSame($story->headline, $killedStory['headline']);
    }
}
