<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Pixel 7', 'iPhone 15', 'Galaxy S24', 'iPad Pro']),
            'platform' => fake()->randomElement(['android', 'ios', 'web']),
            'app_version' => '1.'.fake()->numberBetween(0, 9).'.'.fake()->numberBetween(0, 99),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }
}
