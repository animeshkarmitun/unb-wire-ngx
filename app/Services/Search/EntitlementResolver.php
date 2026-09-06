<?php

namespace App\Services\Search;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class EntitlementResolver
{
    public function forClient(Client $client): array
    {
        $rows = DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.client_id', $client->id)
            ->where('client_packages.status', 'active')
            ->where('packages.status', 'active')
            ->where(function ($q) {
                $q->whereNull('client_packages.ends_at')->orWhere('client_packages.ends_at', '>', now());
            })
            ->select('packages.entitlement_filter')
            ->get();

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
