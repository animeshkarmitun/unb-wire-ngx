<?php

namespace App\Services\Delivery;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Services\Search\EntitlementResolver;
use Illuminate\Support\Facades\DB;

/**
 * COS-79: resolves per-asset media access for a client.
 *
 * An asset with NO package_media rows is "ordinary" story media — its kind
 * is checked against the client's effective media_kinds.
 * An asset WITH package_media rows requires an active subscription to at
 * least one linked package (base package or add-on); effective media_kinds
 * must also allow the asset's kind.
 *
 * Media visibility is independent of story text: an inaccessible asset is
 * filtered from the story's attachments without hiding the story.
 */
class MediaEntitlementService
{
    /**
     * Filter a story's media collection down to what the client may see.
     *
     * @param  iterable<MediaAsset>  $media
     * @return array<int, MediaAsset>
     */
    public function visibleMediaFor(Client $client, iterable $media): array
    {
        $assets = collect($media)->reject(fn (MediaAsset $m) => $m->isEmbargoed());
        if ($assets->isEmpty()) {
            return [];
        }

        $assetIds = $assets->pluck('id')->unique()->all();
        $linked = $this->linkedPackageIds($assetIds); // asset_id => [package_id, ...]
        $activeIds = $this->activePackageIds($client); // [package_id, ...]
        $activeIds = array_map('intval', $activeIds);

        return $assets
            ->filter(function (MediaAsset $m) use ($client, $linked, $activeIds) {
                // Kind gate from the effective entitlement filter.
                $kinds = app(EntitlementResolver::class)
                    ->forClient($client)['media_kinds'] ?? null;
                if (is_array($kinds) && $kinds !== [] && ! in_array($m->kind, $kinds, true)) {
                    return false;
                }

                // Package-linked (special/restricted) assets: need an active
                // subscription to at least one linked package.
                $packageIds = $linked[(int) $m->id] ?? null;
                if ($packageIds !== null && $packageIds !== []) {
                    return ! empty(array_intersect($packageIds, $activeIds));
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * Batch resolve package_media links for many assets (no N+1).
     *
     * @return array<int, array<int, int>>
     */
    public function linkedPackageIds(array $assetIds): array
    {
        $rows = DB::table('package_media')
            ->whereIn('asset_id', $assetIds)
            ->get(['asset_id', 'package_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->asset_id][] = (int) $row->package_id;
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    public function activePackageIds(Client $client): array
    {
        return DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.client_id', $client->id)
            ->where('client_packages.status', 'active')
            ->where('packages.status', 'active')
            ->where(function ($q) {
                $q->whereNull('client_packages.starts_at')->orWhere('client_packages.starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('client_packages.ends_at')->orWhere('client_packages.ends_at', '>', now());
            })
            ->pluck('packages.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
