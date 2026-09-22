<?php

namespace Tests\Feature\Repositories;

use App\Models\Role;
use App\Models\User;
use App\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RoleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(RoleRepository::class);
    }

    public function test_find_or_fail_returns_role(): void
    {
        $role = Role::factory()->create();

        $result = $this->repo->findOrFail($role->id);

        $this->assertEquals($role->id, $result->id);
    }

    public function test_find_or_fail_throws_for_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->repo->findOrFail(9999);
    }

    public function test_find_with_permissions_loads_relation(): void
    {
        $role = Role::factory()->create();

        $result = $this->repo->findWithPermissions($role->id);

        $this->assertTrue($result->relationLoaded('permissions'));
    }

    public function test_find_with_users_loads_relation(): void
    {
        $role = Role::factory()->create();

        $result = $this->repo->findWithUsers($role->id);

        $this->assertTrue($result->relationLoaded('users'));
    }

    public function test_all_with_user_counts_returns_collection(): void
    {
        Role::factory()->create();

        $result = $this->repo->allWithUserCounts();

        $this->assertNotEmpty($result);
        $this->assertTrue($result->first()->relationLoaded('permissions'));
    }

    public function test_first_returns_first_role(): void
    {
        $role = Role::factory()->create();

        $result = $this->repo->first();

        $this->assertEquals($role->id, $result->id);
    }

    public function test_create_creates_role(): void
    {
        $result = $this->repo->create(['name' => 'Test Role', 'type' => 'custom']);

        $this->assertInstanceOf(Role::class, $result);
        $this->assertDatabaseHas('roles', ['name' => 'Test Role']);
    }

    public function test_update_updates_role(): void
    {
        $role = Role::factory()->create(['name' => 'Old Name']);

        $result = $this->repo->update($role, ['name' => 'New Name']);

        $this->assertEquals('New Name', $result->name);
    }

    public function test_delete_deletes_role(): void
    {
        $role = Role::factory()->create();

        $result = $this->repo->delete($role);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_sync_permissions_creates_permissions(): void
    {
        $role = Role::factory()->create();
        $modules = [['id' => 'stories'], ['id' => 'media']];
        $permissions = [
            'stories' => ['can_view' => true, 'can_edit' => true],
            'media' => ['can_view' => true],
        ];

        $this->repo->syncPermissions($role->id, $modules, $permissions);

        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'module' => 'stories', 'can_view' => true, 'can_edit' => true]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $role->id, 'module' => 'media', 'can_view' => true]);
    }

    public function test_clone_permissions_copies_from_source(): void
    {
        $source = Role::factory()->create();
        $target = Role::factory()->create();

        $source->permissions()->create(['module' => 'stories', 'can_view' => true, 'can_edit' => true]);

        $this->repo->clonePermissions($source->id, $target->id);

        $this->assertDatabaseHas('role_permissions', ['role_id' => $target->id, 'module' => 'stories', 'can_view' => true, 'can_edit' => true]);
    }

    public function test_get_all_staff_returns_users(): void
    {
        User::factory()->create(['name' => 'Test User']);

        $result = $this->repo->getAllStaff();

        $this->assertNotEmpty($result);
        $this->assertTrue($result->first()->relationLoaded('role'));
    }

    public function test_find_user_returns_user(): void
    {
        $user = User::factory()->create();

        $result = $this->repo->findUser($user->id);

        $this->assertEquals($user->id, $result->id);
    }

    public function test_create_user_creates_user(): void
    {
        $result = $this->repo->createUser(['name' => 'New User', 'email' => 'new@test.com', 'password' => 'password']);

        $this->assertInstanceOf(User::class, $result);
        $this->assertDatabaseHas('users', ['email' => 'new@test.com']);
    }

    public function test_update_user_updates_user(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        $result = $this->repo->updateUser($user, ['name' => 'New']);

        $this->assertEquals('New', $result->name);
    }

    public function test_get_recent_audits_returns_collection(): void
    {
        $result = $this->repo->getRecentAudits();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_log_audit_inserts_record(): void
    {
        $this->repo->logAudit('test.action', 'role', 1, ['message' => 'Test']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'test.action', 'entity_type' => 'role', 'entity_id' => 1]);
    }
}
