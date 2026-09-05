<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Services\RbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class PackagesManager extends Component
{
    // Modals visibility
    public bool $showPkgModal = false;

    public bool $showAoModal = false;

    public bool $showArchiveModal = false;

    // Package editor state
    public ?int $editingPkgId = null;

    public string $pkgName = '';

    public string $pkgPrice = '';

    public string $pkgGrad = 'g1';

    public string $pkgDesc = '';

    public string $pkgWire = 'Full wire — all categories';

    public string $pkgQuota = 'Unlimited';

    public bool $pkgVideo = true;

    public bool $pkgExcl = false;

    public bool $pkgApi = true;

    public bool $pkgSupport = false;

    // Add-on editor state
    public ?int $editingAoId = null;

    public string $aoName = '';

    public string $aoPrice = '';

    public string $aoDesc = '';

    public array $aoTiers = ['Premium', 'Standard'];

    // Archive / Reassign state
    public ?int $archiveTargetId = null;

    public ?int $reassignPkgId = null;

    // Public stats array for state assertion
    public array $stats = [];

    public function mount(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'view');
    }

    // ==========================================
    // PACKAGE EDITOR & ACTIONS
    // ==========================================

    public function openPkgModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->editingPkgId = $id;

        if ($id) {
            $p = Package::findOrFail($id);
            $filter = (array) ($p->entitlement_filter ?? []);

            $this->pkgName = $p->name;
            $this->pkgPrice = (string) (int) $p->price_monthly;
            $this->pkgDesc = $p->description ?? '';
            $this->pkgGrad = $filter['grad'] ?? 'g1';
            $this->pkgWire = $filter['wire'] ?? 'Full wire — all categories';
            $this->pkgQuota = $filter['quota'] ?? 'Unlimited';
            $this->pkgVideo = (bool) ($filter['video'] ?? true);
            $this->pkgExcl = (bool) ($filter['excl'] ?? false);
            $this->pkgApi = (bool) ($filter['api'] ?? true);
            $this->pkgSupport = (bool) ($filter['support'] ?? false);
        } else {
            $this->pkgName = '';
            $this->pkgPrice = '';
            $this->pkgDesc = '';
            $this->pkgGrad = 'g1';
            $this->pkgWire = 'Full wire — all categories';
            $this->pkgQuota = 'Unlimited';
            $this->pkgVideo = true;
            $this->pkgExcl = false;
            $this->pkgApi = true;
            $this->pkgSupport = false;
        }

        $this->showPkgModal = true;
    }

    public function closePkgModal(): void
    {
        $this->showPkgModal = false;
        $this->editingPkgId = null;
    }

    public function setPkgGrad(string $grad): void
    {
        if (in_array($grad, ['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'], true)) {
            $this->pkgGrad = $grad;
        }
    }

    public function savePkg(string $status, RbacService $rbac): void
    {
        if ($this->editingPkgId) {
            $rbac->assertCan(auth()->user(), 'packages', 'edit');
        } else {
            $rbac->assertCan(auth()->user(), 'packages', 'create');
        }

        $this->validate([
            'pkgName' => 'required|string|max:120',
            'pkgPrice' => 'required|numeric|min:0',
        ], [
            'pkgName.required' => 'Package name is required',
            'pkgPrice.required' => 'Set a monthly price',
            'pkgPrice.numeric' => 'Price must be a valid number',
        ]);

        $filter = [
            'is_addon' => false,
            'grad' => $this->pkgGrad,
            'wire' => $this->pkgWire,
            'quota' => $this->pkgQuota,
            'video' => $this->pkgVideo,
            'excl' => $this->pkgExcl,
            'api' => $this->pkgApi,
            'support' => $this->pkgSupport,
            'ui_status' => $status === 'live' ? 'live' : 'draft',
            'languages' => ['en', 'bn'],
            'media_kinds' => $this->pkgQuota === 'No photos' ? [] : ['photo', 'video'],
        ];

        $dbStatus = $status === 'live' ? 'active' : 'archived';

        if ($this->editingPkgId) {
            $pkg = Package::findOrFail($this->editingPkgId);
            $pkg->update([
                'name' => $this->pkgName,
                'price_monthly' => $this->pkgPrice,
                'description' => $this->pkgDesc ?: null,
                'entitlement_filter' => $filter,
                'status' => $dbStatus,
            ]);

            $this->dispatch('toast', message: '<b>'.e($this->pkgName).'</b> updated');
        } else {
            $baseCode = substr(strtoupper(Str::slug($this->pkgName)), 0, 28);
            $code = $baseCode;
            $counter = 1;
            while (Package::where('code', $code)->exists()) {
                $code = substr($baseCode, 0, 28).'-'.$counter++;
            }

            Package::create([
                'code' => $code,
                'name' => $this->pkgName,
                'kind' => 'bundle',
                'description' => $this->pkgDesc ?: null,
                'price_monthly' => $this->pkgPrice,
                'entitlement_filter' => $filter,
                'status' => $dbStatus,
            ]);

            $this->dispatch('toast', message: '<b>'.e($this->pkgName).'</b> created as '.($status === 'live' ? 'live' : 'draft'));
        }

        $this->closePkgModal();
    }

    public function duplicatePkg(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'create');

        $orig = Package::findOrFail($id);
        $filter = (array) ($orig->entitlement_filter ?? []);
        $filter['ui_status'] = 'draft';

        $newName = $orig->name.' (copy)';
        $baseCode = substr(strtoupper(Str::slug($newName)), 0, 28);
        $code = $baseCode;
        $counter = 1;
        while (Package::where('code', $code)->exists()) {
            $code = substr($baseCode, 0, 28).'-'.$counter++;
        }

        Package::create([
            'code' => $code,
            'name' => $newName,
            'kind' => $orig->kind,
            'description' => $orig->description,
            'price_monthly' => $orig->price_monthly,
            'entitlement_filter' => $filter,
            'status' => 'archived', // Draft in DB
        ]);

        $this->dispatch('toast', message: 'Duplicated as draft — <b>'.e($newName).'</b>');
    }

    public function openArchiveModal(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');

        $this->archiveTargetId = $id;
        $alt = Package::where('id', '!=', $id)
            ->where('status', 'active')
            ->get()
            ->first(function ($p) {
                $f = (array) ($p->entitlement_filter ?? []);

                return empty($f['is_addon']);
            });

        $this->reassignPkgId = $alt?->id;
        $this->showArchiveModal = true;
    }

    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
        $this->archiveTargetId = null;
        $this->reassignPkgId = null;
    }

    public function confirmArchive(RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');

        if (! $this->archiveTargetId) {
            return;
        }

        $pkg = Package::findOrFail($this->archiveTargetId);
        $activeClientCount = ClientPackage::where('package_id', $pkg->id)
            ->where('status', 'active')
            ->count();

        if ($activeClientCount > 0 && $this->reassignPkgId) {
            $targetPkg = Package::findOrFail($this->reassignPkgId);

            // Move active subscriptions to new package
            ClientPackage::where('package_id', $pkg->id)
                ->where('status', 'active')
                ->update(['package_id' => $targetPkg->id]);

            $this->dispatch('toast', message: '<b>'.$activeClientCount.'</b> clients moved to <b>'.e($targetPkg->name).'</b>');
        }

        $filter = (array) ($pkg->entitlement_filter ?? []);
        $filter['ui_status'] = 'archived';

        $pkg->update([
            'status' => 'archived',
            'entitlement_filter' => $filter,
        ]);

        $this->dispatch('toast', message: '<b>'.e($pkg->name).'</b> archived');
        $this->closeArchiveModal();
    }

    public function restorePkg(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');

        $pkg = Package::findOrFail($id);
        $filter = (array) ($pkg->entitlement_filter ?? []);
        $filter['ui_status'] = 'live';

        $pkg->update([
            'status' => 'active',
            'entitlement_filter' => $filter,
        ]);

        $this->dispatch('toast', message: '<b>'.e($pkg->name).'</b> restored to live');
    }

    public function deletePkg(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'delete');

        $pkg = Package::findOrFail($id);
        $clientCount = ClientPackage::where('package_id', $pkg->id)->count();

        if ($clientCount > 0) {
            $this->dispatch('toast', message: 'Reassign clients before deleting');

            return;
        }

        $pkg->delete();
        $this->dispatch('toast', message: 'Package deleted');
    }

    // ==========================================
    // ADD-ON EDITOR & ACTIONS
    // ==========================================

    public function openAoModal(?int $id = null): void
    {
        $this->resetValidation();
        $this->editingAoId = $id;

        if ($id) {
            $ao = Package::findOrFail($id);
            $filter = (array) ($ao->entitlement_filter ?? []);

            $this->aoName = $ao->name;
            $this->aoPrice = (string) (int) $ao->price_monthly;
            $this->aoDesc = $ao->description ?? '';
            $this->aoTiers = (array) ($filter['tiers'] ?? ['Premium', 'Standard']);
        } else {
            $this->aoName = '';
            $this->aoPrice = '';
            $this->aoDesc = '';
            $this->aoTiers = ['Premium', 'Standard'];
        }

        $this->showAoModal = true;
    }

    public function closeAoModal(): void
    {
        $this->showAoModal = false;
        $this->editingAoId = null;
    }

    public function toggleAoTier(string $tier): void
    {
        if (in_array($tier, $this->aoTiers, true)) {
            $this->aoTiers = array_values(array_diff($this->aoTiers, [$tier]));
        } else {
            $this->aoTiers[] = $tier;
        }
    }

    public function saveAo(RbacService $rbac): void
    {
        if ($this->editingAoId) {
            $rbac->assertCan(auth()->user(), 'packages', 'edit');
        } else {
            $rbac->assertCan(auth()->user(), 'packages', 'create');
        }

        $this->validate([
            'aoName' => 'required|string|max:120',
            'aoPrice' => 'required|numeric|min:0',
            'aoTiers' => 'required|array|min:1',
        ], [
            'aoName.required' => 'Add-on name is required',
            'aoPrice.required' => 'Set a monthly price',
            'aoTiers.min' => 'Pick at least one package tier',
        ]);

        if ($this->editingAoId) {
            $ao = Package::findOrFail($this->editingAoId);
            $filter = (array) ($ao->entitlement_filter ?? []);
            $filter['tiers'] = $this->aoTiers;

            $ao->update([
                'name' => $this->aoName,
                'price_monthly' => $this->aoPrice,
                'description' => $this->aoDesc ?: null,
                'entitlement_filter' => $filter,
            ]);

            $this->dispatch('toast', message: '<b>'.e($this->aoName).'</b> updated');
        } else {
            $baseCode = substr('ADDON-'.strtoupper(Str::slug($this->aoName)), 0, 28);
            $code = $baseCode;
            $counter = 1;
            while (Package::where('code', $code)->exists()) {
                $code = substr($baseCode, 0, 28).'-'.$counter++;
            }

            $countExisting = Package::all()->filter(function ($p) {
                $f = (array) ($p->entitlement_filter ?? []);

                return ! empty($f['is_addon']);
            })->count();
            $grads = ['g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8'];
            $grad = $grads[($countExisting + 1) % 8];

            $filter = [
                'is_addon' => true,
                'tiers' => $this->aoTiers,
                'grad' => $grad,
                'ui_status' => 'draft',
                'languages' => ['en', 'bn'],
                'media_kinds' => ['photo'],
            ];

            Package::create([
                'code' => $code,
                'name' => $this->aoName,
                'kind' => 'news',
                'description' => $this->aoDesc ?: null,
                'price_monthly' => $this->aoPrice,
                'entitlement_filter' => $filter,
                'status' => 'archived', // Draft
            ]);

            $this->dispatch('toast', message: '<b>'.e($this->aoName).'</b> created as draft — toggle it live when ready');
        }

        $this->closeAoModal();
    }

    public function toggleAoStatus(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'edit');

        $ao = Package::findOrFail($id);
        $filter = (array) ($ao->entitlement_filter ?? []);
        $currentLive = $ao->status === 'active';
        $newLive = ! $currentLive;

        $filter['ui_status'] = $newLive ? 'live' : 'draft';

        $ao->update([
            'status' => $newLive ? 'active' : 'archived',
            'entitlement_filter' => $filter,
        ]);

        $this->dispatch('toast', message: '<b>'.e($ao->name).'</b> is now '.($newLive ? 'live' : 'draft'));
    }

    public function deleteAo(int $id, RbacService $rbac): void
    {
        $rbac->assertCan(auth()->user(), 'packages', 'delete');

        $ao = Package::findOrFail($id);
        $clientCount = ClientPackage::where('package_id', $ao->id)
            ->where('status', 'active')
            ->count();

        if ($clientCount > 0) {
            $this->dispatch('toast', message: '<b>'.$clientCount.'</b> clients use this add-on — remove it from them first');

            return;
        }

        $ao->delete();
        $this->dispatch('toast', message: 'Add-on deleted');
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        $allPackages = Package::all();

        // 1. Subscription packages (is_addon !== true)
        $subPackages = $allPackages->filter(function ($p) {
            $filter = (array) ($p->entitlement_filter ?? []);

            return empty($filter['is_addon']);
        });

        // 2. Add-ons (is_addon === true)
        $addOns = $allPackages->filter(function ($p) {
            $filter = (array) ($p->entitlement_filter ?? []);

            return ! empty($filter['is_addon']);
        });

        // Retrieve client relations
        $clientPackages = ClientPackage::with('client')
            ->where('status', 'active')
            ->get();

        // Map clients to subscription packages
        $formattedPkgs = $subPackages->map(function ($p) use ($clientPackages) {
            $filter = (array) ($p->entitlement_filter ?? []);
            $grad = $filter['grad'] ?? 'g1';

            $activeSubs = $clientPackages->where('package_id', $p->id);
            $clientList = $activeSubs->map(function ($cp) {
                $c = $cp->client;
                if (! $c) {
                    return null;
                }
                $notes = is_array($c->notes) ? $c->notes : json_decode($c->notes ?? '[]', true);
                $nameWords = preg_split('/\s+/', trim($c->name));
                $ini = count($nameWords) >= 2
                    ? strtoupper(substr($nameWords[0], 0, 1).substr($nameWords[1], 0, 1))
                    : strtoupper(substr($c->name, 0, 2));

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'ini' => $notes['ini'] ?? $ini,
                    'grad' => $notes['grad'] ?? 'g1',
                ];
            })->filter()->values();

            $uiStatus = $p->status === 'active' ? 'live' : ($filter['ui_status'] ?? 'archived');

            return (object) [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'price' => (float) $p->price_monthly,
                'desc' => $p->description ?? '',
                'grad' => $grad,
                'status' => $uiStatus,
                'wire' => $filter['wire'] ?? 'Full wire — all categories',
                'quota' => $filter['quota'] ?? 'Unlimited',
                'video' => (bool) ($filter['video'] ?? true),
                'excl' => (bool) ($filter['excl'] ?? false),
                'api' => (bool) ($filter['api'] ?? true),
                'support' => (bool) ($filter['support'] ?? false),
                'clients' => $clientList,
            ];
        });

        // Map clients to add-ons
        $formattedAddOns = $addOns->map(function ($a) use ($clientPackages) {
            $filter = (array) ($a->entitlement_filter ?? []);
            $grad = $filter['grad'] ?? 'g2';
            $activeSubs = $clientPackages->where('package_id', $a->id);
            $clientNames = $activeSubs->map(fn ($cp) => $cp->client?->name)->filter()->values();

            $uiStatus = $a->status === 'active' ? 'live' : ($filter['ui_status'] ?? 'draft');

            return (object) [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'price' => (float) $a->price_monthly,
                'desc' => $a->description ?? '',
                'grad' => $grad,
                'status' => $uiStatus,
                'tiers' => (array) ($filter['tiers'] ?? ['Premium', 'Standard']),
                'clients' => $clientNames,
            ];
        });

        // 3. Compute 4 Metric Stats
        $livePkgsCount = $formattedPkgs->where('status', 'live')->count();
        $liveAddOnsCount = $formattedAddOns->where('status', 'live')->count();

        // Covered clients
        $coveredClientIds = $clientPackages->pluck('client_id')->unique()->count();

        // MRR: Sum of monthly prices for live packages and live add-ons per active client
        $mrr = 0;
        foreach ($formattedPkgs->where('status', 'live') as $p) {
            $mrr += $p->price * count($p->clients);
        }
        foreach ($formattedAddOns->where('status', 'live') as $a) {
            $mrr += $a->price * count($a->clients);
        }

        $formattedMrr = $mrr >= 100000
            ? '৳'.rtrim(rtrim(number_format($mrr / 100000, 1), '0'), '.').' lakh'
            : '৳'.number_format($mrr);

        $this->stats = [
            'live_packages' => $livePkgsCount,
            'live_addons' => $liveAddOnsCount,
            'clients_covered' => $coveredClientIds,
            'mrr' => $formattedMrr,
        ];

        // Package for archive modal
        $archiveTargetPkg = $this->archiveTargetId ? $formattedPkgs->firstWhere('id', $this->archiveTargetId) : null;
        $reassignOptions = $formattedPkgs->where('id', '!=', $this->archiveTargetId)->where('status', 'live');

        return view('livewire.admin.packages-manager', [
            'packages' => $formattedPkgs,
            'addOns' => $formattedAddOns,
            'stats' => [
                'live_packages' => $livePkgsCount,
                'live_addons' => $liveAddOnsCount,
                'clients_covered' => $coveredClientIds,
                'mrr' => $formattedMrr,
            ],
            'archiveTargetPkg' => $archiveTargetPkg,
            'reassignOptions' => $reassignOptions,
        ]);
    }
}
