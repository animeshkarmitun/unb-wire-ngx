<?php

namespace Database\Factories;

use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UploadSessionFactory extends Factory
{
    protected $model = UploadSession::class;

    public function definition(): array
    {
        $filename = Str::random(8).'.jpg';

        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'kind' => 'photo',
            'filename' => $filename,
            'size_bytes' => fake()->numberBetween(100000, 10000000),
            'offset_bytes' => 0,
            'status' => 'pending',
            'meta' => null,
            'expires_at' => now()->addHour(),
        ];
    }
}
