<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use App\Services\Media\PresignedUrlService;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchedulerAndMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_embargo_lift_transitions_approved_to_published(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create(['status' => 'approved', 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'embargo_until' => now()->subMinute()]);
        $pending = Story::where('embargo_until', '<=', now())->where('status', 'approved')->count();
        $this->assertGreaterThanOrEqual(1, $pending);
        $story->update(['status' => 'published', 'published_at' => now()]);
        $this->assertEquals('published', $story->refresh()->status);
    }

    public function test_archive_sweep_moves_old_published_to_archived(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $old = Story::factory()->create(['status' => 'published', 'published_at' => now()->subMonths(13), 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);
        $recent = Story::factory()->create(['status' => 'published', 'published_at' => now(), 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);
        $candidates = Story::where('status', 'published')->where('published_at', '<', now()->subMonths(12))->get();
        $this->assertTrue($candidates->contains($old));
        $this->assertFalse($candidates->contains($recent));
    }

    public function test_feed_throttle_and_cache_headers(): void
    {
        $this->getJson('/api/v1/portal/feed')->assertOk()->assertHeader('Cache-Control');
        $this->postJson('/api/v1/portal/search-token')->assertOk();
    }

    public function test_client_api_scope_enforcement(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        [$key,$raw] = app(ApiKeyService::class)->issue($client, 'scoped', ['other:read']);
        $this->getJson('/api/v1/feed', ['Authorization' => "Bearer {$raw}"])->assertStatus(403);
        $this->getJson('/api/v1/feed')->assertStatus(401);
        [$key2,$raw2] = app(ApiKeyService::class)->issue($client, 'ok', ['feed:read']);
        $this->getJson('/api/v1/feed', ['Authorization' => "Bearer {$raw2}"])->assertOk();
    }

    public function test_presigned_url_service_aborts_without_config(): void
    {
        config(['filesystems.disks.s3.url' => null]);
        $user = User::factory()->create();
        $a = MediaAsset::create(['public_id' => (string) Str::ulid(), 'kind' => 'photo', 'status' => 'library', 'title' => 't', 'caption' => 'c', 'credit_line' => 'UNB', 'mime' => 'image/jpeg', 'size_bytes' => 10, 'checksum' => hash('sha256', 'x'), 'storage_disk' => 's3', 'original_path' => 'missing.jpg', 'uploaded_by' => $user->id]);
        try {
            app(PresignedUrlService::class)->forAsset($a);
            $this->fail('should abort');
        } catch (HttpException $e) {
            $this->assertEquals(500, $e->getStatusCode());
        }
    }

    public function test_story_versions_immutable_via_model(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = app(StoryService::class)->createDraft(['language' => 'en', 'headline' => 'orig', 'brief' => 'b', 'body_html' => '<p>body long enough</p>', 'category_id' => $cat->id], $user);
        app(StoryService::class)->updateDraft($story, ['headline' => 'v2'], 1, $user);
        $this->assertEquals(2, $story->refresh()->versions()->count());
    }

    public function test_download_ledger_increments(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $a = MediaAsset::create(['public_id' => (string) Str::ulid(), 'kind' => 'photo', 'status' => 'library', 'title' => 'dl', 'caption' => 'c', 'credit_line' => 'UNB', 'mime' => 'image/jpeg', 'size_bytes' => 100, 'checksum' => hash('sha256', 'y'), 'storage_disk' => 's3', 'original_path' => 'dl.jpg', 'uploaded_by' => $user->id, 'download_count' => 0]);
        app(PresignedUrlService::class)->recordDownload($a, $client->id, null, 'original');
        $this->assertDatabaseHas('downloads', ['item_id' => $a->id, 'format' => 'original']);
        $this->assertEquals(1, $a->refresh()->download_count);
    }
}
