<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\StoryVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoryVersionFactory extends Factory
{
    protected $model = StoryVersion::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'version' => 1,
            'snapshot' => ['headline' => fake()->sentence(), 'body_html' => '<p>'.fake()->paragraph().'</p>'],
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
