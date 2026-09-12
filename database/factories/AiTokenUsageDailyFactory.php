<?php

namespace Database\Factories;

use App\Models\AiTokenUsageDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiTokenUsageDailyFactory extends Factory
{
    protected $model = AiTokenUsageDaily::class;

    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'scope' => 'desk:en',
            'kind' => 'preedit',
            'tokens' => fake()->numberBetween(100, 5000),
            'cost_micros' => fake()->numberBetween(500, 25000),
        ];
    }
}
