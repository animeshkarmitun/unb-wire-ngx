<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 5000);
        $discount = 0;

        return [
            'public_id' => (string) Str::ulid(),
            'client_id' => Client::factory(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'status' => 'draft',
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'issued_at' => now()->subDays(15),
            'due_at' => now()->addDays(15),
            'paid_at' => now(),
        ]);
    }
}
