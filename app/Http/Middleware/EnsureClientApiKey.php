<?php

namespace App\Http\Middleware;

use App\Repositories\ClientRepository;
use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EnsureClientApiKey
{
    public function __construct(
        private ApiKeyService $svc,
        private ClientRepository $clients,
    ) {}

    public function handle(Request $request, Closure $next, string $scope = '')
    {
        $raw = $request->bearerToken() ?? $request->header('X-API-Key');
        if (! $raw) {
            return response()->json(['message' => 'Missing API key'], 401);
        }
        $key = $this->svc->authenticate($raw);
        if (! $key) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }
        if ($scope && ! $this->svc->hasScope($key, $scope)) {
            return response()->json(['message' => 'Insufficient scope'], 403);
        }
        $rpm = $key->rate_limit_rpm ?: 60;
        $rateLimitKey = 'api-key:'.$key->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, $rpm)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            return response()->json(['message' => 'Rate limit exceeded'], 429)->header('Retry-After', $retryAfter);
        }
        RateLimiter::hit($rateLimitKey, 60);
        $request->attributes->set('clientApiKey', $key);

        // Eager-load client to avoid lazy-load violation
        $client = $key->client ?? $this->clients->findWithRelations($key->client_id, ['clientChannels']);
        $request->attributes->set('client', $client);

        return $next($request);
    }
}
