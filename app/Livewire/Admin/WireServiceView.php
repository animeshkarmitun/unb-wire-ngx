<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Services\RbacService;
use Illuminate\Support\Collection;
use Livewire\Component;

class WireServiceView extends Component
{
    public string $service = 'en';

    public string $search = '';

    public string $activeCategory = 'all';

    public string $activeRailTab = 'latest';

    public bool $showConfigModal = false;

    public string $wireName = '';

    public string $description = '';

    public bool $enabled = true;

    public function mount(string $service = 'en'): void
    {
        $this->service = in_array($service, ['en', 'bn']) ? $service : 'en';

        $s = Setting::where('key', 'service.'.$this->service)->first();
        if ($s && is_array($s->value)) {
            $v = $s->value;
            $this->wireName = $v['wireName'] ?? ($this->service === 'bn' ? 'UNB বাংলা সার্ভিস' : 'UNB English Wire');
            $this->description = $v['description'] ?? '';
            $this->enabled = $v['enabled'] ?? true;
        } else {
            $this->wireName = $this->service === 'bn' ? 'UNB বাংলা সার্ভিস' : 'UNB English Wire';
            $this->description = 'Real-time news agency wire feed and multimedia distribution service.';
            $this->enabled = true;
        }
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function setRailTab(string $tab): void
    {
        $this->activeRailTab = in_array($tab, ['latest', 'popular']) ? $tab : 'latest';
    }

    public function saveConfig(): void
    {
        try {
            app(RbacService::class)->assertCan(auth()->user(), 'settings', 'edit');
        } catch (\Throwable $e) {
            // fallback if user is admin
            if (auth()->user()?->role?->code !== 'admin') {
                throw $e;
            }
        }

        $this->validate([
            'wireName' => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        Setting::updateOrCreate(
            ['key' => 'service.'.$this->service],
            [
                'value' => [
                    'wireName' => $this->wireName,
                    'description' => $this->description,
                    'enabled' => $this->enabled,
                ],
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]
        );

        $this->showConfigModal = false;
        $this->dispatch('toast', message: 'Wire configuration updated successfully.');
    }

    public function getCategoriesProperty(): Collection
    {
        return Category::withCount(['stories' => function ($q) {
            $q->where('status', 'published')->where('language', $this->service);
        }])->orderBy('sort_order')->get();
    }

    public function getHeroStoryProperty(): ?Story
    {
        $query = Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $this->service);

        if (! empty($this->search)) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('headline', 'like', $term)
                    ->orWhere('body_text', 'like', $term);
            });
        }

        if ($this->activeCategory !== 'all') {
            $cat = $this->activeCategory;
            $query->where(function ($q) use ($cat) {
                $q->where('category_id', $cat)
                    ->orWhereHas('category', fn ($c) => $c->where('slug', $cat));
            });
        } else {
            // Prioritize breaking news if no specific category is selected
            $breaking = (clone $query)->where('is_breaking', true)->latest('published_at')->first();
            if ($breaking) {
                return $breaking;
            }
        }

        return $query->latest('published_at')->first();
    }

    public function getSectionsProperty(): Collection
    {
        $heroId = optional($this->heroStory)->id;

        if ($this->activeCategory !== 'all') {
            // Single category section
            $category = Category::where('id', $this->activeCategory)
                ->orWhere('slug', $this->activeCategory)
                ->first();

            if (! $category) {
                return collect();
            }

            $stories = Story::with(['category', 'media'])
                ->where('status', 'published')
                ->where('language', $this->service)
                ->where('category_id', $category->id)
                ->when($heroId, fn ($q) => $q->where('id', '!=', $heroId))
                ->when(! empty($this->search), function ($q) {
                    $term = '%'.$this->search.'%';
                    $q->where('headline', 'like', $term);
                })
                ->latest('published_at')
                ->limit(8)
                ->get();

            return collect([[
                'category' => $category,
                'title' => $this->service === 'bn' ? ($category->name_bn ?: $category->name_en) : $category->name_en,
                'stories' => $stories,
            ]]);
        }

        // Multiple sections across top categories
        $categories = Category::whereHas('stories', function ($q) {
            $q->where('status', 'published')->where('language', $this->service);
        })->orderBy('sort_order')->limit(4)->get();

        $sections = collect();
        foreach ($categories as $cat) {
            $stories = Story::with(['category', 'media'])
                ->where('status', 'published')
                ->where('language', $this->service)
                ->where('category_id', $cat->id)
                ->when($heroId, fn ($q) => $q->where('id', '!=', $heroId))
                ->latest('published_at')
                ->limit(4)
                ->get();

            if ($stories->isNotEmpty()) {
                $sections->push([
                    'category' => $cat,
                    'title' => $this->service === 'bn' ? ($cat->name_bn ?: $cat->name_en) : $cat->name_en,
                    'stories' => $stories,
                ]);
            }
        }

        // Fallback if stories are not categorized into multiple distinct categories
        if ($sections->isEmpty()) {
            $fallbackStories = Story::with(['category', 'media'])
                ->where('status', 'published')
                ->where('language', $this->service)
                ->when($heroId, fn ($q) => $q->where('id', '!=', $heroId))
                ->latest('published_at')
                ->limit(8)
                ->get();

            if ($fallbackStories->isNotEmpty()) {
                $sections->push([
                    'category' => null,
                    'title' => $this->service === 'bn' ? 'সাম্প্রতিক সংবাদ' : 'Latest Wire Dispatches',
                    'stories' => $fallbackStories,
                ]);
            }
        }

        return $sections;
    }

    public function getLatestRailProperty(): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $this->service)
            ->latest('published_at')
            ->limit(5)
            ->get();
    }

    public function getPopularRailProperty(): Collection
    {
        return Story::with(['category', 'media'])
            ->where('status', 'published')
            ->where('language', $this->service)
            ->orderByDesc('word_count') // proxy for prominent in-depth dispatches
            ->latest('published_at')
            ->limit(5)
            ->get();
    }

    public function getTickerStoriesProperty(): Collection
    {
        $headlines = Story::where('status', 'published')
            ->where('language', $this->service)
            ->latest('published_at')
            ->limit(6)
            ->pluck('headline');

        if ($headlines->isEmpty()) {
            return collect([
                $this->service === 'bn'
                    ? 'ইউএনবি সংবাদ সেবা — সার্বক্ষণিক বস্তুনিষ্ঠ ও নির্ভরযোগ্য সাংবাদিকতা'
                    : 'UNB Wire News Service — Real-time multimedia news distribution',
            ]);
        }

        return $headlines;
    }

    public function render()
    {
        return view('livewire.admin.wire-service-view', [
            'categories' => $this->categories,
            'heroStory' => $this->heroStory,
            'sections' => $this->sections,
            'latestRail' => $this->latestRail,
            'popularRail' => $this->popularRail,
            'tickerStories' => $this->tickerStories,
        ]);
    }
}
