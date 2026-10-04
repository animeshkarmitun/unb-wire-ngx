<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\SendStoryEmail;
use App\Mail\StoryAlert;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementCompilerTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $code = 'TEST'): array
    {
        $catA = Category::create(['slug' => 'cat-a-'.Str::random(4), 'name_en' => 'Cat A', 'name_bn' => 'Cat A', 'is_active' => true]);
        $catB = Category::create(['slug' => 'cat-b-'.Str::random(4), 'name_en' => 'Cat B', 'name_bn' => 'Cat B', 'is_active' => true]);

        $pkgA = Package::create([
            'code' => 'PKG-A-'.Str::random(4),
            'name' => 'A',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en'], 'category_ids' => [$catA->id]],
            'price_monthly' => 0,
            'status' => 'active',
        ]);
        $pkgB = Package::create([
            'code' => 'PKG-B-'.Str::random(4),
            'name' => 'B',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['bn'], 'category_ids' => [$catB->id]],
            'price_monthly' => 0,
            'status' => 'active',
        ]);

        $client = Client::create([
            'code' => $code,
            'name' => $code,
            'type' => 'online',
            'status' => 'active',
            'country' => 'BD',
            'timezone' => 'Asia/Dhaka',
        ]);

        $channel = ClientChannel::create([
            'client_id' => $client->id,
            'type' => 'webhook',
            'config' => ['url' => 'https://hook.example.test/notify', 'signing_secret' => 'secret'],
            'status' => 'active',
        ]);

        return [
            'client' => $client,
            'channel' => $channel,
            'pkgA' => $pkgA,
            'pkgB' => $pkgB,
            'catA' => $catA,
            'catB' => $catB,
        ];
    }

    private function attach(Client $client, Package $pkg, string $startsAt = '-1 day', string $endsAt = '+1 day', string $status = 'active'): void
    {
        ClientPackage::create([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'status' => $status,
            'starts_at' => $startsAt === '-' ? null : now()->modify($startsAt),
            'ends_at' => $endsAt === '+' ? null : now()->modify($endsAt),
            'auto_renew' => true,
        ]);
    }

    public function test_resolver_unions_two_packages_into_match(): void
    {
        $f = $this->makeClient('UNION');
        $this->attach($f['client'], $f['pkgA']);
        $this->attach($f['client'], $f['pkgB']);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['catB']->id,
            'status' => 'published',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'published_at' => now(),
            'version' => 1,
        ]);

        Http::fake(['hook.example.test/*' => Http::response(['ok' => true], 200)]);

        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseHas('deliveries', [
            'client_id' => $f['client']->id,
            'channel_id' => $f['channel']->id,
            'status' => 'sent',
        ]);
    }

    public function test_resolver_blocks_expired_subscription(): void
    {
        $f = $this->makeClient('EXP');
        ClientPackage::create([
            'client_id' => $f['client']->id,
            'package_id' => $f['pkgA']->id,
            'status' => 'active',
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subMinute(),
            'auto_renew' => true,
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['catA']->id,
            'status' => 'published',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'published_at' => now(),
            'version' => 1,
        ]);

        Http::fake();

        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseMissing('deliveries', ['client_id' => $f['client']->id]);
    }

    public function test_resolver_blocks_future_starts_at_subscription(): void
    {
        $f = $this->makeClient('FUT');
        ClientPackage::create([
            'client_id' => $f['client']->id,
            'package_id' => $f['pkgA']->id,
            'status' => 'active',
            'starts_at' => now()->addDay(),
            'ends_at' => null,
            'auto_renew' => true,
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['catA']->id,
            'status' => 'published',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'published_at' => now(),
            'version' => 1,
        ]);

        Http::fake();

        (new FanoutStory($story->id))->handle();

        $this->assertDatabaseMissing('deliveries', ['client_id' => $f['client']->id]);
    }

    public function test_email_suppressed_when_resolver_denies(): void
    {
        Mail::fake();
        $f = $this->makeClient('EMAIL');
        $this->attach($f['client'], $f['pkgA']);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'bn',
            'category_id' => $f['catA']->id,
            'status' => 'published',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'published_at' => now(),
            'version' => 1,
        ]);

        // StoryAlert would be sent if gating were missing; the resolver denies (bn vs en only).
        (new SendStoryEmail($story->id, $f['client']->id))->handle();

        Mail::assertNothingQueued();
    }

    public function test_email_sent_when_resolver_allows(): void
    {
        Mail::fake();
        $f = $this->makeClient('EMAILOK');
        $this->attach($f['client'], $f['pkgA']);
        $f['client']->update([
            'notes' => json_encode([
                'channels' => [
                    'email' => [
                        'on' => true,
                        'list' => ['desk@example.test'],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $f['catA']->id,
            'status' => 'published',
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'published_at' => now(),
            'version' => 1,
        ]);

        (new SendStoryEmail($story->id, $f['client']->id))->handle();

        Mail::assertQueued(StoryAlert::class);
    }
}
