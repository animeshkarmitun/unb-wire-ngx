<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Support\StoryFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side search over the staff Meilisearch index (all story statuses — NFR §1
 * surface #1 newsroom archive). Queried only from the admin layer via the master
 * key; NEVER exposed through portal tenant tokens (TenantTokenIssuer rules cover
 * main/archive/media only). Engine unavailable → null → caller falls back to
 * trgm-assisted SQL LIKE. Scope-sensitive via per-query attributesToSearchOn;
 * scopes backed by columns absent from the index (caption) are never routed here.
 */
class StaffSearchService
{
    /**
     * @return array<string>|null matching story public_ids (possibly empty); null = engine unavailable
     */
    public function searchIds(StoryFilter $f): ?array
    {
        if ($f->text === '' || $f->scope === 'caption') {
            return null;
        }

        $host = config('services.meilisearch.host') ?? env('MEILISEARCH_HOST');
        $key = config('services.meilisearch.key') ?? env('MEILISEARCH_KEY');

        if (! $host || ! $key) {
            return null;
        }

        try {
            $this->ensureIndex($host, $key);

            $body = [
                'q' => $f->text,
                'filter' => $this->buildFilter($f),
                'limit' => 500,
            ];
            if ($attributes = $f->searchAttributes()) {
                $body['attributesToSearchOn'] = $attributes;
            }

            $response = Http::withHeaders(['Authorization' => "Bearer {$key}"])
                ->timeout(2)
                ->post("{$host}/indexes/staff/search", $body);
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
    private function buildFilter(StoryFilter $f): array
    {
        $filter = ['language = "'.$f->language.'"'];

        if ($f->status !== 'all') {
            $filter[] = 'status = "'.$f->status.'"';
        }

        $categoryId = is_numeric($f->category) ? (int) $f->category : Category::where('slug', $f->category)->value('id');
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
