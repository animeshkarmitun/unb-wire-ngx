<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\StoryNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoryNoteFactory extends Factory
{
    protected $model = StoryNote::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'user_id' => User::factory(),
            'kind' => fake()->randomElement(['note', 'system']),
            'is_internal' => true,
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
