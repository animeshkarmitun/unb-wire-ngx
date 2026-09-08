<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'kind' => 'photo',
            'status' => 'library',
            'batch_id' => null,
            'title' => fake()->words(3, true),
            'caption' => fake()->sentence(),
            'credit_line' => 'UNB',
            'photographer_id' => null,
            'source' => 'desk',
            'category_id' => null,
            'event_label' => null,
            'location_city' => 'Dhaka',
            'location_country' => 'BD',
            'captured_at' => now(),
            'en_tags' => null,
            'width' => 1920,
            'height' => 1080,
            'duration_ms' => null,
            'mime' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50000, 5000000),
            'checksum' => hash('sha256', Str::random()),
            'storage_disk' => 's3',
            'original_path' => 'photos/'.Str::random(8).'.jpg',
            'derivatives' => null,
            'exif' => null,
            'embargo_until' => null,
            'uploaded_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'download_count' => 0,
        ];
    }

    public function photo(): static
    {
        return $this->state(fn () => ['kind' => 'photo', 'mime' => 'image/jpeg']);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'kind' => 'video',
            'mime' => 'video/mp4',
            'width' => 1920,
            'height' => 1080,
            'duration_ms' => fake()->numberBetween(5000, 120000),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'library',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'field']);
    }
}
