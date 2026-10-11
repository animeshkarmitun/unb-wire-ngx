<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedPayloadSlimTest extends TestCase
{
    use RefreshDatabase;

    private function publishedStory(string $headline, string $bodyHtml = '<p>Full dispatch body paragraph.</p>'): Story
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);
        $story = $svc->createDraft([
            'language' => 'en',
            'headline' => $headline,
            'brief' => 'Slim feed brief',
            'body_html' => $bodyHtml,
            'category_id' => $cat->id,
        ], $user);
        $svc->transition($story, 'in_review', $user);
        $story = $story->refresh();
        $svc->transition($story, 'approved', $user);
        $story = $story->refresh();
        $svc->transition($story, 'published', $user);

        return $story->refresh();
    }

    public function test_feed_lists_are_brief_only_and_detail_carries_body_html(): void
    {
        Queue::fake();
        $story = $this->publishedStory('Slim payload headline');

        $feed = $this->getJson('/api/v1/portal/feed')->assertOk();
        $item = $feed->json('data.0');
        $this->assertSame($story->public_id, $item['public_id']);
        $this->assertArrayNotHasKey('body_html', $item);
        $this->assertSame('Slim feed brief', $item['brief']);

        $detail = $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertOk();
        $this->assertSame('<p>Full dispatch body paragraph.</p>', $detail->json('data.body_html'));
        $detail->assertHeader('Cache-Control');
    }

    public function test_detail_is_cached_and_epoch_invalidated_on_published_re_edit(): void
    {
        Queue::fake();
        $story = $this->publishedStory('Cached detail headline');
        $user = User::first();

        $first = $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertOk();
        $this->assertSame('<p>Full dispatch body paragraph.</p>', $first->json('data.body_html'));

        // re-edit after publish bumps the epoch — next detail read must not serve the old body
        app(StoryService::class)->updateDraft($story, ['body_html' => '<p>Corrected dispatch body.</p>'], (int) $story->version, $user);

        $second = $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertOk();
        $this->assertSame('<p>Corrected dispatch body.</p>', $second->json('data.body_html'));
    }

    public function test_detail_entitlement_denial_is_cached_per_client(): void
    {
        Queue::fake();
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);
        $story = $svc->createDraft([
            'language' => 'bn',
            'headline' => 'Bengali only headline',
            'brief' => 'b',
            'body_html' => '<p>bn body</p>',
            'category_id' => $cat->id,
        ], $user);
        $svc->transition($story, 'in_review', $user);
        $story = $story->refresh();
        $svc->transition($story, 'approved', $user);
        $story = $story->refresh();
        $svc->transition($story, 'published', $user);

        // guest (en, bn allowed) sees the story
        $this->getJson("/api/v1/portal/story/{$story->public_id}")->assertOk();

        // an English-only entitled client gets 404 for the same publicId
        $client = Client::factory()->create(['status' => 'active']);
        ClientPackage::factory()->create([
            'client_id' => $client->id,
            'package_id' => Package::factory()->create(['entitlement_filter' => ['languages' => ['en']]])->id,
            'status' => 'active',
        ]);

        $entitled = $this->withHeader('X-API-Key', app(ApiKeyService::class)->issue($client, 'feed', ['feed:read'])[1]);
        $entitled->getJson("/api/v1/portal/story/{$story->public_id}")->assertStatus(404);
    }
}
