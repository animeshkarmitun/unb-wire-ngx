<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RolesManager extends Component
{
    public string $activeTab = 'roles';

    public ?int $editingRoleId = null;
    public string $editName = '';
    public string $editDesc = '';
    public array $editPerms = [];

    public string $newName = '';
    public string $newDesc = '';
    public string $newCopyFrom = '';
    public bool $showNewModal = false;

    public ?int $deleteId = null;

    public bool $showInviteModal = false;
    public string $invName = '';
    public string $invEmail = '';
    public string $invDesk = 'English desk';
    public string $invRole = '';

    public array $modules = [
        ['id' => 'stories', 'label' => 'English News', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'stories_bn', 'label' => 'Bangla News', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'media', 'label' => 'UNB Photos', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'clients', 'label' => 'Clients & distribution', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'packages', 'label' => 'Packages', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'distribution', 'label' => 'Distribution log', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'settings', 'label' => 'Settings & admin', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
        ['id' => 'ai', 'label' => 'AI settings', 'actions' => ['view', 'create', 'edit', 'publish', 'delete']],
    ];

    public function mount(): void
    {
        $first = Role::first();
        $this->invRole = $first?->id ? (string) $first->id : '';
        $this->newCopyFrom = $first?->id ? (string) $first->id : '';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openEdit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        if ($role->is_locked) {
            $this->dispatch('toast', message: 'System role is locked');
            return;
        }
        $this->editingRoleId = $id;
        $this->editName = $role->name;
        $this->editDesc = $role->description ?? '';
        $perms = [];
        foreach ($this->modules as $m) {
            $p = $role->permissions->firstWhere('module', $m['id']);
            foreach ($m['actions'] as $a) {
                $perms[$m['id']][$a] = $p ? (bool) $p->{'can_'.$a} : false;
            }
        }
        $this->editPerms = $perms;
    }

    public function closeEdit(): void
    {
        $this->editingRoleId = null;
    }

    public function saveEdit(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'edit');
        $role = Role::findOrFail($this->editingRoleId);
        if ($role->is_locked) {
            abort(403);
        }
        $this->validate(['editName' => 'required|string|max:80']);
        DB::transaction(function () use ($role) {
            $role->update(['name' => $this->editName, 'description' => $this->editDesc]);
            foreach ($this->modules as $m) {
                $row = $role->permissions()->where('module', $m['id'])->first();
                if (! $row) continue;
                $data = [];
                foreach ($m['actions'] as $a) {
                    $data['can_'.$a] = ! empty($this->editPerms[$m['id']][$a]);
                }
                $row->update($data);
            }
            DB::table('audit_logs')->insert([
                'actor_type' => 'user', 'actor_id' => auth()->id(), 'action' => 'role.updated',
                'entity_type' => 'role', 'entity_id' => $role->id, 'diff' => json_encode(['name' => $this->editName]),
                'created_at' => now(),
            ]);
        });
        $this->editingRoleId = null;
        $this->dispatch('toast', message: 'Role updated');
    }

    public function createRole(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'create');
        $this->validate(['newName' => 'required|string|max:80|unique:roles,name']);
        $copy = $this->newCopyFrom ? Role::with('permissions')->find($this->newCopyFrom) : null;
        DB::transaction(function () use ($copy) {
            $role = Role::create(['name' => $this->newName, 'type' => 'custom', 'description' => $this->newDesc, 'is_locked' => false]);
            foreach ($this->modules as $m) {
                $src = $copy?->permissions->firstWhere('module', $m['id']);
                $role->permissions()->create([
                    'module' => $m['id'],
                    'can_view' => $src?->can_view ?? false,
                    'can_create' => $src?->can_create ?? false,
                    'can_edit' => $src?->can_edit ?? false,
                    'can_publish' => $src?->can_publish ?? false,
                    'can_delete' => $src?->can_delete ?? false,
                ]);
            }
        });
        $this->showNewModal = false;
        $this->newName = '';
        $this->newDesc = '';
        $this->dispatch('toast', message: 'Role created');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function deleteRole(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'delete');
        $role = Role::withCount('users')->findOrFail($this->deleteId);
        if ($role->is_locked) abort(403, 'System role cannot be deleted');
        if ($role->users_count > 0) {
            $this->dispatch('toast', message: 'Reassign members before deleting');
            return;
        }
        $role->delete();
        $this->deleteId = null;
        $this->dispatch('toast', message: 'Role deleted');
    }

    public function invite(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'settings', 'create');
        $this->validate(['invName' => 'required|max:120', 'invEmail' => 'required|email|unique:users,email', 'invRole' => 'required|exists:roles,id']);
        User::create(['name' => $this->invName, 'email' => $this->invEmail, 'password' => bcrypt('password'), 'role_id' => $this->invRole, 'status' => 'invited', 'timezone' => 'Asia/Dhaka']);
        $this->showInviteModal = false;
        $this->invName = '';
        $this->invEmail = '';
        $this->dispatch('toast', message: 'Invite sent');
    }

    public function render()
    {
        $roles = Role::with(['permissions','users'])->withCount('users')->get();
        $people = User::with('role')->orderBy('name')->get();
        $audits = DB::table('audit_logs')->orderByDesc('created_at')->limit(10)->get();
        $pending = $people->where('status', 'invited')->count();
        return view('livewire.admin.roles-manager', [
            'roles' => $roles,
            'people' => $people,
            'audits' => $audits,
            'totalRoles' => $roles->count(),
            'totalPeople' => $people->count(),
            'customRoles' => $roles->where('type', 'custom')->count(),
            'pendingInvites' => $pending,
        ]);
    }
}
