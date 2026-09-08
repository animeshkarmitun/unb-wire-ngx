<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientChannelFactory extends Factory
{
    protected $model = ClientChannel::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => 'email',
            'config' => ['list' => [fake()->safeEmail()], 'on' => true, 'health' => 'ok'],
            'status' => 'active',
            'failure_count' => 0,
            'last_success_at' => now(),
        ];
    }

    public function ftp(): static
    {
        return $this->state(fn () => [
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'unb_user',
                'port' => '21',
                'password' => 'password',
                'health' => 'ok',
            ],
        ]);
    }

    public function api(): static
    {
        return $this->state(fn () => [
            'type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/unb',
                'key' => 'unb_live_'.Str::random(16),
                'webhook' => '',
                'health' => 'ok',
            ],
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn () => [
            'failure_count' => 3,
            'status' => 'active',
        ]);
    }
}
