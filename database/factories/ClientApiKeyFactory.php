<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientApiKeyFactory extends Factory
{
    protected $model = ClientApiKey::class;

    public function definition(): array
    {
        $raw = 'unb_live_'.Str::random(16);

        return [
            'client_id' => Client::factory(),
            'name' => 'Default Wire API Key',
            'key_hash' => hash('sha256', $raw),
            'scopes' => ['feed:read', 'media:download'],
            'rate_limit_rpm' => 60,
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }
}
