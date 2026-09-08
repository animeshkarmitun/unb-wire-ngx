<?php

namespace Database\Factories;

use App\Models\AiGeneration;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'user_id' => User::factory(),
            'kind' => fake()->randomElement(['preedit', 'tags', 'translate', 'generate']),
            'prompt_version' => 'v1',
            'model' => 'openai:gpt-4o',
            'input_hash' => hash('sha256', fake()->text()),
            'pack' => ['headline' => fake()->sentence(), 'brief' => fake()->sentence()],
            'new_facts' => null,
            'tokens_in' => fake()->numberBetween(50, 500),
            'tokens_out' => fake()->numberBetween(100, 1000),
            'cost_micros' => fake()->numberBetween(500, 5000),
            'applied' => null,
            'created_at' => now(),
        ];
    }
}
