<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\MediaReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaReviewFactory extends Factory
{
    protected $model = MediaReview::class;

    public function definition(): array
    {
        return [
            'asset_id' => MediaAsset::factory(),
            'reviewer_id' => User::factory(),
            'action' => fake()->randomElement(['approved', 'rejected']),
            'reason_code' => fake()->randomElement([null, 'quality', 'duplicate', 'rights', 'offtopic']),
            'note' => fake()->optional()->sentence(),
            'created_at' => now(),
        ];
    }
}
