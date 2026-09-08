<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\StoryRepository;
use Illuminate\Support\Collection;

class StoryQueryService
{
    public function __construct(private StoryRepository $stories) {}

    public function filteredList(string $language, string $status, string $category, string $search, int $perPage = 15)
    {
        return $this->stories->filteredList($language, $status, $category, $search, $perPage);
    }

    public function filteredIds(string $language, string $status, string $category, string $search, int $perPage = 15): array
    {
        return $this->stories->filteredIds($language, $status, $category, $search, $perPage);
    }

    public function findWithDetails(int $id)
    {
        return $this->stories->findWithDetails($id);
    }

    public function statusCounts(string $language): array
    {
        return $this->stories->statusCounts($language);
    }

    public function categoriesTree(): Collection
    {
        return Category::whereNull('parent_id')->with('children')->orderBy('sort_order')->get();
    }

    public function exportQuery(string $language, string $status, string $category, string $search): Collection
    {
        return $this->stories->exportQuery($language, $status, $category, $search);
    }
}
