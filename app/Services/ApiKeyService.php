<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientApiKey;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function issue(Client $client, string $name, array $scopes = ['feed:read'], int $rpm = 60): array
    {
        $raw = 'unb_'.Str::random(48);
        $key = ClientApiKey::create([
            'client_id' => $client->id,
            'name' => $name,
            'key_hash' => hash('sha256', $raw),
            'scopes' => $scopes,
            'rate_limit_rpm' => $rpm,
        ]);

        return [$key, $raw];
    }

    public function rotate(ClientApiKey $old): array
    {
        $raw = 'unb_'.Str::random(48);
        $new = ClientApiKey::create([
            'client_id' => $old->client_id,
            'name' => $old->name.'-rotated',
            'key_hash' => hash('sha256', $raw),
            'scopes' => $old->scopes,
            'rate_limit_rpm' => $old->rate_limit_rpm,
        ]);
        $old->update(['expires_at' => now()->addHour()]);

        return [$new, $raw];
    }

    public function revoke(ClientApiKey $key): void
    {
        $key->update(['revoked_at' => now()]);
    }

    public function authenticate(string $raw): ?ClientApiKey
    {
        $hash = hash('sha256', $raw);
        $key = ClientApiKey::with('client')->where('key_hash', $hash)->whereNull('revoked_at')->first();
        if (! $key) {
            return null;
        }
        if ($key->expires_at && $key->expires_at->isPast()) {
            return null;
        }
        if ($key->client->status !== 'active') {
            return null;
        }
        $key->update(['last_used_at' => now()]);

        return $key;
    }

    public function hasScope(ClientApiKey $key, string $scope): bool
    {
        return in_array($scope, (array) $key->scopes, true);
    }
}
