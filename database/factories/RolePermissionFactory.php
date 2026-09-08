<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

class RolePermissionFactory extends Factory
{
    protected $model = RolePermission::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'module' => 'stories',
            'can_view' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_publish' => false,
            'can_delete' => false,
        ];
    }
}
