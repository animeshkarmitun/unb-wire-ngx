<?php

namespace App\Services\Search;

use App\Models\Client;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

class EntitlementResolver
{
    public function forClient(Client $client): array
    {
        $rows = $this->currentRows($client);

        $langs = [];
        $cats = [];
        $media = [];

        foreach ($rows as $r) {
            $f = is_string($r->entitlement_filter) ? json_decode($r->entitlement_filter, true) : $r->entitlement_filter;
            if (! empty($f['languages'])) {
                $langs = array_merge($langs, (array) $f['languages']);
            }
            if (! empty($f['category_ids'])) {
                $cats = array_merge($cats, (array) $f['category_ids']);
            }
            if (! empty($f['media_kinds'])) {
                $media = array_merge($media, (array) $f['media_kinds']);
            }
        }

        return [
            'languages' => array_values(array_unique($langs)) ?: ['en', 'bn'],
            'category_ids' => $cats ? array_values(array_unique($cats)) : null,
            'media_kinds' => $media ? array_values(array_unique($media)) : null,
        ];
    }

    public function strictEntitlement(Client $client): array
    {
        $rows = $this->currentRows($client);

        $langs = [];
        $cats = [];
        $media = [];

        foreach ($rows as $r) {
            $f = is_string($r->entitlement_filter) ? json_decode($r->entitlement_filter, true) : $r->entitlement_filter;
            if (! empty($f['languages'])) {
                $langs = array_merge($langs, (array) $f['languages']);
            }
            if (! empty($f['category_ids'])) {
                $cats = array_merge($cats, (array) $f['category_ids']);
            }
            if (! empty($f['media_kinds'])) {
                $media = array_merge($media, (array) $f['media_kinds']);
            }
        }

        return [
            'languages' => array_values(array_unique($langs)),
            'category_ids' => $cats ? array_values(array_unique($cats)) : [],
            'media_kinds' => $media ? array_values(array_unique($media)) : [],
        ];
    }

    private function currentRows(Client $client)
    {
        return DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.client_id', $client->id)
            ->where('client_packages.status', 'active')
            ->where('packages.status', 'active')
            ->where(function ($q) {
                $q->whereNull('client_packages.ends_at')->orWhere('client_packages.ends_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('client_packages.starts_at')->orWhere('client_packages.starts_at', '<=', now());
            })
            ->select('packages.entitlement_filter')
            ->get();
    }

    public function clientAllowed(Client $client, Story $story): bool
    {
        if ($client->status !== 'active') {
            return false;
        }

        $e = $this->strictEntitlement($client);

        if (empty($e['languages'])) {
            return false;
        }

        if (! in_array($story->language, $e['languages'], true)) {
            return false;
        }

        if (! empty($e['category_ids']) && ! in_array($story->category_id, $e['category_ids'], true)) {
            return false;
        }

        if (! empty($e['media_kinds'])) {
            $kinds = $story->relationLoaded('media') ? $story->media->pluck('kind')->all() : $story->media()->pluck('kind')->all();
            $kinds = array_unique($kinds);
            if (empty(array_intersect($e['media_kinds'], $kinds))) {
                return false;
            }
        }

        return true;
    }

    public function compileMeiliFilter(Client $client): string
    {
        $e = $this->forClient($client);
        $langs = implode(', ', $e['languages']);
        $filter = "language IN [{$langs}]";
        if (! empty($e['category_ids'])) {
            $ids = implode(', ', $e['category_ids']);
            $filter .= " AND category_id IN [{$ids}]";
        }

        return $filter;
    }

    public function compileMeiliFilterFromEntitlement(array $entitlement): string
    {
        $langs = implode(', ', $entitlement['languages'] ?? ['en']);
        $filter = "language IN [{$langs}]";
        if (! empty($entitlement['category_ids'])) {
            $ids = implode(', ', $entitlement['category_ids']);
            $filter .= " AND category_id IN [{$ids}]";
        }

        return $filter;
    }
}
