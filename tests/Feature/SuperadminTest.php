<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolesManager;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperadminTest extends TestCase
{
    use RefreshDatabase;

    private function setUpRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(string $roleName = 'Editor'): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function makeSuperadmin(): User
    {
        $role = Role::where('name', 'Admin')->firstOrFail();

        return User::factory()->superadmin()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    public function test_superadmin_bypasses_rbac_checks(): void
    {
        $this->setUpRoles();
        $user = $this->makeSuperadmin();
        $svc = app(RbacService::class);

        $modules = ['stories', 'media', 'clients', 'packages', 'distribution', 'settings', 'ai', 'history', 'audit'];
        $actions = ['view', 'create', 'edit', 'publish', 'delete'];

        foreach ($modules as $mod) {
            foreach ($actions as $act) {
                $this->assertTrue($svc->can($user, $mod, $act), "Superadmin should have {$act} on {$mod}");
            }
        }
    }

    public function test_non_superadmin_respects_permission_matrix(): void
    {
        $this->setUpRoles();
        $user = $this->makeUser('Uploader-English');
        $svc = app(RbacService::class);

        $this->assertTrue($svc->can($user, 'stories', 'create'));
        $this->assertFalse($svc->can($user, 'stories', 'publish'));
        $this->assertFalse($svc->can($user, 'settings', 'view'));
    }

    public function test_non_superadmin_cannot_modify_locked_role(): void
    {
        $this->setUpRoles();
        $admin = $this->makeUser('Admin');
        $this->actingAs($admin);

        $lockedRole = Role::where('is_locked', true)->first();
        $this->assertNotNull($lockedRole);

        Livewire::test(RolesManager::class)
            ->call('openDrawer', $lockedRole->id)
            ->set('editName', 'Hacked Role')
            ->call('saveRole')
            ->assertStatus(403);
    }

    public function test_superadmin_can_modify_locked_role(): void
    {
        $this->setUpRoles();
        $superadmin = $this->makeSuperadmin();
        $this->actingAs($superadmin);

        $lockedRole = Role::where('is_locked', true)->first();
        $this->assertNotNull($lockedRole);

        Livewire::test(RolesManager::class)
            ->call('openDrawer', $lockedRole->id)
            ->set('editDesc', 'Updated by superadmin')
            ->call('saveRole')
            ->assertOk();

        $lockedRole->refresh();
        $this->assertEquals('Updated by superadmin', $lockedRole->description);
    }

    public function test_non_superadmin_cannot_toggle_superadmin(): void
    {
        $this->setUpRoles();
        $editor = $this->makeUser('Editor');
        $target = $this->makeUser('Uploader-English');
        $this->actingAs($editor);

        Livewire::test(RolesManager::class)
            ->call('toggleSuperadmin', $target->id)
            ->assertStatus(403);
    }

    public function test_superadmin_can_grant_superadmin(): void
    {
        $this->setUpRoles();
        $superadmin = $this->makeSuperadmin();
        $target = $this->makeUser('Editor');
        $this->actingAs($superadmin);

        Livewire::test(RolesManager::class)
            ->call('toggleSuperadmin', $target->id);

        $target->refresh();
        $this->assertTrue($target->is_superadmin);
    }

    public function test_superadmin_can_revoke_superadmin(): void
    {
        $this->setUpRoles();
        $superadmin = $this->makeSuperadmin();
        $target = $this->makeUser('Editor');
        $target->update(['is_superadmin' => true]);
        $this->actingAs($superadmin);

        Livewire::test(RolesManager::class)
            ->call('toggleSuperadmin', $target->id);

        $target->refresh();
        $this->assertFalse($target->is_superadmin);
    }

    public function test_last_superadmin_cannot_revoke_self(): void
    {
        $this->setUpRoles();
        $superadmin = $this->makeSuperadmin();
        $this->actingAs($superadmin);

        // Trying to revoke own superadmin via toggle (should be blocked by self-check)
        Livewire::test(RolesManager::class)
            ->call('toggleSuperadmin', $superadmin->id);

        $superadmin->refresh();
        $this->assertTrue($superadmin->is_superadmin);
    }

    public function test_non_superadmin_cannot_deactivate_superadmin(): void
    {
        $this->setUpRoles();
        $editor = $this->makeUser('Editor');
        $superadmin = $this->makeSuperadmin();
        $this->actingAs($editor);

        Livewire::test(RolesManager::class)
            ->call('deactivateUser', $superadmin->id)
            ->assertStatus(403);
    }

    public function test_non_superadmin_cannot_change_superadmin_role(): void
    {
        $this->setUpRoles();
        $editor = $this->makeUser('Editor');
        $superadmin = $this->makeSuperadmin();
        $otherRole = Role::where('name', 'Strategist')->first();
        $this->actingAs($editor);

        Livewire::test(RolesManager::class)
            ->call('updateUserRole', $superadmin->id, $otherRole->id)
            ->assertStatus(403);
    }

    public function test_role_seeder_marks_admin_users_as_superadmin(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('name', 'Admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        // Re-run seeder to apply superadmin flag
        $this->seed(RoleSeeder::class);

        $user->refresh();
        $this->assertTrue($user->is_superadmin);
    }

    public function test_is_superadmin_default_false(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->is_superadmin);
        $this->assertFalse($user->isSuperAdmin());
    }
}
