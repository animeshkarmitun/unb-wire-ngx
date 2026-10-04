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
use Tests\TestCase;

class KillPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function seedStoryWithRecipient(): array
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
        $pkgBn = Package::create([
            'code' => 'PKG-BN-'.Str::random(6),
            'name' => 'pkgBn',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['bn']],
            'price_monthly' => 0,
            'status' => 'active',
        ]);

        $clientReceived = Client::create(['code' => 'RCV-'.Str::random(4), 'name' => 'Received', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        $clientNotReceived = Client::create(['code' => 'NR-'.Str::random(4), 'name' => 'Not', 'type' => 'online', 'status' => 'active', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);

        ClientPackage::create(['client_id' => $clientReceived->id, 'package_id' => $pkg->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        ClientPackage::create(['client_id' => $clientNotReceived->id, 'package_id' => $pkgBn->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $chReceived = ClientChannel::create(['client_id' => $clientReceived->id, 'type' => 'webhook', 'config' => ['url' => 'https://rcv.example.test/hook', 'signing_secret' => 's'], 'status' => 'active']);
        $chNotReceived = ClientChannel::create(['client_id' => $clientNotReceived->id, 'type' => 'webhook', 'config' => ['url' => 'https://nr.example.test/hook', 'signing_secret' => 's'], 'status' => 'active']);

        $user = User::factory()->create();

        return [
            'cat' => $cat,
            'pkg' => $pkg,
            'pkgBn' => $pkgBn,
            'rcv' => $clientReceived,
            'nr' => $clientNotReceived,
            'chRcv' => $chReceived,
            'chNr' => $chNotReceived,
            'user' => $user,
        ];
    }

    public function test_kill_without_prior_delivery_creates_no_kill_notice(): void
    {
        Http::fake();
        $f = $this->seedStoryWithRecipient();

        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['cat']->id,
            'owner_id' => $f['user']->id,
            'created_by' => $f['user']->id,
            'status' => 'published',
            'version' => 1,
            'published_at' => now()->subMinute(),
        ]);

        // Mark story as killed directly (no prior delivery)
        $story->update(['status' => 'killed']);
        $story->delete(); // simulate; restore via DB::insert

        // Use StoryService to transition into 'killed' from published (allowed)
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['cat']->id,
            'owner_id' => $f['user']->id,
            'created_by' => $f['user']->id,
            'status' => 'published',
            'version' => 1,
            'published_at' => now()->subMinute(),
        ]);

        app(StoryService::class)->transition($story, 'killed', $f['user']);

        // Run the job for any kill-notice logic
        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseMissing('deliveries', ['deliverable_id' => $story->id, 'idempotency_key' => hash('sha256', $story->id.'-'.$f['chRcv']->id.'-2-killed')]);
    }

    public function test_kill_with_prior_delivery_creates_notice_and_main_delete_outbox(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $f = $this->seedStoryWithRecipient();

        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['cat']->id,
            'owner_id' => $f['user']->id,
            'created_by' => $f['user']->id,
            'status' => 'published',
            'version' => 1,
            'published_at' => now()->subMinute(),
        ]);

        // First publish delivers to both clients.
        (new FanoutStory($story->id))->handle();
        $this->assertDatabaseHas('deliveries', ['client_id' => $f['rcv']->id, 'channel_id' => $f['chRcv']->id, 'status' => 'sent']);

        // Now kill via service.
        app(StoryService::class)->transition($story, 'killed', $f['user']);

        // Outbox: a delete row on main for this public_id.
        $this->assertDatabaseHas('index_outbox', [
            'index_name' => 'main',
            'op' => 'delete',
            'document_id' => $story->public_id,
        ]);

        // Run fan-out again to dispatch kill notices.
        (new FanoutStory($story->id))->handle();

        // The recipient gets a kill row, the never-received client does not.
        $this->assertDatabaseHas('deliveries', [
            'client_id' => $f['rcv']->id,
            'channel_id' => $f['chRcv']->id,
            'idempotency_key' => hash('sha256', $story->id.'-'.$f['chRcv']->id.'-2-killed'),
        ]);
        $this->assertDatabaseMissing('deliveries', [
            'client_id' => $f['nr']->id,
            'idempotency_key' => hash('sha256', $story->id.'-'.$f['chNr']->id.'-2-killed'),
        ]);
    }

    public function test_correction_on_published_writes_upsert_not_delete(): void
    {
        Http::fake();
        $f = $this->seedStoryWithRecipient();

        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['cat']->id,
            'owner_id' => $f['user']->id,
            'created_by' => $f['user']->id,
            'status' => 'published',
            'version' => 2,
            'published_at' => now()->subMinute(),
        ]);

        app(StoryService::class)->updateDraft($story, ['headline' => 'Correction v3'], 2, $f['user']);

        $this->assertDatabaseHas('index_outbox', [
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => $story->public_id,
        ]);
        $this->assertDatabaseMissing('index_outbox', [
            'index_name' => 'main',
            'op' => 'delete',
            'document_id' => $story->public_id,
        ]);
    }
}
