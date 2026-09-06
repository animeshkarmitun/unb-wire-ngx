<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyServiceExtendedTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ApiKeyService::class);
    }

    public function test_issue_creates_hashed_key(): void
    {
        $client = Client::factory()->create();
        [$key, $raw] = $this->svc->issue($client, 'test-key');
        $this->assertEquals(hash('sha256', $raw), $key->key_hash);
        $this->assertStringStartsWith('unb_', $raw);
        $this->assertTrue($this->svc->hasScope($key, 'feed:read'));
    }

    public function test_authenticate_succeeds(): void
    {
        $client = Client::factory()->create();
        [$key, $raw] = $this->svc->issue($client, 'k1');
        $found = $this->svc->authenticate($raw);
        $this->assertNotNull($found);
        $this->assertEquals($key->id, $found->id);
        $this->assertNotNull($found->refresh()->last_used_at);
    }

    public function test_authenticate_fails_revoked(): void
    {
        $client = Client::factory()->create();
        [$k, $raw] = $this->svc->issue($client, 'k1');
        $this->svc->revoke($k);
        $this->assertNull($this->svc->authenticate($raw));
    }

    public function test_authenticate_fails_expired(): void
    {
        $client = Client::factory()->create();
        [$k, $raw] = $this->svc->issue($client, 'k1');
        $k->update(['expires_at' => now()->subHour()]);
        $this->assertNull($this->svc->authenticate($raw));
    }

    public function test_authenticate_fails_suspended_client(): void
    {
        $client = Client::factory()->create(['status' => 'suspended']);
        [$k,$raw] = $this->svc->issue($client, 'k1');
        $this->assertNull($this->svc->authenticate($raw));
    }

    public function test_rotate_keeps_old_valid_for_hour(): void
    {
        $client = Client::factory()->create();
        [$old,$rawOld] = $this->svc->issue($client, 'orig');
        [$new,$rawNew] = $this->svc->rotate($old);
        $this->assertNotEquals($rawOld, $rawNew);
        $this->assertNotNull($this->svc->authenticate($rawOld));
        $this->assertNotNull($this->svc->authenticate($rawNew));
        $this->assertTrue($old->refresh()->expires_at->isFuture());
    }

    public function test_has_scope_negative(): void
    {
        $client = Client::factory()->create();
        [$k] = $this->svc->issue($client, 'k1', ['feed:read']);
        $this->assertFalse($this->svc->hasScope($k, 'admin:write'));
    }

    public function test_middleware_scope_and_rate_limit(): void
    {
        $client = Client::factory()->create(['status' => 'active']);
        [$k,$raw] = $this->svc->issue($client, 'k1', ['feed:read'], 2);
        $this->getJson('/api/v1/feed', ['Authorization' => "Bearer {$raw}"])->assertOk();
        $this->getJson('/api/v1/feed', ['Authorization' => "Bearer {$raw}"])->assertOk();
        $this->getJson('/api/v1/feed', ['Authorization' => "Bearer {$raw}"])->assertStatus(429);
        $this->getJson('/api/v1/feed')->assertStatus(401);
    }
}
