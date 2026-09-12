<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'slug' => substr(fake()->unique()->slug(2), 0, 50),
            'name_en' => fake()->words(2, true),
            'name_bn' => fake()->words(2, true),
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }
}
