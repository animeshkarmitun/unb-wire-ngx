<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientPackageFactory extends Factory
{
    protected $model = ClientPackage::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'package_id' => Package::factory(),
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->addYear(),
            'status' => 'active',
            'created_at' => now(),
        ];
    }
}
