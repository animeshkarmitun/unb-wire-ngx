<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PublishChainTest extends TestCase
{
    use RefreshDatabase;

    private function publishableStory(): Story
    {
        $cat = Category::create(['slug' => 'cat-'.Str::random(4), 'name_en' => 'Cat', 'name_bn' => 'Cat', 'is_active' => true]);
        $pkg = Package::create([
            'code' => 'PKG-'.Str::random(6),
            'name' => 'pkg',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        $client = Client::create(['code' => 'CL-'.Str::random(4), 'name' => 'CL', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        ClientPackage::create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        ClientChannel::create(['client_id' => $client->id, 'type' => 'webhook', 'config' => ['url' => 'https://hook.example.test/notify', 'signing_secret' => 's'], 'status' => 'active']);

        $user = User::factory()->create();

        return Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'approved',
            'version' => 1,
        ]);
    }

    public function test_publish_writes_snapshot_event_outbox_and_delivers(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $story = $this->publishableStory();
        $user = User::find($story->owner_id);

        app(StoryService::class)->transition($story, 'published', $user);

        $story->load('versions', 'events');

        $this->assertGreaterThan(0, $story->versions()->count(), 'snapshot was not written');
        $this->assertDatabaseHas('story_events', [
            'story_id' => $story->id,
            'action' => 'published',
            'to_status' => 'published',
        ]);
        $this->assertDatabaseHas('index_outbox', [
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => $story->public_id,
        ]);

        // Run the dispatched fan-out.
        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseHas('deliveries', [
            'deliverable_id' => $story->id,
            'status' => 'sent',
        ]);
    }

    public function test_stale_save_raises_conflict_and_does_not_change_headline(): void
    {
        $story = $this->publishableStory();
        $user = User::find($story->owner_id);

        $headlineBefore = $story->headline;

        $this->expectException(ConflictHttpException::class);
        try {
            app(StoryService::class)->updateDraft($story, ['headline' => 'STALE'], 99, $user);
        } finally {
            $this->assertSame($headlineBefore, $story->refresh()->headline);
        }
    }

    public function test_takeover_with_stale_version_throws_conflict(): void
    {
        $story = $this->publishableStory();
        $actor = User::find($story->owner_id);

        $this->expectException(ConflictHttpException::class);
        app(StoryService::class)->takeOver($story, $actor, expectedVersion: 99);
    }
}
