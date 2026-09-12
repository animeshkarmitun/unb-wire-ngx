<?php

namespace Database\Factories;

use App\Models\MediaBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaBatchFactory extends Factory
{
    protected $model = MediaBatch::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'uploader_id' => User::factory(),
            'assignment_id' => null,
            'event_label' => fake()->words(3, true),
            'urgency' => fake()->randomElement(['routine', 'urgent']),
            'status' => 'pending',
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
