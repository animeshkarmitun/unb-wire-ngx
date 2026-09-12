<?php

namespace Database\Factories;

use App\Models\IndexOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IndexOutboxFactory extends Factory
{
    protected $model = IndexOutbox::class;

    public function definition(): array
    {
        return [
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => (string) Str::ulid(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'processed_at' => null,
        ];
    }
}
