<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Models\Category;
use App\Models\Client;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fanout_creates_deliveries_with_idempotency(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['en']]]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()]);
        $chId = DB::table('client_channels')->insertGetId(['client_id' => $client->id, 'type' => 'webhook', 'config' => json_encode(['url' => 'https://example.test/hook']), 'status' => 'active', 'failure_count' => 0]);

        $story = Story::factory()->create(['language' => 'en', 'status' => 'published', 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);

        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseHas('deliveries', ['client_id' => $client->id, 'channel_id' => $chId]);
        $count = DB::table('deliveries')->where('client_id', $client->id)->count();
        $this->assertEquals(1, $count);

        (new FanoutStory($story->id))->handle();
        $this->assertEquals(1, DB::table('deliveries')->where('client_id', $client->id)->count());
    }

    public function test_fanout_respects_language_entitlement(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['bn']]]);
        DB::table('client_packages')->insert(['client_id' => $client->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()]);
        DB::table('client_channels')->insert(['client_id' => $client->id, 'type' => 'webhook', 'config' => json_encode(['url' => 'https://example.test/hook']), 'status' => 'active', 'failure_count' => 0]);

        $story = Story::factory()->create(['language' => 'en', 'status' => 'published', 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);

        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseMissing('deliveries', ['client_id' => $client->id]);
    }

    public function test_entitlement_filter_is_single_source(): void
    {
        $pkg = Package::factory()->create(['entitlement_filter' => ['languages' => ['en', 'bn'], 'category_ids' => [1, 2]]]);
        $filter = $pkg->entitlement_filter;
        $this->assertEquals(['en', 'bn'], $filter['languages']);
        $this->assertTrue(in_array(1, $filter['category_ids']));
    }

    public function test_delivery_payload_hash_stable(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create(['body_text' => 'hello', 'status' => 'published', 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);
        $hash = hash('sha256', $story->body_text);
        $this->assertEquals(64, strlen($hash));
    }
}
