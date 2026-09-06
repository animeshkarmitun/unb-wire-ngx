<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnsureClientApiKey
{
    public function __construct(private ApiKeyService $svc) {}

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
        $cacheKey = "ratelimit:api:{$key->id}:".now()->format('YmdHi');
        $hits = Cache::increment($cacheKey);
        if ($hits === 1) {
            Cache::put($cacheKey, 1, 65);
        }
        if ($hits > $rpm) {
            return response()->json(['message' => 'Rate limit exceeded'], 429)->header('Retry-After', 60);
        }
        $request->attributes->set('clientApiKey', $key);
        $request->attributes->set('client', $key->client);

        return $next($request);
    }
}
