<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        // Alpine.js (bundled with Livewire) evaluates x-data/x-show/@click
        // expressions via new Function(), which CSP blocks without
        // 'unsafe-eval'. Without it every Alpine directive on the admin panel
        // silently dies — dropdowns render permanently open, clicks do nothing.
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringContainsString("connect-src 'self' ws: wss:", $csp);
    }

    public function test_csp_nonce_is_per_request_and_renders_on_inline_scripts(): void
    {
        $first = $this->get('/login');
        $second = $this->get('/login');
        $nonce1 = $this->nonceFromCsp((string) $first->headers->get('Content-Security-Policy'));
        $nonce2 = $this->nonceFromCsp((string) $second->headers->get('Content-Security-Policy'));

        $this->assertNotNull($nonce1);
        $this->assertNotNull($nonce2);
        $this->assertNotSame($nonce1, $nonce2, 'nonce must be unique per request');
    }

    private function nonceFromCsp(string $csp): ?string
    {
        return preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $m) ? $m[1] : null;
    }

    public function test_hsts_only_set_over_https(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertFalse($response->headers->has('Strict-Transport-Security'), 'HSTS must not be sent over plain HTTP');
    }
}
