<?php

namespace App\Services\Search;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;

class TenantTokenIssuer
{
    public function __construct(private EntitlementResolver $resolver) {}

    public function issueFor(?Client $client, int $ttlMinutes = 30): array
    {
        $host = config('services.meilisearch.host') ?? env('MEILISEARCH_HOST', 'http://localhost:7700');
        $key = config('services.meilisearch.key') ?? env('MEILISEARCH_KEY');

        if ($client) {
            $cacheKey = "entitlement:client:{$client->id}";
            $entitlement = Cache::remember($cacheKey, 300, fn() => $this->resolver->forClient($client));
            $filter = $this->resolver->compileMeiliFilterFromEntitlement($entitlement);
        } else {
            $entitlement = ['languages' => ['en', 'bn'], 'category_ids' => null, 'media_kinds' => ['photo']];
            $filter = 'language IN [en, bn]';
        }

        $expiresAt = now()->addMinutes($ttlMinutes);
        $searchRules = [
            'main' => ['filter' => $filter],
            'archive' => ['filter' => $filter],
            'media' => ['filter' => $filter],
        ];

        if (! empty($key)) {
            try {
                $clientMs = new \Meilisearch\Client($host, $key);
                $uid = substr(hash('sha256', $key), 0, 8);
                $token = $clientMs->generateTenantToken($uid, $searchRules, ['expiresAt' => $expiresAt]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('TenantTokenIssuer fallback', ['error'=>$e->getMessage()]);
                $token = $this->fallbackToken($filter, $expiresAt, $key);
            }
        } else {
            \Illuminate\Support\Facades\Log::warning('TenantTokenIssuer missing key — using insecure fallback');
            $token = $this->fallbackToken($filter, $expiresAt, config('app.key').'-fallback');
        }

        return [
            'token' => $token,
            'host' => $host,
            'index' => 'main',
            'filter' => $filter,
            'entitlement' => $entitlement,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function fallbackToken(string $filter, \Carbon\Carbon $exp, string $secret): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['filter' => $filter, 'exp' => $exp->timestamp, 'searchRules' => ['main' => ['filter' => $filter]]])), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');
        return "{$header}.{$payload}.{$sig}";
    }
}
