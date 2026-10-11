<?php

namespace App\Services;

use App\Services\Search\StaffSearchService;
use App\Support\StoryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Composes the news-list query from a validated StoryFilter: pipeline dimensions
 * (author/tags/date/image) are structured SQL clauses; free-text is resolved by
 * StaffSearchService (Meili ids → whereIn) with a SQL ILIKE fallback and a
 * SQL-only caption scope. All clauses AND-combine so pagination, select-all and
 * export see one identical filtered dataset.
 */
class StoryFilterService
{
    public function filter(Builder $q, StoryFilter $f): Builder
    {
        $q->where('language', $f->language);

        if ($f->status !== 'all') {
            $q->where('status', $f->status);
        }

        if ($f->category !== 'all') {
            if (is_numeric($f->category)) {
                $q->where('category_id', (int) $f->category);
            } else {
                $q->whereHas('category', fn ($c) => $c->where('slug', $f->category));
            }
        }

        if ($f->authorId !== null) {
            $q->where('owner_id', $f->authorId);
        }

        if ($f->tagIds !== []) {
            $q->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $f->tagIds));
        }

        if ($f->isDateActive()) {
            $col = $f->dateField === 'created' ? 'created_at' : 'published_at';
            $q->where($col, '>=', $f->windowStart)
                ->where($col, '<', $f->windowEndExclusive);
        }

        if ($f->hasImage === 'with') {
            $q->whereHas('media');
        } elseif ($f->hasImage === 'without') {
            $q->whereDoesntHave('media');
        }

        if ($f->text !== '') {
            $this->applyText($q, $f);
        }

        return $q;
    }

    private function applyText(Builder $q, StoryFilter $f): void
    {
        if ($f->scope === 'caption') {
            self::applyCaptionSearch($q, $f->text);

            return;
        }

        $ids = app(StaffSearchService::class)->searchIds($f);
        if ($ids !== null) {
            // Meilisearch staff index (all statuses) — ids are authoritative
            $q->whereIn('public_id', $ids);

            return;
        }

        // Engine unavailable — trgm-assisted SQL fallback (M15-SCALE-001)
        $term = '%'.$f->text.'%';
        $op = self::ciOperator();
        $q->where(function ($w) use ($term, $f, $op) {
            foreach ($f->searchColumns() as $col) {
                $w->orWhere($col, $op, $term);
            }
        });
    }

    public static function applyCaptionSearch(Builder $q, string $text): void
    {
        // Caption lives on media_assets (+ story_media.caption_override) — absent
        // from the staff index, so this path is SQL-only.
        $term = '%'.$text.'%';
        $op = self::ciOperator();

        $q->whereIn('id', function ($sub) use ($term, $op) {
            $sub->select('story_media.story_id')
                ->from('story_media')
                ->join('media_assets', 'media_assets.id', '=', 'story_media.asset_id')
                ->where(function ($w) use ($term, $op) {
                    $w->where('media_assets.caption', $op, $term)
                        ->orWhere('story_media.caption_override', $op, $term);
                });
        });
    }

    /** Case-insensitive LIKE on Postgres (trgm-accelerated), plain LIKE elsewhere. */
    private static function ciOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * Distinct owners of stories in the language — author dropdown population.
     */
    public static function authorOptions(string $language): Collection
    {
        $ownerIds = DB::table('stories')
            ->where('language', $language)
            ->distinct()
            ->orderBy('owner_id')
            ->pluck('owner_id')
            ->filter();

        return DB::table('users')
            ->whereIn('id', $ownerIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public static function tagOptions(): Collection
    {
        return DB::table('tags')->orderBy('name')->get(['id', 'name']);
    }
}
