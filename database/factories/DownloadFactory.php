<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadFactory extends Factory
{
    protected $model = Download::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'client_user_id' => null,
            'item_type' => fake()->randomElement(['story', 'media']),
            'item_id' => fake()->numberBetween(1, 1000),
            'format' => fake()->randomElement(['xml', 'json', 'jpg', 'png']),
            'size_bytes' => fake()->numberBetween(1000, 5000000),
            'ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
