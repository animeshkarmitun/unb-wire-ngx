<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Delivery;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessDeliveriesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(string $status, int $attempts, ?string $url = 'https://hook.example.test/notify', ?Story &$storyRef = null): Delivery
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::create([
            'code' => 'PKG-'.Str::random(6),
            'name' => 'pkg',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        ClientPackage::create(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $channel = ClientChannel::create([
            'client_id' => $client->id,
            'type' => 'webhook',
            'config' => ['url' => $url, 'signing_secret' => 'secret'],
            'status' => 'active',
        ]);
        $story = Story::factory()->create([
            'status' => 'published',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'version' => 1,
            'published_at' => now()->subMinute(),
        ]);
        $storyRef = $story;

        return Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => $status,
            'attempt_count' => $attempts,
            'idempotency_key' => hash('sha256', $story->id.'-'.$channel->id.'-1'),
            'payload_hash' => str_repeat('a', 64),
        ]);
    }

    public function test_successful_webhook_marks_sent(): void
    {
        Http::fake(['hook.example.test/*' => Http::response(['ok' => true], 200)]);
        $delivery = $this->makeDelivery('queued', 0);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertSame(0, $delivery->attempt_count);
    }

    public function test_non_2xx_marks_failed_and_bumps_attempts(): void
    {
        Http::fake(['hook.example.test/*' => Http::response('boom', 500)]);
        $delivery = $this->makeDelivery('queued', 0);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame(500, $delivery->response_code);
        $this->assertSame('boom', $delivery->error);
    }

    public function test_thrown_exception_marks_failed(): void
    {
        Http::fake(['hook.example.test/*' => function () {
            throw new ConnectionException('timeout');
        }]);
        $delivery = $this->makeDelivery('queued', 0);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertStringContainsString('timeout', (string) $delivery->error);
    }

    public function test_failed_row_within_backoff_window_is_skipped(): void
    {
        Http::fake(['hook.example.test/*' => Http::response(['ok' => true], 200)]);
        $delivery = $this->makeDelivery('failed', 3);
        // Setting('delivery.backoff_seconds') is 60 by default; backoff for attempt 3 = 60*(2^3-1)=420s. created_at=now by default → in-window.
        $delivery->update(['created_at' => now()]);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(3, $delivery->attempt_count);
    }

    public function test_delivery_at_max_retries_is_not_selected(): void
    {
        Http::fake(['hook.example.test/*' => Http::response(['ok' => true], 200)]);
        $delivery = $this->makeDelivery('failed', 5);
        // attempt_count >= max_retries (5) → row not selected.

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(5, $delivery->attempt_count);
    }

    public function test_inactive_channel_is_skipped(): void
    {
        Http::fake();
        $delivery = $this->makeDelivery('queued', 0);
        $delivery->channel->update(['status' => 'inactive']);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('queued', $delivery->status);
    }

    public function test_ftp_channel_is_skipped(): void
    {
        Http::fake();
        $delivery = $this->makeDelivery('queued', 0);
        $delivery->channel->update(['type' => 'ftp', 'config' => ['host' => 'ftp.example.test']]);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('queued', $delivery->status);
    }

    public function test_missing_story_marks_failed(): void
    {
        Http::fake();
        $story = null;
        $delivery = $this->makeDelivery('queued', 0, 'https://hook.example.test/notify', $story);
        $delivery->story?->delete();
        // Force a null story in the relation by deleting then refreshing.
        Delivery::where('id', $delivery->id)->update(['deliverable_id' => 999999]);

        $this->artisan('delivery:process')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame('Story not found', $delivery->error);
    }

    public function test_auto_pauses_channel_after_threshold(): void
    {
        Http::fake(['hook.example.test/*' => Http::response('boom', 500)]);
        $delivery = $this->makeDelivery('queued', 0);

        // 4 prior failures on the channel; threshold 5 default; this run is #5 → channel paused.
        $delivery->channel->update(['failure_count' => 4]);

        $this->artisan('delivery:process')->assertSuccessful();

        $this->assertSame('paused', $delivery->channel->fresh()->status);
    }

    public function test_killed_story_uses_story_killed_event(): void
    {
        Http::fake(['hook.example.test/*' => Http::response(['ok' => true], 200)]);
        $story = null;
        $delivery = $this->makeDelivery('queued', 0, 'https://hook.example.test/notify', $story);
        $story->update(['status' => 'killed']);

        $this->artisan('delivery:process')->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hook.example.test');
        });
    }
}
