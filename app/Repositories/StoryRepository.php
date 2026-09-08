<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Story;
use App\Models\StoryEvent;
use App\Models\StoryNote;
use App\Models\StoryVersion;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoryRepository
{
    // ─── Read Methods ───────────────────────────────────────────

    public function filteredList(string $language, string $status, string $category, string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilterQuery($language, $status, $category, $search)
            ->with(['category', 'subCategory', 'owner.role', 'assignedEditor', 'lockedBy', 'notes.user.role'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function filteredIds(string $language, string $status, string $category, string $search, int $perPage = 15): array
    {
        return $this->buildFilterQuery($language, $status, $category, $search)
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function findWithDetails(int $id): ?Story
    {
        return Story::with([
            'category', 'subCategory', 'owner.role',
            'assignedEditor', 'lockedBy', 'notes.user.role', 'events.actor',
        ])->find($id);
    }

    public function findWithAllRelations(int $id): ?Story
    {
        return Story::with([
            'category', 'subCategory', 'owner.role',
            'assignedEditor', 'creator', 'tags', 'media',
            'versions.creator', 'notes.user', 'events.actor',
        ])->find($id);
    }

    public function findByPublicId(string $publicId): ?Story
    {
        return Story::with(['category', 'tags', 'media'])
            ->where('public_id', $publicId)
            ->first();
    }

    public function findPublishedByPublicId(string $publicId): ?Story
    {
        return Story::with(['category', 'tags', 'media'])
            ->where('public_id', $publicId)
            ->where('status', 'published')
            ->first();
    }

    public function findOrFail(int $id): Story
    {
        return Story::with(['category'])->findOrFail($id);
    }

    public function findWithMinimal(int $id): ?Story
    {
        return Story::find($id);
    }

    public function findMany(array $ids): Collection
    {
        return Story::with(['category'])->whereIn('id', $ids)->get();
    }

    public function statusCounts(string $language): array
    {
        return [
            'all' => Story::where('language', $language)->count(),
            'published' => Story::where('language', $language)->where('status', 'published')->count(),
            'draft' => Story::where('language', $language)->where('status', 'draft')->count(),
            'in_review' => Story::where('language', $language)->where('status', 'in_review')->count(),
            'changes_requested' => Story::where('language', $language)->where('status', 'changes_requested')->count(),
        ];
    }

    public function recentPublished(int $limit = 4, int $fallbackDays = 30): Collection
    {
        $stories = Story::with(['category', 'tags', 'owner', 'deliveries'])
            ->whereIn('status', ['published', 'approved'])
            ->latest('published_at')
            ->limit($limit)
            ->get();

        if ($stories->count() < $limit) {
            $fallback = Story::with(['category', 'tags', 'owner', 'deliveries'])
                ->whereNotIn('id', $stories->pluck('id'))
                ->latest('updated_at')
                ->limit($limit - $stories->count())
                ->get();
            $stories = $stories->concat($fallback);
        }

        return $stories;
    }

    public function countByDateAndStatus(Carbon $date, string $status = 'published'): int
    {
        return Story::whereDate('published_at', $date)->where('status', $status)->count();
    }

    public function countExclusive(Carbon $date): int
    {
        return Story::whereDate('published_at', $date)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('is_breaking', true)
                    ->orWhereIn('priority', ['urgent', 'flash'])
                    ->orWhereHas('tags', function ($t) {
                        $t->where('name', 'exclusive')->orWhere('slug', 'exclusive');
                    });
            })
            ->count();
    }

    public function headlines(string $language, int $limit = 6): Collection
    {
        return Story::where('status', 'published')
            ->where('language', $language)
            ->latest('published_at')
            ->limit($limit)
            ->pluck('headline');
    }

    public function relatedStories(int $storyId, ?int $categoryId, int $limit = 3): Collection
    {
        $related = Story::with(['category', 'media'])
            ->where('id', '!=', $storyId)
            ->where('status', 'published')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->latest('published_at')
            ->limit($limit)
            ->get();

        if ($related->count() < $limit) {
            $fallback = Story::with(['category', 'media'])
                ->where('id', '!=', $storyId)
                ->where('status', 'published')
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('published_at')
                ->limit($limit - $related->count())
                ->get();

            $related = $related->concat($fallback);
        }

        return $related;
    }

    public function latestRail(string $language, int $limit = 5, ?int $excludeId = null): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function exportQuery(string $language, string $status, string $category, string $search): Collection
    {
        return $this->buildFilterQuery($language, $status, $category, $search)
            ->with(['category', 'subCategory', 'owner'])
            ->orderByDesc('updated_at')
            ->get();
    }

    // ─── Wire Feed Methods ─────────────────────────────────────

    public function heroStory(string $language, string $search = '', string $activeCategory = 'all'): ?Story
    {
        $query = Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language);

        if (! empty($search)) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('headline', 'like', $term)
                    ->orWhere('body_text', 'like', $term);
            });
        }

        if ($activeCategory !== 'all') {
            $query->where(function ($q) use ($activeCategory) {
                $q->where('category_id', $activeCategory)
                    ->orWhereHas('category', fn ($c) => $c->where('slug', $activeCategory));
            });
        } else {
            $breaking = (clone $query)->where('is_breaking', true)->latest('published_at')->first();
            if ($breaking) {
                return $breaking;
            }
        }

        return $query->latest('published_at')->first();
    }

    public function publishedByCategory(string $language, int $categoryId, ?int $excludeId = null, int $limit = 8): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->where('category_id', $categoryId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function publishedByCategorySlug(string $language, string $categorySlug, ?int $excludeId = null, int $limit = 8): Collection
    {
        $category = Category::where('slug', $categorySlug)->first();
        if (! $category) {
            return collect();
        }

        return $this->publishedByCategory($language, $category->id, $excludeId, $limit);
    }

    public function publishedFeed(string $language, ?int $excludeId = null, int $limit = 8): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function create(array $data): Story
    {
        return Story::create($data);
    }

    public function update(Story $story, array $data): Story
    {
        $story->update($data);

        return $story->refresh();
    }

    public function delete(int $id): bool
    {
        return Story::findOrFail($id)->delete();
    }

    public function deleteMany(array $ids): int
    {
        return Story::whereIn('id', $ids)->delete();
    }

    public function createVersion(Story $story, array $snapshot, int $actorId): StoryVersion
    {
        return $story->versions()->create([
            'version' => $story->version,
            'snapshot' => $snapshot,
            'created_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    public function createEvent(Story $story, array $eventData): StoryEvent
    {
        return $story->events()->create($eventData);
    }

    public function createNote(Story $story, array $noteData): StoryNote
    {
        return $story->notes()->create($noteData);
    }

    public function syncTags(Story $story, array $tagNames): void
    {
        if (empty($tagNames)) {
            $story->tags()->sync([]);

            return;
        }

        $tagIds = [];
        foreach ($tagNames as $tName) {
            $slug = Str::slug($tName);
            $tagObj = Tag::firstOrCreate(['name' => $tName], ['slug' => $slug ?: Str::random(8)]);
            $tagIds[] = $tagObj->id;
        }
        $story->tags()->sync($tagIds);
    }

    public function syncMedia(int $storyId, ?int $featuredMediaId, ?string $featuredCaption, array $attachedMedia): void
    {
        DB::table('story_media')->where('story_id', $storyId)->delete();

        if ($featuredMediaId) {
            DB::table('story_media')->insert([
                'story_id' => $storyId,
                'asset_id' => $featuredMediaId,
                'role' => 'featured',
                'sort_order' => 0,
                'caption_override' => $featuredCaption ?: null,
            ]);
        }

        foreach ($attachedMedia as $idx => $att) {
            if ($att['id'] === $featuredMediaId) {
                continue;
            }
            DB::table('story_media')->insert([
                'story_id' => $storyId,
                'asset_id' => $att['id'],
                'role' => 'inline',
                'sort_order' => $idx + 1,
                'caption_override' => $att['cap'] ?: null,
            ]);
        }
    }

    public function getAttachedMedia(int $storyId): Collection
    {
        return DB::table('story_media')
            ->join('media_assets', 'media_assets.id', '=', 'story_media.asset_id')
            ->where('story_media.story_id', $storyId)
            ->orderBy('story_media.sort_order')
            ->select('media_assets.id', 'media_assets.title', 'media_assets.caption', 'media_assets.kind', 'story_media.role', 'story_media.caption_override')
            ->get();
    }

    // ─── Private Helpers ───────────────────────────────────────

    private function buildFilterQuery(string $language, string $status, string $category, string $search)
    {
        $query = Story::where('language', $language);

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($category !== 'all') {
            $query->where('category_id', $category);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('headline', 'like', '%'.$search.'%')
                    ->orWhere('brief', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }
}
