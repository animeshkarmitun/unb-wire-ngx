<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleRepository
{
    // ─── Roles ─────────────────────────────────────────────────

    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    public function findWithPermissions(int $id): ?Role
    {
        return Role::with('permissions')->find($id);
    }

    public function findWithUsers(int $id): ?Role
    {
        return Role::with('users')->find($id);
    }

    public function allWithUserCounts(): Collection
    {
        return Role::with(['permissions', 'users'])->withCount('users')->orderBy('id')->get();
    }

    public function first(): ?Role
    {
        return Role::first();
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->refresh();
    }

    public function delete(Role $role): bool
    {
        return $role->delete();
    }

    // ─── Permissions ───────────────────────────────────────────

    public function syncPermissions(int $roleId, array $modules, array $permissions): void
    {
        $role = Role::findOrFail($roleId);
        $role->permissions()->delete();

        foreach ($modules as $module) {
            $p = $permissions[$module['id']] ?? [];
            $role->permissions()->create([
                'module' => $module['id'],
                'can_view' => $p['can_view'] ?? false,
                'can_create' => $p['can_create'] ?? false,
                'can_edit' => $p['can_edit'] ?? false,
                'can_publish' => $p['can_publish'] ?? false,
                'can_delete' => $p['can_delete'] ?? false,
            ]);
        }
    }

    public function clonePermissions(int $sourceRoleId, int $targetRoleId): void
    {
        $src = Role::with('permissions')->findOrFail($sourceRoleId);
        $target = Role::findOrFail($targetRoleId);

        $target->permissions()->delete();

        foreach ($src->permissions as $p) {
            $target->permissions()->create([
                'module' => $p->module,
                'can_view' => $p->can_view,
                'can_create' => $p->can_create,
                'can_edit' => $p->can_edit,
                'can_publish' => $p->can_publish,
                'can_delete' => $p->can_delete,
            ]);
        }
    }

    // ─── Users (Staff) ─────────────────────────────────────────

    public function getAllStaff(): Collection
    {
        return User::with('role')->orderBy('name')->get();
    }

    public function findUser(int $id): ?User
    {
        return User::find($id);
    }

    public function findUserOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function getClientRoleId(): ?int
    {
        return Role::where('type', 'client')->value('id');
    }

    // ─── Audit Logs ────────────────────────────────────────────

    public function getRecentAudits(int $limit = 15): \Illuminate\Support\Collection
    {
        return DB::table('audit_logs')->orderByDesc('created_at')->limit($limit)->get();
    }

    public function logAudit(string $action, string $entityType, ?int $entityId, array $diff): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff' => json_encode($diff),
            'created_at' => now(),
        ]);
    }
}
