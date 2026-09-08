<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function rootCategories(): Collection
    {
        return Category::whereNull('parent_id')->orderBy('sort_order')->get();
    }

    public function subcategoriesOf(int $parentId): Collection
    {
        return Category::where('parent_id', $parentId)->orderBy('sort_order')->get();
    }

    public function categoriesTree(): Collection
    {
        return Category::whereNull('parent_id')->with('children')->orderBy('sort_order')->get();
    }

    public function findFirstRoot(): ?Category
    {
        return Category::whereNull('parent_id')->orderBy('sort_order')->first();
    }

    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::where('slug', $slug)->first();
    }

    public function findByNameLike(string $name): ?Category
    {
        return Category::where('name_en', 'like', '%'.$name.'%')->first();
    }

    public function withPublishedStoryCounts(string $language): Collection
    {
        return Category::withCount(['stories' => function ($q) use ($language) {
            $q->where('status', 'published')->where('language', $language);
        }])->orderBy('sort_order')->get();
    }

    public function categoriesWithPublishedStories(string $language): Collection
    {
        return Category::whereHas('stories', function ($q) use ($language) {
            $q->where('status', 'published')->where('language', $language);
        })->orderBy('sort_order')->limit(4)->get();
    }
}
