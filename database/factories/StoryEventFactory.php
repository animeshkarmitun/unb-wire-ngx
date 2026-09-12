<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\StoryEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoryEventFactory extends Factory
{
    protected $model = StoryEvent::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'actor_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'in_review', 'approved', 'published', 'note_added', 'take_over']),
            'from_status' => 'draft',
            'to_status' => 'in_review',
            'payload' => null,
            'created_at' => now(),
        ];
    }
}
