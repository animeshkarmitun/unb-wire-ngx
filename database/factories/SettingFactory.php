<?php

namespace Database\Factories;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'value' => ['enabled' => true],
            'updated_by' => User::factory(),
            'updated_at' => now(),
        ];
    }
}
