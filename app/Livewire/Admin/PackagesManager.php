<?php

namespace App\Livewire\Admin;

use App\Models\Package;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PackagesManager extends Component
{
    public bool $showCreate = false;
    public bool $showEdit = false;
    public ?int $editId = null;

    public string $code = '';
    public string $name = '';
    public string $kind = 'news';
    public string $description = '';
    public string $price = '';
    public string $status = 'active';
    public string $entitlement = '{"languages":["en","bn"],"category_ids":null,"media_kinds":["photo"]}';

    public ?int $archiveId = null;

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showCreate = true;
    }

    public function openEdit(int $id): void
    {
        $p = Package::findOrFail($id);
        $this->editId = $id;
        $this->code = $p->code;
        $this->name = $p->name;
        $this->kind = $p->kind;
        $this->description = $p->description ?? '';
        $this->price = (string) $p->price_monthly;
        $this->status = $p->status;
        $this->entitlement = json_encode($p->entitlement_filter, JSON_PRETTY_PRINT);
        $this->showEdit = true;
    }

    public function saveCreate(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'create');
        $this->validate([
            'code' => 'required|max:32|unique:packages,code',
            'name' => 'required|max:120',
            'kind' => 'required|in:news,photos,bundle',
            'price' => 'nullable|numeric|min:0',
            'entitlement' => 'required|json',
        ]);
        Package::create([
            'code' => $this->code, 'name' => $this->name, 'kind' => $this->kind,
            'description' => $this->description ?: null,
            'entitlement_filter' => json_decode($this->entitlement, true),
            'price_monthly' => $this->price !== '' ? $this->price : null,
            'status' => $this->status,
        ]);
        $this->showCreate = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Package created');
    }

    public function saveEdit(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');
        $p = Package::findOrFail($this->editId);
        $this->validate([
            'code' => 'required|max:32|unique:packages,code,'.$p->id,
            'name' => 'required|max:120',
            'kind' => 'required|in:news,photos,bundle',
            'price' => 'nullable|numeric|min:0',
            'entitlement' => 'required|json',
        ]);
        $p->update([
            'code' => $this->code, 'name' => $this->name, 'kind' => $this->kind,
            'description' => $this->description ?: null,
            'entitlement_filter' => json_decode($this->entitlement, true),
            'price_monthly' => $this->price !== '' ? $this->price : null,
            'status' => $this->status,
        ]);
        $this->showEdit = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Package updated');
    }

    public function archive(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');
        $p = Package::findOrFail($this->archiveId);
        $p->update(['status' => $p->status === 'active' ? 'archived' : 'active']);
        $this->archiveId = null;
        $this->dispatch('toast', message: 'Package status toggled');
    }

    public function delete(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'delete');
        $p = Package::withCount('clientPackages')->findOrFail($this->archiveId);
        if ($p->clientPackages_count > 0) {
            $this->dispatch('toast', message: 'Reassign clients before deleting');
            return;
        }
        $p->delete();
        $this->archiveId = null;
        $this->dispatch('toast', message: 'Package deleted');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->code = '';
        $this->name = '';
        $this->kind = 'news';
        $this->description = '';
        $this->price = '';
        $this->status = 'active';
        $this->entitlement = '{"languages":["en","bn"],"category_ids":null,"media_kinds":["photo"]}';
    }

    public function render()
    {
        $packages = Package::withCount('clientPackages')->orderBy('name')->get();
        $live = $packages->where('status', 'active')->count();
        $clientsCovered = DB::table('client_packages')->where('status', 'active')->distinct('client_id')->count('client_id');
        $mrr = $packages->where('status', 'active')->sum(function ($p) { return (float) $p->price_monthly * ($p->client_packages_count ?? 0); });
        return view('livewire.admin.packages-manager', compact('packages', 'live', 'clientsCovered', 'mrr'));
    }
}
