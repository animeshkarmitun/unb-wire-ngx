<?php

namespace App\Repositories;

use App\Models\ClientPackage;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PackageRepository
{
    // ─── Read Methods ───────────────────────────────────────────

    public function findOrFail(int $id): Package
    {
        return Package::findOrFail($id);
    }

    public function findByCode(string $code): ?Package
    {
        return Package::where('code', $code)->first();
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        return Package::where('code', $code)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function all(): Collection
    {
        return Package::all();
    }

    public function activePackages(): Collection
    {
        return Package::where('status', 'active')->get();
    }

    public function subscriberCount(int $packageId): int
    {
        return ClientPackage::where('package_id', $packageId)
            ->where('status', 'active')
            ->count();
    }

    public function totalClientCount(int $packageId): int
    {
        return ClientPackage::where('package_id', $packageId)->count();
    }

    public function activeSubscribersWithClients(): Collection
    {
        return ClientPackage::with('client')
            ->where('status', 'active')
            ->get();
    }

    public function getActiveEntitlements(): Collection
    {
        return DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.status', 'active')
            ->select('client_packages.client_id', 'packages.entitlement_filter')
            ->get();
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function create(array $data): Package
    {
        return Package::create($data);
    }

    public function update(Package $package, array $data): Package
    {
        $package->update($data);

        return $package->refresh();
    }

    public function delete(Package $package): bool
    {
        return $package->delete();
    }

    public function reassignSubscribers(int $fromPackageId, int $toPackageId): int
    {
        return ClientPackage::where('package_id', $fromPackageId)
            ->where('status', 'active')
            ->update(['package_id' => $toPackageId]);
    }
}
