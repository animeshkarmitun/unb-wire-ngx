<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 100, 2000);

        return [
            'invoice_id' => Invoice::factory(),
            'package_id' => Package::factory(),
            'description' => fake()->words(3, true),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => $qty * $unitPrice,
        ];
    }
}
