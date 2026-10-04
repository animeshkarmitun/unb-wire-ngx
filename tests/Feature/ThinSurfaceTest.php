<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\MediaBatch;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StoryNotification;
use App\Services\AuditQueryService;
use App\Services\ClientService;
use App\Services\NotificationService;
use App\Services\Search\TenantTokenIssuer;
use App\Services\StoryService;
use App\Services\WireFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThinSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wire_feed_service_popular_rail_returns_collection_or_empty(): void
    {
        $svc = app(WireFeedService::class);

        // No stories yet: returns empty collection, does not throw.
        $this->assertCount(0, $svc->popularRail('en'));

        $cat = Category::factory()->create();
        $user = User::factory()->create();
        Story::factory()->count(3)->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'published',
            'version' => 1,
            'published_at' => now()->subHour(),
        ]);

        $rail = $svc->popularRail('en', 5);
        $this->assertGreaterThan(0, $rail->count());
    }

    public function test_notification_service_media_decision_fires_notification(): void
    {
        NotificationFacade::fake();

        $uploader = User::factory()->create();
        $batch = MediaBatch::create([
            'public_id' => (string) Str::ulid(),
            'uploader_id' => $uploader->id,
            'event_label' => 'thin surface test',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        app(NotificationService::class)->notifyMediaDecision($batch->id, 'media_approved', $uploader->id, $uploader);

        NotificationFacade::assertSentTo($uploader, StoryNotification::class);
    }

    public function test_client_service_stats_and_issue(): void
    {
        $svc = app(ClientService::class);

        $c1 = Client::factory()->create(['status' => 'active']);
        Client::factory()->create(['status' => 'suspended']);
        $pkg = Package::create(['code' => 'P-'.Str::random(6), 'name' => 'p', 'kind' => 'news', 'entitlement_filter' => ['languages' => ['en']], 'price_monthly' => 0, 'status' => 'active']);
        ClientPackage::create(['client_id' => $c1->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $stats = $svc->computeStats();
        $this->assertGreaterThanOrEqual(2, $stats['total'] ?? $stats['clients'] ?? 0);

        // clientHasIssue is a hook — must not throw even on a clean client.
        $this->assertFalse($svc->clientHasIssue($c1));
    }

    public function test_audit_query_service_search_filters_hits_and_misses(): void
    {
        $svc = app(AuditQueryService::class);

        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $svcStory = app(StoryService::class);
        $svcStory->transition($story, 'in_review', $user);
        $svcStory->transition($story, 'approved', $user);
        $svcStory->transition($story, 'published', $user);

        $paginator = $svc->search(action: 'published');
        $this->assertGreaterThan(0, $paginator->total());

        $empty = $svc->search(action: 'this_action_never_occurs_xyzzy');
        $this->assertSame(0, $empty->total());
    }

    public function test_tenant_token_issuer_shape_and_ttl(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::create(['code' => 'TT-'.Str::random(6), 'name' => 't', 'kind' => 'news', 'entitlement_filter' => ['languages' => ['en']], 'price_monthly' => 0, 'status' => 'active']);
        ClientPackage::create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $issued = app(TenantTokenIssuer::class)->issueFor($client, 7);

        $this->assertArrayHasKey('token', $issued);
        $this->assertArrayHasKey('host', $issued);
        $this->assertArrayHasKey('filter', $issued);
        $this->assertArrayHasKey('expires_at', $issued);
        $this->assertNotEmpty($issued['token']);
        $this->assertGreaterThan(now()->addMinutes(6)->timestamp, Carbon::parse($issued['expires_at'])->timestamp);
    }

    public function test_tenant_token_issuer_null_client_falls_back(): void
    {
        $issued = app(TenantTokenIssuer::class)->issueFor(null, 5);

        $this->assertArrayHasKey('token', $issued);
        $this->assertNotEmpty($issued['token']);
    }
}
