<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\RbacService;
use App\Services\WireFeedService;
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
        return app(WireFeedService::class)->categories($this->service);
    }

    public function getHeroStoryProperty()
    {
        return app(WireFeedService::class)->heroStory($this->service, $this->search, $this->activeCategory);
    }

    public function getSectionsProperty(): Collection
    {
        return app(WireFeedService::class)->sections(
            $this->service, $this->search, $this->activeCategory, optional($this->heroStory)->id
        );
    }

    public function getLatestRailProperty(): Collection
    {
        return app(WireFeedService::class)->latestRail($this->service);
    }

    public function getPopularRailProperty(): Collection
    {
        return app(WireFeedService::class)->popularRail($this->service);
    }

    public function getTickerStoriesProperty(): Collection
    {
        return app(WireFeedService::class)->tickerHeadlines($this->service);
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
