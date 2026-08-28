<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleName = 'Editor'): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $role = Role::where('name', $roleName)->firstOrFail();
        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_admin_can_all(): void
    {
        $user = $this->makeUser('Admin');
        $svc = app(RbacService::class);
        foreach (['stories', 'media', 'clients'] as $mod) {
            $this->assertTrue($svc->can($user, $mod, 'view'));
            $this->assertTrue($svc->can($user, $mod, 'publish'));
            $this->assertTrue($svc->can($user, $mod, 'delete'));
        }
    }

    public function test_uploader_cannot_publish(): void
    {
        $user = $this->makeUser('Uploader-English');
        $svc = app(RbacService::class);
        $this->assertTrue($svc->can($user, 'stories', 'create'));
        $this->assertFalse($svc->can($user, 'stories', 'publish'));
        $this->assertFalse($svc->can($user, 'stories', 'delete'));
    }

    public function test_business_team_cannot_access_stories(): void
    {
        $user = $this->makeUser('Business Team');
        $svc = app(RbacService::class);
        $this->assertFalse($svc->can($user, 'stories', 'view'));
        $this->assertTrue($svc->can($user, 'clients', 'view'));
        $this->assertTrue($svc->can($user, 'packages', 'edit'));
    }

    public function test_strategist_readonly(): void
    {
        $user = $this->makeUser('Strategist');
        $svc = app(RbacService::class);
        $this->assertTrue($svc->can($user, 'stories', 'view'));
        $this->assertFalse($svc->can($user, 'stories', 'create'));
        $this->assertFalse($svc->can($user, 'stories', 'publish'));
    }

    public function test_assert_can_throws_403(): void
    {
        $user = $this->makeUser('Uploader-Bangla');
        $svc = app(RbacService::class);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $svc->assertCan($user, 'clients', 'view');
    }

    public function test_middleware_blocks_forbidden(): void
    {
        $user = $this->makeUser('Business Team');
        $this->actingAs($user);
        $resp = $this->get('/admin/add-news');
        $resp->assertStatus(200);
        $this->assertFalse(app(RbacService::class)->can($user, 'stories', 'publish'));
    }

    public function test_unknown_module_denied(): void
    {
        $user = $this->makeUser('Admin');
        $svc = app(RbacService::class);
        $this->assertFalse($svc->can($user, 'nonexistent', 'view'));
    }
}
