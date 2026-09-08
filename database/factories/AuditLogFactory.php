<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_type' => 'user',
            'actor_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted', 'published', 'login']),
            'entity_type' => fake()->randomElement(['story', 'client', 'media', 'user']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'diff' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
