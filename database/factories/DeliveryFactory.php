<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'deliverable_type' => 'story',
            'deliverable_id' => 1,
            'client_id' => Client::factory(),
            'channel_id' => ClientChannel::factory(),
            'status' => 'pending',
            'attempt_count' => 0,
            'idempotency_key' => Str::uuid()->toString(),
            'payload_hash' => hash('sha256', Str::random()),
            'response_code' => null,
            'error' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'created_at' => now(),
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'sent_at' => now()->subMinute(),
            'delivered_at' => now(),
            'response_code' => 200,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'attempt_count' => 3,
            'response_code' => 500,
            'error' => 'Connection timed out',
        ]);
    }
}
