<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('PKG-????')),
            'name' => fake()->words(2, true),
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en', 'bn'], 'category_ids' => null, 'media_kinds' => null],
            'price_monthly' => fake()->randomFloat(2, 10, 500),
            'status' => 'active',
        ];
    }
}
