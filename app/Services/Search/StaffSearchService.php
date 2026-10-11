<?php

namespace App\Services\Search;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side search over the staff Meilisearch index (all story statuses — NFR §1
 * surface #1 newsroom archive). Queried only from the admin layer via the master
 * key; NEVER exposed through portal tenant tokens (TenantTokenIssuer rules cover
 * main/archive/media only). Engine unavailable → null → caller falls back to
 * trgm-assisted SQL LIKE.
 */
class StaffSearchService
{
    /**
     * @return array<string>|null matching story public_ids (possibly empty); null = engine unavailable
     */
    public function searchIds(string $q, string $language, string $status, string $category): ?array
    {
        $host = config('services.meilisearch.host') ?? env('MEILISEARCH_HOST');
        $key = config('services.meilisearch.key') ?? env('MEILISEARCH_KEY');

        if (! $host || ! $key) {
            return null;
        }

        try {
            $this->ensureIndex($host, $key);

            $response = Http::withHeaders(['Authorization' => "Bearer {$key}"])
                ->timeout(2)
                ->post("{$host}/indexes/staff/search", [
                    'q' => $q,
                    'filter' => $this->buildFilter($language, $status, $category),
                    'limit' => 500,
                ]);
            $response->throw();

            return array_values(array_filter(array_column($response->json('hits') ?? [], 'id')));
        } catch (Throwable $e) {
            Log::warning('staff search unavailable — falling back to SQL', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function buildFilter(string $language, string $status, string $category): array
    {
        $filter = ['language = "'.$language.'"'];

        if ($status !== 'all') {
            $filter[] = 'status = "'.$status.'"';
        }

        $categoryId = is_numeric($category) ? (int) $category : Category::where('slug', $category)->value('id');
        if ($categoryId) {
            $filter[] = "category_id = {$categoryId}";
        }

        return $filter;
    }

    private function ensureIndex(string $host, string $key): void
    {
        if (Cache::get('search:staff:settings')) {
            return;
        }

        Http::withHeaders(['Authorization' => "Bearer {$key}"])
            ->timeout(2)
            ->patch("{$host}/indexes/staff/settings", [
                'searchableAttributes' => ['headline', 'brief', 'body_text', 'search_tags'],
                'filterableAttributes' => ['language', 'status', 'category_id', 'is_breaking'],
            ])
            ->throw();

        Cache::put('search:staff:settings', true, 86400);
    }
}
