<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoryFactory extends Factory
{
    protected $model = Story::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'language' => fake()->randomElement(['en', 'bn']),
            'status' => 'draft',
            'headline' => fake()->sentence(6),
            'sub_head' => fake()->sentence(4),
            'brief' => fake()->sentence(10),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'body_text' => fake()->paragraph(),
            'category_id' => Category::factory(),
            'dateline_city' => 'Dhaka',
            'is_breaking' => false,
            'priority' => 'routine',
            'source' => 'desk',
            'owner_id' => User::factory(),
            'created_by' => User::factory(),
            'version' => 1,
            'ai_touched' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }
}
