<?php

namespace App\Http\Middleware;

use App\Models\ClientApiKey;
use App\Models\ClientUser;
use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a Client from either a Sanctum ClientUser token or a ClientApiKey.
 *
 * Mode:
 *  - 'required': 401 if unauthenticated, 403 if deactivated or suspended.
 *  - 'optional': proceeds with client=null if unauthenticated; 403 if deactivated or suspended.
 *
 * Sets on request attributes:
 *  - 'client'        → App\Models\Client|null
 *  - 'clientUser'    → App\Models\ClientUser (if Sanctum auth)
 *  - 'clientApiKey'  → App\Models\ClientApiKey (if API key auth)
 */
class ResolveClient
{
    public function __construct(private ApiKeyService $apiKeySvc) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        // Already resolved by another middleware?
        if ($request->attributes->get('client')) {
            return $next($request);
        }

        $bearer = $request->bearerToken();
        $apiKeyHeader = $request->header('X-API-Key');

        // 1. Try Sanctum token (portal user)
        if ($bearer) {
            $accessToken = PersonalAccessToken::findToken($bearer);
            if ($accessToken && $accessToken->tokenable instanceof ClientUser) {
                $user = $accessToken->tokenable;
                if ($user->status !== 'active') {
                    return response()->json(['message' => 'Account deactivated'], 403);
                }
                $client = $user->client;
                if (! $client || $client->status !== 'active') {
                    return response()->json(['message' => 'Client suspended'], 403);
                }
                $user->withAccessToken($accessToken);
                $request->setUserResolver(fn () => $user);
                $request->attributes->set('clientUser', $user);
                $request->attributes->set('client', $client);

                return $next($request);
            }
        }

        // 2. Try API key (from X-API-Key header or bearer token)
        $rawKey = $apiKeyHeader ?? $bearer;
        if ($rawKey) {
            $hash = hash('sha256', $rawKey);
            $key = ClientApiKey::with('client')->where('key_hash', $hash)->whereNull('revoked_at')->first();

            if ($key) {
                if ($key->client && $key->client->status !== 'active') {
                    return response()->json(['message' => 'Client suspended'], 403);
                }

                if (! $key->expires_at || $key->expires_at->isFuture()) {
                    $key->update(['last_used_at' => now()]);

                    $rpm = $key->rate_limit_rpm ?: 60;
                    $rateLimitKey = 'api-key:'.$key->id;
                    if (RateLimiter::tooManyAttempts($rateLimitKey, $rpm)) {
                        $retryAfter = RateLimiter::availableIn($rateLimitKey);

                        return response()->json(['message' => 'Rate limit exceeded'], 429)
                            ->header('Retry-After', $retryAfter);
                    }
                    RateLimiter::hit($rateLimitKey, 60);

                    $request->attributes->set('clientApiKey', $key);
                    $request->attributes->set('client', $key->client);

                    return $next($request);
                }
            }
        }

        // 3. Neither authenticated
        if ($mode === 'optional') {
            $request->attributes->set('client', null);

            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
