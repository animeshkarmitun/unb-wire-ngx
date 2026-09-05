<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolesManager;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolesManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_roles_manager_page_loads_for_admin(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin/roles');

        $response->assertStatus(200);
        $response->assertSeeLivewire(RolesManager::class);
        $response->assertSee('Roles &amp; access', false);
    }

    public function test_roles_manager_blocked_for_unauthorized_user(): void
    {
        $uploader = User::where('email', 'maria@unbnews.org')->firstOrFail();

        $response = $this->actingAs($uploader)->get('/admin/roles');

        $response->assertStatus(403);
    }

    public function test_roles_manager_computes_correct_stats(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->assertViewHas('totalRoles', 8)
            ->assertViewHas('customRoles', 6)
            ->assertViewHas('pendingInvites', 2)
            ->assertSee('Total roles')
            ->assertSee('People with access');
    }

    public function test_admin_can_open_drawer_and_save_role_permissions(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $editor = Role::where('name', 'Editor')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openDrawer', $editor->id)
            ->assertSet('editingRoleId', $editor->id)
            ->assertSet('editName', 'Editor')
            ->set('editDesc', 'Updated editor description')
            ->set('editPerms.stories.delete', true)
            ->call('saveRole')
            ->assertDispatched('toast', message: 'Role saved')
            ->assertSet('editingRoleId', null);

        $editor->refresh();
        $this->assertSame('Updated editor description', $editor->description);
        $perm = $editor->permissions()->where('module', 'stories')->first();
        $this->assertTrue((bool) $perm->can_delete);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.updated',
            'entity_type' => 'role',
            'entity_id' => $editor->id,
        ]);
    }

    public function test_system_role_cannot_be_modified_or_deleted(): void
    {
        $adminUser = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $this->assertTrue($adminRole->is_locked);

        Livewire::actingAs($adminUser)
            ->test(RolesManager::class)
            ->call('openDrawer', $adminRole->id)
            ->assertSet('editLocked', true)
            ->call('saveRole')
            ->assertForbidden();
    }

    public function test_admin_can_create_custom_role_with_copy_from(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $editor = Role::where('name', 'Editor')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openNewRole')
            ->assertSet('showNewModal', true)
            ->set('newName', 'Night Desk Editor')
            ->set('newDesc', 'Handles night cycle')
            ->set('newCopyFrom', (string) $editor->id)
            ->call('createRole')
            ->assertDispatched('toast', message: 'Role Night Desk Editor created')
            ->assertSet('showNewModal', false);

        $created = Role::where('name', 'Night Desk Editor')->first();
        $this->assertNotNull($created);
        $this->assertSame('custom', $created->type);
        $this->assertFalse($created->is_locked);

        // Permissions copied from Editor
        $storiesPerm = $created->permissions()->where('module', 'stories')->first();
        $this->assertTrue((bool) $storiesPerm->can_publish);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.created',
            'entity_id' => $created->id,
        ]);
    }

    public function test_admin_can_duplicate_role(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $strat = Role::where('name', 'Strategist')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('duplicateRole', $strat->id)
            ->assertDispatched('toast', message: 'Duplicated as Strategist (copy)');

        $copy = Role::where('name', 'Strategist (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame('custom', $copy->type);
        $this->assertFalse($copy->is_locked);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.created',
            'entity_id' => $copy->id,
        ]);
    }

    public function test_admin_cannot_delete_role_with_members(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $editor = Role::where('name', 'Editor')->firstOrFail();
        $this->assertGreaterThan(0, $editor->users()->count());

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openDelete', $editor->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deleteRoleMemberCount', $editor->users()->count())
            ->call('deleteRole')
            ->assertDispatched('toast', message: 'Reassign members before deleting');

        $this->assertDatabaseHas('roles', ['id' => $editor->id]);
    }

    public function test_admin_can_delete_role_without_members(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $role = Role::create([
            'name' => 'Empty Role',
            'type' => 'custom',
            'description' => 'Temporary',
            'is_locked' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openDelete', $role->id)
            ->assertSet('deleteRoleMemberCount', 0)
            ->call('deleteRole')
            ->assertDispatched('toast', message: 'Role deleted')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.deleted',
        ]);
    }

    public function test_apply_presets_in_drawer(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $role = Role::create([
            'name' => 'Test Custom Role',
            'type' => 'custom',
            'description' => 'For presets',
            'is_locked' => false,
        ]);

        $test = Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openDrawer', $role->id);

        // View only preset
        $test->call('applyPreset', 'view');
        $perms = $test->get('editPerms');
        $this->assertTrue($perms['stories']['view']);
        $this->assertFalse($perms['stories']['create']);
        $this->assertFalse($perms['stories']['publish']);

        // Full access preset
        $test->call('applyPreset', 'full');
        $perms = $test->get('editPerms');
        $this->assertTrue($perms['stories']['view']);
        $this->assertTrue($perms['stories']['create']);
        $this->assertTrue($perms['stories']['publish']);
        $this->assertTrue($perms['stories']['delete']);

        // Clear all preset
        $test->call('applyPreset', 'clear');
        $perms = $test->get('editPerms');
        $this->assertFalse($perms['stories']['view']);
        $this->assertFalse($perms['stories']['publish']);
    }

    public function test_update_user_role(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $target = User::where('email', 'shohel@unbnews.org')->firstOrFail();
        $strat = Role::where('name', 'Strategist')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('updateUserRole', $target->id, $strat->id)
            ->assertDispatched('toast', message: 'Shohel Ahmed is now Strategist');

        $this->assertSame($strat->id, $target->fresh()->role_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.role_changed',
            'entity_id' => $target->id,
        ]);
    }

    public function test_user_cannot_change_own_role(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $editor = Role::where('name', 'Editor')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('updateUserRole', $admin->id, $editor->id)
            ->assertDispatched('toast', message: 'You cannot change your own role');

        $this->assertNotSame($editor->id, $admin->fresh()->role_id);
    }

    public function test_deactivate_and_activate_user(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $target = User::where('email', 'shohel@unbnews.org')->firstOrFail();

        // Deactivate
        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('deactivateUser', $target->id)
            ->assertDispatched('toast', message: 'Shohel Ahmed deactivated');

        $this->assertSame('deactivated', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deactivated',
            'entity_id' => $target->id,
        ]);

        // Reactivate
        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('activateUser', $target->id)
            ->assertDispatched('toast', message: 'Shohel Ahmed reactivated');

        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.activated',
            'entity_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('deactivateUser', $admin->id)
            ->assertDispatched('toast', message: 'You cannot deactivate your own account');

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_invite_member_with_validation(): void
    {
        $admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $editor = Role::where('name', 'Editor')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(RolesManager::class)
            ->call('openInviteModal')
            ->assertSet('showInviteModal', true)
            ->set('invName', 'Farhana Yeasmin')
            ->set('invEmail', 'farhana@unbnews.org')
            ->set('invDesk', 'English desk')
            ->set('invRole', (string) $editor->id)
            ->call('inviteMember')
            ->assertDispatched('toast', message: 'Invite sent to farhana@unbnews.org')
            ->assertSet('showInviteModal', false);

        $invited = User::where('email', 'farhana@unbnews.org')->first();
        $this->assertNotNull($invited);
        $this->assertSame('invited', $invited->status);
        $this->assertSame($editor->id, $invited->role_id);
        $this->assertSame('English desk', $invited->desk);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.invited',
            'entity_id' => $invited->id,
        ]);
    }
}
