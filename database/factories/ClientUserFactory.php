<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class ClientUserFactory extends Factory
{
    protected $model = ClientUser::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'client_role_id' => Role::where('type', 'client')->first()?->id ?? Role::factory(),
            'status' => 'active',
            'last_login_at' => null,
        ];
    }
}
