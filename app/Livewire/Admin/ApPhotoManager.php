<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\Story;
use App\Repositories\MediaRepository;
use App\Repositories\StoryRepository;
use App\Services\RbacService;
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

    public int $todaySyncCount = 0;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        app(RbacService::class)->assertCan(auth()->user(), 'media', 'view');
    }

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
        app(RbacService::class)->assertCan(auth()->user(), 'media', 'edit');

        $mediaRepo = app(MediaRepository::class);
        $asset = $mediaRepo->findById($assetId);
        if (! $asset) {
            return;
        }

        $storyRepo = app(StoryRepository::class);
        $story = $storyId
            ? $storyRepo->findWithMinimal($storyId)
            : Story::whereIn('status', ['draft', 'in_review'])->latest()->first();

        if ($story) {
            $story->media()->syncWithoutDetaching([
                $asset->id => ['role' => 'featured', 'sort_order' => 0],
            ]);
        }

        if ($asset->status !== 'library') {
            $mediaRepo->update($asset, ['status' => 'library']);
        }

        $this->attachedIds[$assetId] = true;
        $this->dispatch('toast', message: 'AP photo attached to story draft');
    }

    public function downloadOriginal(int $assetId): void
    {
        $asset = app(MediaRepository::class)->findById($assetId);
        if ($asset) {
            $asset->increment('download_count');
            $this->dispatch('toast', message: 'Downloading original AP photo: '.Str::limit($asset->caption ?: $asset->title, 35));
        }
    }

    public function syncNow(): void
    {
        $this->lastSyncTime = now()->setTimezone('Asia/Dhaka')->format('g:i A');
        $this->dispatch('toast', message: 'AP feed not configured — add partner credentials to enable sync');
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

        $selected = $this->selectedId ? app(MediaRepository::class)->findById($this->selectedId) : null;

        $syncLogs = [];

        return view('livewire.admin.ap-photo-manager', [
            'assets' => $assets,
            'selected' => $selected,
            'visibleCount' => $visibleCount,
            'totalApCount' => $totalApCount,
            'remainingCount' => $remainingCount,
            'syncLogs' => $syncLogs,
            'categories' => self::CATEGORIES,
            'subCategories' => self::SUB_CATEGORIES,
        ]);
    }
}
