<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'shot_list' => [['item' => fake()->sentence(2)]],
            'location' => fake()->city(),
            'due_at' => now()->addDay(),
            'priority' => fake()->randomElement(['routine','urgent','flash']),
            'status' => 'open',
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
