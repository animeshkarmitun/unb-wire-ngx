<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class RolesManager extends Component
{
    public string $activeTab = 'roles';

    // Role Drawer State
    public ?int $editingRoleId = null;

    public string $editName = '';

    public string $editDesc = '';

    public string $editGrad = 'g1';

    public bool $editLocked = false;

    public string $editRoleType = 'custom';

    public array $editPerms = [];

    // New Role Modal State
    public bool $showNewModal = false;

    public string $newName = '';

    public string $newDesc = '';

    public string $newGrad = 'g1';

    public string $newCopyFrom = '';

    // Delete Role Modal State
    public bool $showDeleteModal = false;

    public ?int $deleteRoleId = null;

    public string $deleteRoleName = '';

    public int $deleteRoleMemberCount = 0;

    public string $deleteRoleMemberNames = '';

    // Invite Modal State
    public bool $showInviteModal = false;

    public string $invName = '';

    public string $invEmail = '';

    public string $invDesk = 'English desk';

    public string $invRole = '';

    public array $modules = [
        ['id' => 'stories', 'label' => 'English News', 'short' => 'English', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'stories_bn', 'label' => 'Bangla News', 'short' => 'Bangla', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'media', 'label' => 'UNB Photos', 'short' => 'Photos', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'clients', 'label' => 'Clients & distribution', 'short' => 'Clients', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'packages', 'label' => 'Packages', 'short' => 'Packages', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'distribution', 'label' => 'Distribution log', 'short' => 'Distribution', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'settings', 'label' => 'Settings & admin', 'short' => 'Settings', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'ai', 'label' => 'AI settings', 'short' => 'AI', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
    ];

    public array $dangerActions = ['delete'];

    public function mount(): void
    {
        $firstRole = Role::first();
        if ($firstRole) {
            $this->invRole = (string) $firstRole->id;
            $this->newCopyFrom = (string) $firstRole->id;
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['roles', 'people', 'audit'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function getRoleGradient(Role $role): string
    {
        $map = [
            'Admin' => 'g2',
            'Editor' => 'g1',
            'Strategist' => 'g5',
            'Admin Report' => 'g6',
            'Business Team' => 'g4',
            'Client Bangla (Without AP)' => 'g7',
            'Uploader-Bangla' => 'g3',
            'Uploader-English' => 'g8',
        ];

        return $map[$role->name] ?? ('g'.((abs(crc32($role->name)) % 8) + 1));
    }

    public function getUserGradient(int $index): string
    {
        $grads = ['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'];

        return $grads[$index % 8];
    }

    public function getInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if ($w !== '') {
                $initials .= mb_substr($w, 0, 1);
            }
        }
        if (mb_strlen($initials) === 1 && mb_strlen($name) >= 2) {
            $initials = mb_substr($name, 0, 2);
        }

        return mb_strtoupper(mb_substr($initials, 0, 2));
    }

    // ================= ROLE DRAWER =================

    public function openDrawer(int $roleId): void
    {
        $role = Role::with(['permissions', 'users'])->findOrFail($roleId);

        $this->editingRoleId = $roleId;
        $this->editName = $role->name;
        $this->editDesc = $role->description ?? '';
        $this->editGrad = $this->getRoleGradient($role);
        $this->editLocked = (bool) $role->is_locked;
        $this->editRoleType = $role->type;

        $perms = [];
        foreach ($this->modules as $m) {
            $p = $role->permissions->firstWhere('module', $m['id']);
            foreach ($m['actions'] as $a) {
                $perms[$m['id']][$a] = $p ? (bool) $p->{'can_'.$a} : false;
            }
        }
        $this->editPerms = $perms;
    }

    public function closeDrawer(): void
    {
        $this->editingRoleId = null;
    }

    public function setEditGrad(string $grad): void
    {
        if (in_array($grad, ['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'], true)) {
            $this->editGrad = $grad;
        }
    }

    public function toggleModuleAll(string $moduleId): void
    {
        if ($this->editLocked) {
            return;
        }

        $mod = collect($this->modules)->firstWhere('id', $moduleId);
        if (! $mod) {
            return;
        }

        $allChecked = true;
        foreach ($mod['actions'] as $act) {
            if (empty($this->editPerms[$moduleId][$act])) {
                $allChecked = false;
                break;
            }
        }

        foreach ($mod['actions'] as $act) {
            $this->editPerms[$moduleId][$act] = ! $allChecked;
        }
    }

    public function applyPreset(string $preset): void
    {
        if ($this->editLocked) {
            return;
        }

        foreach ($this->modules as $m) {
            $mid = $m['id'];
            foreach ($m['actions'] as $a) {
                if ($preset === 'clear') {
                    $this->editPerms[$mid][$a] = false;
                } elseif ($preset === 'full') {
                    $this->editPerms[$mid][$a] = true;
                } elseif ($preset === 'view') {
                    $this->editPerms[$mid][$a] = ($a === 'view');
                } elseif ($preset === 'uploader') {
                    if (in_array($mid, ['stories', 'stories_bn'], true)) {
                        $this->editPerms[$mid][$a] = in_array($a, ['view', 'create', 'edit'], true);
                    } elseif ($mid === 'media') {
                        $this->editPerms[$mid][$a] = in_array($a, ['view', 'create', 'edit'], true);
                    } elseif (in_array($mid, ['clients', 'packages', 'distribution'], true)) {
                        $this->editPerms[$mid][$a] = ($a === 'view');
                    } else {
                        $this->editPerms[$mid][$a] = false;
                    }
                } elseif ($preset === 'editor') {
                    if (in_array($mid, ['stories', 'stories_bn'], true)) {
                        $this->editPerms[$mid][$a] = in_array($a, ['view', 'create', 'edit', 'publish'], true);
                    } elseif ($mid === 'media') {
                        $this->editPerms[$mid][$a] = in_array($a, ['view', 'create', 'edit'], true);
                    } elseif (in_array($mid, ['clients', 'packages', 'distribution'], true)) {
                        $this->editPerms[$mid][$a] = ($a === 'view');
                    } else {
                        $this->editPerms[$mid][$a] = false;
                    }
                }
            }
        }
    }

    public function saveRole(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        $role = Role::findOrFail($this->editingRoleId);
        if ($role->is_locked) {
            abort(403, 'System role cannot be modified');
        }

        $this->validate([
            'editName' => 'required|string|max:80|unique:roles,name,'.$role->id,
            'editDesc' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($role) {
            $role->update([
                'name' => trim($this->editName),
                'description' => trim($this->editDesc),
            ]);

            foreach ($this->modules as $m) {
                $mid = $m['id'];
                $data = [
                    'can_view' => ! empty($this->editPerms[$mid]['view']),
                    'can_create' => ! empty($this->editPerms[$mid]['create']),
                    'can_edit' => ! empty($this->editPerms[$mid]['edit']),
                    'can_publish' => ! empty($this->editPerms[$mid]['publish']),
                    'can_delete' => ! empty($this->editPerms[$mid]['delete']),
                ];

                $role->permissions()->updateOrCreate(['module' => $mid], $data);
            }

            DB::table('audit_logs')->insert([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'role.updated',
                'entity_type' => 'role',
                'entity_id' => $role->id,
                'diff' => json_encode([
                    'message' => '<b>'.e(auth()->user()->name ?? 'Admin').'</b> updated role <b>'.e($role->name).'</b>',
                    'color' => 'var(--crimson, #e5484d)',
                ]),
                'created_at' => now(),
            ]);
        });

        $this->editingRoleId = null;
        $this->dispatch('toast', message: 'Role saved');
    }

    // ================= DUPLICATE ROLE =================

    public function duplicateRole(int $roleId, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'create');

        $src = Role::with('permissions')->findOrFail($roleId);

        $baseName = $src->name.' (copy)';
        $newName = $baseName;
        $counter = 2;
        while (Role::where('name', $newName)->exists()) {
            $newName = $src->name.' (copy '.$counter.')';
            $counter++;
        }

        $newRole = DB::transaction(function () use ($src, $newName) {
            $role = Role::create([
                'name' => $newName,
                'type' => $src->type === 'client' ? 'client' : 'custom',
                'description' => $src->description,
                'is_locked' => false,
            ]);

            foreach ($this->modules as $m) {
                $p = $src->permissions->firstWhere('module', $m['id']);
                $role->permissions()->create([
                    'module' => $m['id'],
                    'can_view' => $p?->can_view ?? false,
                    'can_create' => $p?->can_create ?? false,
                    'can_edit' => $p?->can_edit ?? false,
                    'can_publish' => $p?->can_publish ?? false,
                    'can_delete' => $p?->can_delete ?? false,
                ]);
            }

            DB::table('audit_logs')->insert([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'role.created',
                'entity_type' => 'role',
                'entity_id' => $role->id,
                'diff' => json_encode([
                    'message' => 'Role <b>'.e($role->name).'</b> created — copied from <b>'.e($src->name).'</b>',
                    'color' => 'var(--green, #16a34a)',
                ]),
                'created_at' => now(),
            ]);

            return $role;
        });

        $this->dispatch('toast', message: 'Duplicated as '.$newRole->name);
    }

    // ================= DELETE ROLE =================

    public function openDelete(int $roleId): void
    {
        $role = Role::with('users')->findOrFail($roleId);
        if ($role->is_locked) {
            $this->dispatch('toast', message: 'System role cannot be deleted');

            return;
        }

        $this->deleteRoleId = $roleId;
        $this->deleteRoleName = $role->name;
        $this->deleteRoleMemberCount = $role->users->count();
        $this->deleteRoleMemberNames = $role->users->pluck('name')->implode(', ');
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteRoleId = null;
    }

    public function deleteRole(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'delete');

        $role = Role::withCount('users')->findOrFail($this->deleteRoleId);
        if ($role->is_locked) {
            abort(403, 'System role cannot be deleted');
        }

        if ($role->users_count > 0) {
            $this->dispatch('toast', message: 'Reassign members before deleting');

            return;
        }

        DB::transaction(function () use ($role) {
            $roleName = $role->name;
            $role->permissions()->delete();
            $role->delete();

            DB::table('audit_logs')->insert([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'role.deleted',
                'entity_type' => 'role',
                'entity_id' => null,
                'diff' => json_encode([
                    'message' => 'Role <b>'.e($roleName).'</b> deleted',
                    'color' => '#b7791f',
                ]),
                'created_at' => now(),
            ]);
        });

        $this->showDeleteModal = false;
        $this->deleteRoleId = null;
        $this->dispatch('toast', message: 'Role deleted');
    }

    // ================= CREATE ROLE =================

    public function openNewRole(): void
    {
        $this->newName = '';
        $this->newDesc = '';
        $this->newGrad = 'g1';
        $first = Role::first();
        $this->newCopyFrom = $first?->id ? (string) $first->id : '';
        $this->showNewModal = true;
    }

    public function closeNewModal(): void
    {
        $this->showNewModal = false;
    }

    public function setNewGrad(string $grad): void
    {
        if (in_array($grad, ['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'], true)) {
            $this->newGrad = $grad;
        }
    }

    public function createRole(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'create');

        $this->validate([
            'newName' => 'required|string|max:80|unique:roles,name',
            'newDesc' => 'nullable|string|max:255',
        ]);

        $src = $this->newCopyFrom ? Role::with('permissions')->find($this->newCopyFrom) : null;

        $role = DB::transaction(function () use ($src) {
            $r = Role::create([
                'name' => trim($this->newName),
                'type' => 'custom',
                'description' => trim($this->newDesc) ?: 'Custom role',
                'is_locked' => false,
            ]);

            foreach ($this->modules as $m) {
                $p = $src?->permissions->firstWhere('module', $m['id']);
                $r->permissions()->create([
                    'module' => $m['id'],
                    'can_view' => $p?->can_view ?? false,
                    'can_create' => $p?->can_create ?? false,
                    'can_edit' => $p?->can_edit ?? false,
                    'can_publish' => $p?->can_publish ?? false,
                    'can_delete' => $p?->can_delete ?? false,
                ]);
            }

            DB::table('audit_logs')->insert([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'role.created',
                'entity_type' => 'role',
                'entity_id' => $r->id,
                'diff' => json_encode([
                    'message' => 'Role <b>'.e($r->name).'</b> created'.($src ? ' — copied from <b>'.e($src->name).'</b>' : ''),
                    'color' => 'var(--green, #16a34a)',
                ]),
                'created_at' => now(),
            ]);

            return $r;
        });

        $this->showNewModal = false;
        $this->dispatch('toast', message: 'Role '.$role->name.' created');

        // Immediately open drawer to fine-tune (1:1 with prototype)
        $this->openDrawer($role->id);
    }

    // ================= PEOPLE MANAGEMENT =================

    public function updateUserRole(int $userId, int $roleId, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        if ($userId === auth()->id()) {
            $this->dispatch('toast', message: 'You cannot change your own role');

            return;
        }

        $target = User::findOrFail($userId);
        $newRole = Role::findOrFail($roleId);

        $target->update(['role_id' => $newRole->id]);

        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.role_changed',
            'entity_type' => 'user',
            'entity_id' => $target->id,
            'diff' => json_encode([
                'message' => '<b>'.e($target->name).'</b> moved to role <b>'.e($newRole->name).'</b>',
                'color' => 'var(--blue, #3b6fe0)',
            ]),
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: $target->name.' is now '.$newRole->name);
    }

    public function deactivateUser(int $userId, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        if ($userId === auth()->id()) {
            $this->dispatch('toast', message: 'You cannot deactivate your own account');

            return;
        }

        $target = User::findOrFail($userId);
        $target->update(['status' => 'deactivated']);

        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.deactivated',
            'entity_type' => 'user',
            'entity_id' => $target->id,
            'diff' => json_encode([
                'message' => '<b>'.e($target->name).'</b> deactivated — access revoked',
                'color' => '#b7791f',
            ]),
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: $target->name.' deactivated');
    }

    public function activateUser(int $userId, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');

        $target = User::findOrFail($userId);
        $target->update([
            'status' => 'active',
            'last_seen_at' => now(),
        ]);

        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.activated',
            'entity_type' => 'user',
            'entity_id' => $target->id,
            'diff' => json_encode([
                'message' => '<b>'.e($target->name).'</b> reactivated',
                'color' => 'var(--green, #16a34a)',
            ]),
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: $target->name.' reactivated');
    }

    public function resendInvite(int $userId): void
    {
        $target = User::findOrFail($userId);
        $this->dispatch('toast', message: 'Invite re-sent to '.$target->email);
    }

    public function openInviteModal(): void
    {
        $this->invName = '';
        $this->invEmail = '';
        $this->invDesk = 'English desk';
        $first = Role::first();
        $this->invRole = $first?->id ? (string) $first->id : '';
        $this->showInviteModal = true;
    }

    public function closeInviteModal(): void
    {
        $this->showInviteModal = false;
    }

    public function inviteMember(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'create');

        $this->validate([
            'invName' => 'required|string|max:120',
            'invEmail' => 'required|email|unique:users,email',
            'invDesk' => 'required|string|max:40',
            'invRole' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail((int) $this->invRole);

        $user = User::create([
            'public_id' => (string) Str::ulid(),
            'name' => trim($this->invName),
            'email' => trim($this->invEmail),
            'desk' => $this->invDesk,
            'role_id' => $role->id,
            'password' => Hash::make(Str::random(16)),
            'status' => 'invited',
            'timezone' => 'Asia/Dhaka',
        ]);

        DB::table('audit_logs')->insert([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.invited',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'diff' => json_encode([
                'message' => '<b>'.e($user->name).'</b> invited as <b>'.e($role->name).'</b>',
                'color' => 'var(--blue, #3b6fe0)',
            ]),
            'created_at' => now(),
        ]);

        $this->showInviteModal = false;
        $this->dispatch('toast', message: 'Invite sent to '.$user->email);
    }

    // ================= RENDER =================

    public function render()
    {
        $roles = Role::with(['permissions', 'users'])->withCount('users')->orderBy('id')->get();
        $people = User::with('role')->orderBy('name')->get();
        $audits = DB::table('audit_logs')->orderByDesc('created_at')->limit(15)->get();

        $editingRole = $this->editingRoleId ? Role::with('users')->find($this->editingRoleId) : null;

        return view('livewire.admin.roles-manager', [
            'roles' => $roles,
            'people' => $people,
            'audits' => $audits,
            'editingRole' => $editingRole,
            'totalRoles' => $roles->count(),
            'totalPeople' => $people->where('status', '!=', 'deactivated')->count(),
            'customRoles' => $roles->where('type', 'custom')->count(),
            'pendingInvites' => $people->where('status', 'invited')->count(),
        ]);
    }
}
