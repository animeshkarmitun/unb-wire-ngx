<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Story;
use App\Repositories\StoryRepository;
use Illuminate\Support\Collection;

class WireFeedService
{
    public function __construct(private StoryRepository $stories) {}

    public function categories(string $language): Collection
    {
        return Category::withCount(['stories' => function ($q) use ($language) {
            $q->where('status', 'published')->where('language', $language);
        }])->orderBy('sort_order')->get();
    }

    public function heroStory(string $language, string $search = '', string $activeCategory = 'all'): ?Story
    {
        return $this->stories->heroStory($language, $search, $activeCategory);
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

        $stories = $this->stories->publishedByCategory($language, $category->id, $excludeId, 8);

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
            $stories = $this->stories->publishedByCategory($language, $cat->id, $excludeId, 4);

            if ($stories->isNotEmpty()) {
                $sections->push([
                    'category' => $cat,
                    'title' => $language === 'bn' ? ($cat->name_bn ?: $cat->name_en) : $cat->name_en,
                    'stories' => $stories,
                ]);
            }
        }

        if ($sections->isEmpty()) {
            $fallback = $this->stories->publishedFeed($language, $excludeId, 8);

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
        return $this->stories->latestRail($language, $limit);
    }

    public function popularRail(string $language, int $limit = 5): Collection
    {
        return $this->stories->latestRail($language, $limit);
    }

    public function tickerHeadlines(string $language, int $limit = 6): Collection
    {
        $headlines = $this->stories->headlines($language, $limit);

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
