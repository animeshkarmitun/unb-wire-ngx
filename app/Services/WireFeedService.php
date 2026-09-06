<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Support\Collection;

class WireFeedService
{
    public function categories(string $language): Collection
    {
        return Category::withCount(['stories' => function ($q) use ($language) {
            $q->where('status', 'published')->where('language', $language);
        }])->orderBy('sort_order')->get();
    }

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

    public function sections(string $language, string $search, string $activeCategory, ?int $excludeId): Collection
    {
        if ($activeCategory !== 'all') {
            return $this->singleCategorySection($language, $search, $activeCategory, $excludeId);
        }

        return $this->multiCategorySections($language, $excludeId);
    }

    private function singleCategorySection(string $language, string $search, string $activeCategory, ?int $excludeId): Collection
    {
        $category = Category::where('id', $activeCategory)
            ->orWhere('slug', $activeCategory)
            ->first();

        if (! $category) {
            return collect();
        }

        $stories = Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->where('category_id', $category->id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when(! empty($search), function ($q) use ($search) {
                $q->where('headline', 'like', '%'.$search.'%');
            })
            ->latest('published_at')
            ->limit(8)
            ->get();

        return collect([[
            'category' => $category,
            'title' => $language === 'bn' ? ($category->name_bn ?: $category->name_en) : $category->name_en,
            'stories' => $stories,
        ]]);
    }

    private function multiCategorySections(string $language, ?int $excludeId): Collection
    {
        $categories = Category::whereHas('stories', function ($q) use ($language) {
            $q->where('status', 'published')->where('language', $language);
        })->orderBy('sort_order')->limit(4)->get();

        $sections = collect();
        foreach ($categories as $cat) {
            $stories = Story::with(['category', 'media'])
                ->where('status', 'published')
                ->where('language', $language)
                ->where('category_id', $cat->id)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->latest('published_at')
                ->limit(4)
                ->get();

            if ($stories->isNotEmpty()) {
                $sections->push([
                    'category' => $cat,
                    'title' => $language === 'bn' ? ($cat->name_bn ?: $cat->name_en) : $cat->name_en,
                    'stories' => $stories,
                ]);
            }
        }

        if ($sections->isEmpty()) {
            $fallback = Story::with(['category', 'media'])
                ->where('status', 'published')
                ->where('language', $language)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->latest('published_at')
                ->limit(8)
                ->get();

            if ($fallback->isNotEmpty()) {
                $sections->push([
                    'category' => null,
                    'title' => $language === 'bn' ? 'সাম্প্রতিক সংবাদ' : 'Latest Wire Dispatches',
                    'stories' => $fallback,
                ]);
            }
        }

        return $sections;
    }

    public function latestRail(string $language, int $limit = 5): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function popularRail(string $language, int $limit = 5): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $language)
            ->orderByDesc('word_count')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function tickerHeadlines(string $language, int $limit = 6): Collection
    {
        $headlines = Story::where('status', 'published')
            ->where('language', $language)
            ->latest('published_at')
            ->limit($limit)
            ->pluck('headline');

        if ($headlines->isEmpty()) {
            return collect([
                $language === 'bn'
                    ? 'ইউএনবি সংবাদ সেবা — সার্বক্ষণিক বস্তুনিষ্ঠ ও নির্ভরযোগ্য সাংবাদিকতা'
                    : 'UNB Wire News Service — Real-time multimedia news distribution',
            ]);
        }

        return $headlines;
    }
}
