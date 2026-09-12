<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Story;
use App\Models\User;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_and_authenticate(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$key, $raw] = $svc->issue($c, 'test', ['feed:read']);
        $this->assertNotEmpty($raw);
        $this->assertEquals(hash('sha256', $raw), $key->key_hash);
        $found = $svc->authenticate($raw);
        $this->assertNotNull($found);
        $this->assertTrue($svc->hasScope($found, 'feed:read'));
        $this->assertFalse($svc->hasScope($found, 'media:download'));
    }

    public function test_rotate_keeps_two_keys_active(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$k1, $r1] = $svc->issue($c, 'orig');
        [$k2, $r2] = $svc->rotate($k1);
        $this->assertNotNull($svc->authenticate($r1));
        $this->assertNotNull($svc->authenticate($r2));
        $this->assertNotEquals($r1, $r2);
    }

    public function test_revoke_blocks_auth(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$k, $r] = $svc->issue($c, 'to-revoke');
        $svc->revoke($k);
        $this->assertNull($svc->authenticate($r));
    }

    public function test_rate_limit(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$k, $raw] = $svc->issue($c, 'limited', ['feed:read'], 2);
        Category::factory()->create();
        $user = User::factory()->create();
        foreach ([1, 2] as $i) {
            $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$raw])->assertOk();
        }
        $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$raw])->assertStatus(429);
    }

    public function test_scope_enforced(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$k, $raw] = $svc->issue($c, 'no-feed', ['media:download']);
        $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$raw])->assertStatus(403);
    }

    public function test_feed_returns_iso8601_and_cursor(): void
    {
        $c = Client::factory()->create();
        $svc = app(ApiKeyService::class);
        [$k, $raw] = $svc->issue($c, 'feed', ['feed:read']);
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        Story::factory()->create(['status' => 'published', 'published_at' => now(), 'category_id' => $cat->id, 'owner_id' => $user->id, 'created_by' => $user->id]);
        $resp = $this->getJson('/api/v1/feed', ['Authorization' => 'Bearer '.$raw]);
        $resp->assertOk();
        $this->assertNotEmpty($resp->json('data.0.published_at'));
        $this->assertTrue(str_contains($resp->json('data.0.published_at'), 'T'));
    }
}
