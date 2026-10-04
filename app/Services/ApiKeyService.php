<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientApiKey;
use App\Repositories\AuditLogRepository;
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
        app(AuditLogRepository::class)->log('api_key.issued', 'ClientApiKey', (int) $key->id, ['client_id' => $client->id, 'name' => $name]);

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
        app(AuditLogRepository::class)->log('api_key.rotated', 'ClientApiKey', (int) $new->id, ['client_id' => $old->client_id, 'replaced_id' => $old->id]);

        return [$new, $raw];
    }

    public function revoke(ClientApiKey $key): void
    {
        $key->update(['revoked_at' => now()]);
        app(AuditLogRepository::class)->log('api_key.revoked', 'ClientApiKey', (int) $key->id, ['client_id' => $key->client_id]);
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
