<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RateLimitHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_helper_returns_configured_attempts(): void
    {
        config(['rate-limiting.limits.portal_feed.attempts' => 60]);
        config(['rate-limiting.dev_multiplier' => 1]);

        $this->assertEquals(60, RateLimitHelper::attempts('portal_feed'));
    }

    public function test_helper_applies_dev_multiplier_in_non_production(): void
    {
        config(['rate-limiting.limits.portal_feed.attempts' => 60]);
        config(['rate-limiting.dev_multiplier' => 10]);

        // In testing environment (non-production), multiplier applies
        $result = RateLimitHelper::attempts('portal_feed');
        $this->assertEquals(600, $result);
    }

    public function test_helper_returns_decay(): void
    {
        config(['rate-limiting.limits.portal_feed.decay' => 1]);
        $this->assertEquals(1, RateLimitHelper::decay('portal_feed'));
    }

    public function test_helper_disabled_returns_true_when_config_false(): void
    {
        config(['rate-limiting.enabled' => false]);
        $this->assertTrue(RateLimitHelper::disabled());
    }

    public function test_helper_disabled_returns_false_when_config_true(): void
    {
        config(['rate-limiting.enabled' => true]);
        $this->assertFalse(RateLimitHelper::disabled());
    }

    public function test_config_has_all_endpoint_limits(): void
    {
        $expected = [
            'portal_login', 'portal_forgot_pw', 'portal_reset_pw',
            'portal_feed', 'portal_story', 'portal_search_token',
            'portal_session', 'client_feed', 'media_download',
            'staff_upload', 'ai_assist', 'staff_login', 'email_verify',
        ];

        foreach ($expected as $key) {
            $this->assertNotNull(config("rate-limiting.limits.{$key}"), "Missing config for {$key}");
            $this->assertNotNull(config("rate-limiting.limits.{$key}.attempts"), "Missing attempts for {$key}");
            $this->assertNotNull(config("rate-limiting.limits.{$key}.decay"), "Missing decay for {$key}");
        }
    }

    public function test_portal_feed_rate_limit_uses_named_limiter(): void
    {
        config(['rate-limiting.enabled' => true]);
        config(['rate-limiting.limits.portal_feed.attempts' => 60]);
        config(['rate-limiting.dev_multiplier' => 1]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // 60 requests should succeed
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/portal/feed')->assertOk();
        }

        // 61st should be rate limited
        $this->getJson('/api/v1/portal/feed')->assertStatus(429);
    }

    public function test_email_verify_uses_named_limiter(): void
    {
        config(['rate-limiting.enabled' => true]);
        config(['rate-limiting.limits.email_verify.attempts' => 6]);
        config(['rate-limiting.dev_multiplier' => 1]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 6 requests should succeed
        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/verification-notification')->assertRedirect();
        }

        // 7th should be rate limited
        $this->post('/email/verification-notification')->assertStatus(429);
    }
}
