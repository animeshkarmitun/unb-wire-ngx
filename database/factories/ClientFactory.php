<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('CLI-????')),
            'type' => fake()->randomElement(['newspaper', 'tv', 'online', 'radio', 'govt', 'agency']),
            'status' => 'active',
        ];
    }
}
