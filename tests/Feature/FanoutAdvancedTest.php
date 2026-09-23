<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FanoutAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedStory(array $over = []): Story
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();

        return Story::factory()->create(array_merge(['category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'published', 'language' => 'en', 'published_at' => now()], $over));
    }

    private function makeClientWithChannel(array $filter = ['languages' => ['en']]): array
    {
        $client = Client::factory()->create(['status' => 'active']);
        $pkg = Package::factory()->create(['entitlement_filter' => $filter]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()]);
        $chId = DB::table('client_channels')->insertGetId(['client_id' => $client->id, 'type' => 'webhook', 'config' => json_encode(['url' => 'https://example.test/hook']), 'status' => 'active', 'failure_count' => 0]);

        return [$client, $chId];
    }

    public function test_idempotency_second_run_no_duplicate(): void
    {
        [$c,$ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory();
        Http::fake(fn () => Http::response('ok', 200));
        (new FanoutStory($s->id))->handle();
        (new FanoutStory($s->id))->handle();
        $this->assertEquals(1, DB::table('deliveries')->where('channel_id', $ch)->count());
    }

    public function test_auto_pause_after_5_failures(): void
    {
        [$c,$ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory();
        Http::fake(fn () => throw new \Exception('timeout'));
        DB::table('client_channels')->where('id', $ch)->update(['failure_count' => 4]);
        (new FanoutStory($s->id))->handle();
        $this->assertEquals('paused', DB::table('client_channels')->where('id', $ch)->value('status'));
        $this->assertEquals(5, DB::table('client_channels')->where('id', $ch)->value('failure_count'));
    }

    public function test_kill_notice_fans_out_to_prior_recipients(): void
    {
        [$c, $ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory();
        Http::fake(fn () => Http::response('ok', 200));
        (new FanoutStory($s->id))->handle();
        $this->assertEquals(1, DB::table('deliveries')->where('client_id', $c->id)->count());

        $s->update(['status' => 'killed']);
        (new FanoutStory($s->id))->handle();

        $this->assertEquals(2, DB::table('deliveries')->where('client_id', $c->id)->count());
        $this->assertEquals(1, DB::table('deliveries')->where('client_id', $c->id)->where('payload_hash', hash('sha256', 'killed:'.(string) $s->public_id))->count());
        Http::assertSent(fn ($req) => ($req['event'] ?? null) === 'story.killed');

        // Kill fan-out is idempotent
        (new FanoutStory($s->id))->handle();
        $this->assertEquals(2, DB::table('deliveries')->where('client_id', $c->id)->count());
    }

    public function test_killed_story_without_prior_delivery_gets_no_notice(): void
    {
        [$c, $ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory(['status' => 'killed']);
        (new FanoutStory($s->id))->handle();
        $this->assertDatabaseMissing('deliveries', ['client_id' => $c->id]);
    }

    public function test_suspended_client_gets_no_publish_delivery(): void
    {
        [$c, $ch] = $this->makeClientWithChannel();
        $c->update(['status' => 'suspended']);
        $s = $this->makePublishedStory();
        Http::fake(fn () => Http::response('ok', 200));
        (new FanoutStory($s->id))->handle();
        $this->assertDatabaseMissing('deliveries', ['client_id' => $c->id]);
    }

    public function test_update_fanout_sends_updated_event(): void
    {
        [$c, $ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory(['version' => 1]);
        Http::fake(fn () => Http::response('ok', 200));

        (new FanoutStory($s->id))->handle();
        Http::assertSent(fn ($req) => ($req['event'] ?? null) === 'story.published');

        $s->update(['version' => 2, 'body_text' => 'corrected copy']);
        (new FanoutStory($s->id))->handle();

        $this->assertEquals(2, DB::table('deliveries')->where('client_id', $c->id)->count());
        Http::assertSent(fn ($req) => ($req['event'] ?? null) === 'story.updated');
    }

    public function test_update_on_published_story_dispatches_fanout(): void
    {
        Queue::fake();
        $s = $this->makePublishedStory(['version' => 3]);

        app(StoryService::class)->updateDraft($s, ['headline' => 'Corrected headline'], 3, User::factory()->create());

        $this->app->terminate();
        Queue::assertPushed(FanoutStory::class);
    }

    public function test_payload_hash_stored(): void
    {
        [$c,$ch] = $this->makeClientWithChannel();
        $s = $this->makePublishedStory(['body_text' => 'hash me']);
        Http::fake(fn () => Http::response('ok', 200));
        (new FanoutStory($s->id))->handle();
        $expected = hash('sha256', 'hash me');
        $this->assertDatabaseHas('deliveries', ['payload_hash' => $expected]);
    }

    public function test_only_entitled_clients_receive(): void
    {
        [$cEn,$chEn] = $this->makeClientWithChannel(['languages' => ['en']]);
        [$cBn,$chBn] = $this->makeClientWithChannel(['languages' => ['bn']]);
        $s = $this->makePublishedStory(['language' => 'en']);
        Http::fake(fn () => Http::response('ok', 200));
        (new FanoutStory($s->id))->handle();
        $this->assertDatabaseHas('deliveries', ['client_id' => $cEn->id]);
        $this->assertDatabaseMissing('deliveries', ['client_id' => $cBn->id]);
    }
}
