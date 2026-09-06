<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StoryQueryService
{
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

    public function categoriesTree(): Collection
    {
        return Category::whereNull('parent_id')->with('children')->orderBy('sort_order')->get();
    }

    public function exportQuery(string $language, string $status, string $category, string $search): Collection
    {
        return $this->buildFilterQuery($language, $status, $category, $search)
            ->with(['category', 'subCategory', 'owner'])
            ->orderByDesc('updated_at')
            ->get();
    }

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
