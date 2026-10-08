<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
            // 'unsafe-eval' is REQUIRED by Alpine.js (bundled with Livewire): it
            // evaluates directive expressions (x-show, @click, x-data) via
            // new Function(), which CSP blocks without it. Without 'unsafe-eval'
            // every Alpine directive silently dies — dropdowns render permanently
            // open, clicks do nothing, x-data scopes stay empty. The CSP-build of
            // Alpine (@alpinejs/csp) avoids this but Livewire ships its own
            // bundled Alpine, so it cannot be swapped without forking Livewire.
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdn.jsdelivr.net",
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' ws: wss:",
        ]));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
