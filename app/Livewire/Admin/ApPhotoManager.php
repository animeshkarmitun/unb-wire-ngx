<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\Story;
use Illuminate\Support\Str;
use Livewire\Component;

class ApPhotoManager extends Component
{
    public const CATEGORIES = [
        'all' => 'All categories',
        'world' => 'World',
        'politics' => 'Politics',
        'business' => 'Business',
        'sports' => 'Sports',
        'tech' => 'Tech',
        'entertainment' => 'Entertainment',
        'lifestyle' => 'Lifestyle',
        'health' => 'Health',
        'science' => 'Science',
        'environment' => 'Environment',
        'weather' => 'Weather',
        'election' => 'Election',
    ];

    public const SUB_CATEGORIES = [
        'all' => 'All sub categories',
        'Asia' => 'Asia',
        'Europe' => 'Europe',
        'Americas' => 'Americas',
        'Middle East' => 'Middle East',
    ];

    // Filters
    public string $search = '';

    public string $category = 'all';

    public string $subCategory = 'all';

    public string $tag = '';

    public int $perPage = 11;

    // Lightbox & Modal state
    public ?int $selectedId = null;

    public bool $showSyncLog = false;

    public array $attachedIds = [];

    public bool $isSyncing = false;

    public string $lastSyncTime = '9:14 PM';

    public int $todaySyncCount = 214;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => 'all'],
    ];

    public function updatedSearch(): void
    {
        $this->perPage = 11;
    }

    public function updatedCategory(): void
    {
        $this->perPage = 11;
    }

    public function updatedSubCategory(): void
    {
        $this->perPage = 11;
    }

    public function setCategory(string $cat): void
    {
        $this->category = $cat;
        $this->perPage = 11;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->category = 'all';
        $this->subCategory = 'all';
        $this->tag = '';
        $this->perPage = 11;
    }

    public function loadMore(): void
    {
        $this->perPage += 5;
    }

    public function openLightbox(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeLightbox(): void
    {
        $this->selectedId = null;
    }

    public function attachToStory(int $assetId, ?int $storyId = null): void
    {
        $asset = MediaAsset::find($assetId);
        if (! $asset) {
            return;
        }

        $story = $storyId
            ? Story::find($storyId)
            : Story::whereIn('status', ['draft', 'in_review'])->latest()->first();

        if ($story) {
            $story->media()->syncWithoutDetaching([
                $asset->id => ['role' => 'featured', 'sort_order' => 0],
            ]);
        }

        if ($asset->status !== 'library') {
            $asset->update(['status' => 'library']);
        }

        $this->attachedIds[$assetId] = true;
        $this->dispatch('toast', message: 'AP photo attached to story draft');
    }

    public function downloadOriginal(int $assetId): void
    {
        $asset = MediaAsset::find($assetId);
        if ($asset) {
            $asset->increment('download_count');
            $this->dispatch('toast', message: 'Downloading original AP photo: '.Str::limit($asset->caption ?: $asset->title, 35));
        }
    }

    public function syncNow(): void
    {
        $this->lastSyncTime = now()->setTimezone('Asia/Dhaka')->format('g:i A');
        $this->todaySyncCount += 12;
        $this->dispatch('toast', message: 'AP wire synced successfully. '.$this->todaySyncCount.' new photos today.');
    }

    public function toggleSyncLog(): void
    {
        $this->showSyncLog = ! $this->showSyncLog;
    }

    public function render()
    {
        $q = MediaAsset::where('source', 'ap');

        // Category filter
        if ($this->category !== 'all') {
            $cat = strtolower($this->category);
            $q->where(function ($sub) use ($cat) {
                $sub->where('event_label', $cat)
                    ->orWhere('caption', 'like', "%{$cat}%")
                    ->orWhere('en_tags', 'like', "%{$cat}%");
            });
        }

        // Subcategory filter
        if ($this->subCategory !== 'all') {
            $sub = $this->subCategory;
            $q->where(function ($query) use ($sub) {
                $query->where('location_city', 'like', "%{$sub}%")
                    ->orWhere('location_country', 'like', "%{$sub}%")
                    ->orWhere('en_tags', 'like', "%{$sub}%");
            });
        }

        // Tag filter
        if ($this->tag !== '') {
            $t = trim($this->tag);
            $q->where(function ($query) use ($t) {
                $query->where('en_tags', 'like', "%{$t}%")
                    ->orWhere('caption', 'like', "%{$t}%");
            });
        }

        // Search filter
        if ($this->search !== '') {
            $s = trim($this->search);
            $q->where(function ($query) use ($s) {
                $query->where('title', 'like', "%{$s}%")
                    ->orWhere('caption', 'like', "%{$s}%")
                    ->orWhere('location_city', 'like', "%{$s}%")
                    ->orWhere('location_country', 'like', "%{$s}%")
                    ->orWhere('event_label', 'like', "%{$s}%");
            });
        }

        $filteredTotal = (clone $q)->count();
        $totalApCount = MediaAsset::where('source', 'ap')->count();

        $assets = $q->orderByDesc('captured_at')->orderByDesc('created_at')->take($this->perPage)->get();
        $visibleCount = $assets->count();
        $remainingCount = max(0, $filteredTotal - $visibleCount);

        $selected = $this->selectedId ? MediaAsset::find($this->selectedId) : null;

        $syncLogs = [
            [
                'time' => 'Today 9:14 PM',
                'status' => 'Success',
                'photos' => '214 new photos',
                'channel' => 'AP Associated Press Media API v1',
                'duration' => '1.2s',
            ],
            [
                'time' => 'Today 8:59 PM',
                'status' => 'Success',
                'photos' => '189 new photos',
                'channel' => 'AP Associated Press Media API v1',
                'duration' => '1.4s',
            ],
            [
                'time' => 'Today 8:44 PM',
                'status' => 'Success',
                'photos' => '142 new photos',
                'channel' => 'AP Associated Press Media API v1',
                'duration' => '1.1s',
            ],
            [
                'time' => 'Today 8:29 PM',
                'status' => 'Success',
                'photos' => '165 new photos',
                'channel' => 'AP Associated Press Media API v1',
                'duration' => '1.3s',
            ],
        ];

        return view('livewire.admin.ap-photo-manager', [
            'assets' => $assets,
            'selected' => $selected,
            'visibleCount' => $visibleCount,
            'totalApCount' => max(1248, $totalApCount),
            'remainingCount' => $remainingCount,
            'syncLogs' => $syncLogs,
            'categories' => self::CATEGORIES,
            'subCategories' => self::SUB_CATEGORIES,
        ]);
    }
}
