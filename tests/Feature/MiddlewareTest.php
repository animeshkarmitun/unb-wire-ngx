<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_rbac_returns_401_when_unauthenticated(): void
    {
        $this->get('/admin/news/en')
            ->assertRedirect('/login');
    }

    public function test_ensure_rbac_returns_403_when_unauthorized(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->create(['module' => 'stories', 'can_view' => false]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user);
        $this->get('/admin/news/en')
            ->assertStatus(403);
    }

    public function test_ensure_rbac_passes_when_authorized(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->create(['module' => 'stories', 'can_view' => true]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user);
        $this->get('/admin/news/en')
            ->assertOk();
    }

    public function test_ensure_rbac_superadmin_bypasses(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'is_superadmin' => true]);

        $this->actingAs($user);
        $this->get('/admin/news/en')
            ->assertOk();
    }

    public function test_ensure_client_api_key_returns_401_without_key(): void
    {
        $this->getJson('/api/v1/feed')
            ->assertStatus(401);
    }

    public function test_ensure_client_api_key_returns_401_with_invalid_key(): void
    {
        $this->withHeader('X-API-Key', 'invalid-key')
            ->getJson('/api/v1/feed')
            ->assertStatus(401);
    }
}
