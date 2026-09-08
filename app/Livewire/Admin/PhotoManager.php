<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\MediaReview;
use App\Models\User;
use App\Repositories\MediaRepository;
use App\Repositories\StoryRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PhotoManager extends Component
{
    use WithFileUploads, WithPagination;

    // Workflow tabs: all, field, review, library, packaged, published, embargo
    public string $tab = 'all';

    // Toolbar filters
    public string $search = '';

    public string $photographer = 'all';

    public string $category = 'all';

    public string $sort = 'new';

    public bool $unattachedOnly = false;

    // Multi-select & Bulk Bar
    public array $selectedIds = [];

    // Burst Series Stack expansion
    public ?int $expandedStackId = null;

    // Sticky Inspector Panel
    public ?int $selectedAssetId = null;

    public string $inspCaption = '';

    public string $inspPhotographer = '';

    public string $inspLocation = '';

    public string $inspKeywords = '';

    public string $inspPackage = '—';

    public string $inspStoryInput = '';

    // Field Intake Queue Reason Modal
    public bool $modalOpen = false;

    public string $modalType = ''; // 'reject_batch', 'reedit_batch', 'reject_photo'

    public string $modalTitle = '';

    public ?int $targetBatchId = null;

    public ?int $targetAssetId = null;

    public string $selectedReason = '';

    public string $reasonNote = '';

    // Uploads
    public $uploads = [];

    protected $queryString = [
        'tab' => ['except' => 'all'],
        'search' => ['except' => ''],
        'photographer' => ['except' => 'all'],
        'category' => ['except' => 'all'],
        'sort' => ['except' => 'new'],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPhotographer(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedUploads(): void
    {
        $this->handleUploads();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
        $this->selectedIds = [];
        $this->expandedStackId = null;
    }

    public function toggleUnattached(): void
    {
        $this->unattachedOnly = ! $this->unattachedOnly;
        $this->resetPage();
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function selectAll(array $ids): void
    {
        $this->selectedIds = $ids;
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function toggleStack(int $id): void
    {
        $this->expandedStackId = $this->expandedStackId === $id ? null : $id;
    }

    public function setStackCover(int $parentId, int $frame): void
    {
        $parent = MediaAsset::find($parentId);
        if (! $parent) {
            return;
        }

        $derivatives = $parent->derivatives ?? [];
        $rValues = [1.5, 1.33, 1.78, 1.5, 0.8];
        $newR = $rValues[($frame - 1) % 5];
        $newGrad = 'g'.(($frame % 8) + 1);

        $derivatives['r'] = $newR;
        $derivatives['grad'] = $newGrad;
        $parent->derivatives = $derivatives;
        $parent->save();

        $this->dispatch('toast', message: '✓ Frame '.$frame.' set as stack cover');
    }

    // Inspector
    public function selectAsset(int $id): void
    {
        if ($this->selectedAssetId === $id) {
            $this->selectedAssetId = null;

            return;
        }

        $this->selectedAssetId = $id;
        $asset = app(MediaRepository::class)->findWithRelations($id);
        if (! $asset) {
            $this->selectedAssetId = null;

            return;
        }

        $this->inspCaption = (string) ($asset->caption ?: $asset->title);
        $this->inspPhotographer = (string) ($asset->photographer?->name ?: str_replace(['Photo: ', ' / UNB'], '', $asset->credit_line));
        $this->inspLocation = (string) ($asset->location_city ?: '');
        $this->inspKeywords = is_array($asset->en_tags) ? implode(', ', $asset->en_tags) : '';

        $pkg = $asset->packages->first();
        $this->inspPackage = $pkg ? ($pkg->code === 'PREMIUM-BUNDLE' ? 'Exclusive' : 'Standard') : '—';
        $this->inspStoryInput = '';
    }

    public function closeInspector(): void
    {
        $this->selectedAssetId = null;
    }

    public function saveAssetMetadata(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }

        $repo = app(MediaRepository::class);
        $asset = $repo->findById($this->selectedAssetId);
        if (! $asset) {
            return;
        }

        $tags = array_values(array_filter(array_map('trim', explode(',', $this->inspKeywords))));

        $repo->update($asset, [
            'caption' => $this->inspCaption,
            'title' => Str::limit($this->inspCaption, 120),
            'credit_line' => 'Photo: '.$this->inspPhotographer.' / UNB',
            'location_city' => $this->inspLocation,
            'en_tags' => $tags,
        ]);

        // Update package assignment
        $repo->syncPackages($asset->id, $this->inspPackage);

        $this->dispatch('toast', message: '✓ Metadata saved');
    }

    public function approveInspected(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }

        $repo = app(MediaRepository::class);
        $asset = $repo->findById($this->selectedAssetId);
        if ($asset) {
            $repo->update($asset, [
                'status' => 'library',
                'approved_at' => now(),
                'approved_by' => auth()->id() ?? User::first()?->id,
            ]);
            $this->dispatch('toast', message: '✓ Approved — now in library');
        }
    }

    public function linkAssetStory(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }
        $query = trim($this->inspStoryInput);
        if ($query === '') {
            $this->dispatch('toast', message: 'Type a story title or ID first');

            return;
        }

        $storyRepo = app(StoryRepository::class);
        $story = is_numeric($query)
            ? $storyRepo->findWithMinimal((int) $query)
            : \App\Models\Story::where('headline', 'like', '%'.$query.'%')->first();

        if (! $story) {
            $this->dispatch('toast', message: 'No matching story found');

            return;
        }

        app(MediaRepository::class)->linkToStory($story->id, $this->selectedAssetId);

        $this->inspStoryInput = '';
        $this->dispatch('toast', message: '✓ Linked to story: '.Str::limit($story->headline, 30));
    }

    // Bulk actions
    public function bulkApprove(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $count = MediaAsset::whereIn('id', $this->selectedIds)
            ->where(function ($q) {
                $q->where('status', 'field')->orWhereNull('approved_at');
            })
            ->update([
                'status' => 'library',
                'approved_at' => now(),
                'approved_by' => auth()->id() ?? User::first()?->id,
            ]);

        $this->selectedIds = [];
        $this->dispatch('toast', message: $count > 0 ? '✓ '.$count.' photo'.($count > 1 ? 's' : '').' approved to library' : 'Nothing in “Needs review” among selected');
    }

    public function bulkAssignPackage(string $packageName): void
    {
        if (empty($this->selectedIds) || empty($packageName)) {
            return;
        }

        app(MediaRepository::class)->bulkSyncPackages($this->selectedIds, $packageName);

        $count = count($this->selectedIds);
        $this->dispatch('toast', message: '✓ '.$count.' assets assigned to '.$packageName.' package');
    }

    // Field Queue Actions
    public function approve(int $id): void
    {
        $this->approveFieldAsset($id);
    }

    public function approveFieldAsset(int $id): void
    {
        $repo = app(MediaRepository::class);
        $asset = $repo->findById($id);
        if (! $asset) {
            return;
        }

        $reviewerId = auth()->id() ?? User::first()?->id;

        $repo->update($asset, [
            'status' => 'library',
            'approved_at' => now(),
            'approved_by' => $reviewerId,
        ]);

        // Auto assign package based on urgency if no package
        if (! $asset->packages()->exists()) {
            $pkgCode = $asset->event_label && str_contains(strtolower($asset->event_label), 'breaking') ? 'PREMIUM-BUNDLE' : 'STANDARD-NEWS';
            $repo->syncPackages($asset->id, $pkgCode === 'PREMIUM-BUNDLE' ? 'Exclusive' : 'Standard');
        }

        $repo->createReview([
            'asset_id' => $asset->id,
            'reviewer_id' => $reviewerId,
            'action' => 'approve',
            'created_at' => now(),
        ]);

        $photogName = $asset->credit_line ? str_replace(['Photo: ', ' / UNB'], '', $asset->credit_line) : 'photographer';
        $this->dispatch('toast', message: '✓ Approved to library — '.$photogName.' notified');
    }

    public function approveFieldBatch(int $batchId): void
    {
        $repo = app(MediaRepository::class);
        $batch = $repo->findBatchWithAssets($batchId);
        if (! $batch) {
            return;
        }

        $reviewerId = auth()->id() ?? User::first()?->id;
        $count = $batch->assets->count();

        foreach ($batch->assets as $asset) {
            $repo->update($asset, [
                'status' => 'library',
                'approved_at' => now(),
                'approved_by' => $reviewerId,
            ]);

            $repo->createReview([
                'asset_id' => $asset->id,
                'reviewer_id' => $reviewerId,
                'action' => 'approve',
                'created_at' => now(),
            ]);
        }

        $repo->updateBatch($batch, [
            'status' => 'reviewed',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        $photogName = $batch->uploader?->name ?? 'photographer';
        $this->dispatch('toast', message: '✓ '.$count.' frames approved to library — '.$photogName.' notified');
    }

    // Modal: Reject / Request Re-edit
    public function openModal(string $type, ?int $batchId = null, ?int $assetId = null): void
    {
        $this->modalType = $type;
        $this->targetBatchId = $batchId;
        $this->targetAssetId = $assetId;
        $this->reasonNote = '';

        if ($type === 'reject_photo') {
            $this->modalTitle = 'Reject photo';
            $this->selectedReason = 'Out of focus / soft at full size';
        } elseif ($type === 'reject_batch') {
            $batch = MediaBatch::find($batchId);
            $this->modalTitle = 'Reject batch'.($batch ? ' — '.$batch->event_label : '');
            $this->selectedReason = 'Out of focus / soft at full size';
        } elseif ($type === 'reedit_batch') {
            $batch = MediaBatch::find($batchId);
            $this->modalTitle = 'Request re-edit'.($batch ? ' — '.$batch->event_label : '');
            $this->selectedReason = 'Captions need names / places filled in';
        }

        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->targetBatchId = null;
        $this->targetAssetId = null;
        $this->selectedReason = '';
        $this->reasonNote = '';
    }

    public function confirmModalAction(): void
    {
        $reviewerId = auth()->id() ?? User::first()?->id;
        $fullNote = $this->selectedReason.($this->reasonNote ? ' — '.trim($this->reasonNote) : '');

        if ($this->modalType === 'reject_photo' && $this->targetAssetId) {
            $asset = MediaAsset::find($this->targetAssetId);
            if ($asset) {
                $asset->update(['status' => 'rejected']);
                MediaReview::create([
                    'asset_id' => $asset->id,
                    'reviewer_id' => $reviewerId,
                    'action' => 'reject',
                    'reason_code' => Str::slug(Str::limit($this->selectedReason, 40)),
                    'note' => $fullNote,
                    'created_at' => now(),
                ]);
                $photogName = $asset->credit_line ? str_replace(['Photo: ', ' / UNB'], '', $asset->credit_line) : 'photographer';
                $this->dispatch('toast', message: '✕ Photo rejected — '.$photogName.' notified with reason');
            }
        } elseif ($this->modalType === 'reject_batch' && $this->targetBatchId) {
            $batch = MediaBatch::with('assets')->find($this->targetBatchId);
            if ($batch) {
                foreach ($batch->assets as $asset) {
                    $asset->update(['status' => 'rejected']);
                    MediaReview::create([
                        'asset_id' => $asset->id,
                        'reviewer_id' => $reviewerId,
                        'action' => 'reject',
                        'reason_code' => Str::slug(Str::limit($this->selectedReason, 40)),
                        'note' => $fullNote,
                        'created_at' => now(),
                    ]);
                }
                $batch->update([
                    'status' => 'reviewed',
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                ]);
                $photogName = $batch->uploader?->name ?? 'photographer';
                $this->dispatch('toast', message: '✕ Batch rejected — '.$photogName.' notified with reason');
            }
        } elseif ($this->modalType === 'reedit_batch' && $this->targetBatchId) {
            $batch = MediaBatch::with('assets')->find($this->targetBatchId);
            if ($batch) {
                foreach ($batch->assets as $asset) {
                    $asset->update(['status' => 'reedit']);
                    MediaReview::create([
                        'asset_id' => $asset->id,
                        'reviewer_id' => $reviewerId,
                        'action' => 'reedit',
                        'reason_code' => Str::slug(Str::limit($this->selectedReason, 40)),
                        'note' => $fullNote,
                        'created_at' => now(),
                    ]);
                }
                $batch->update([
                    'status' => 'reviewed',
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                ]);
                $photogName = $batch->uploader?->name ?? 'photographer';
                $this->dispatch('toast', message: '↩ Sent back — '.$photogName.' asked to fix and resend');
            }
        }

        $this->closeModal();
    }

    public function handleUploads(): void
    {
        $this->validate(['uploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240']);
        $count = count($this->uploads);
        $uploaderId = auth()->id() ?? User::first()?->id;

        $gradientIndex = 1;
        foreach ($this->uploads as $file) {
            $path = $file->store('media/uploads', 'public');
            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanTitle = ucwords(str_replace(['-', '_'], ' ', $filename));

            $size = @getimagesize($file->getRealPath());
            $width = $size[0] ?? 1200;
            $height = $size[1] ?? 800;
            $ratio = $height > 0 ? round($width / $height, 2) : 1.5;

            MediaAsset::create([
                'public_id' => (string) Str::ulid(),
                'title' => $cleanTitle,
                'caption' => $cleanTitle,
                'credit_line' => 'Photo: Field upload / UNB',
                'kind' => 'photo',
                'status' => 'field',
                'mime' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'storage_disk' => 'public',
                'original_path' => $path,
                'derivatives' => [
                    'grad' => 'g'.(($gradientIndex % 8) + 1),
                    'r' => $ratio,
                ],
                'uploaded_by' => $uploaderId,
            ]);
            $gradientIndex++;
        }

        $this->uploads = [];
        $this->dispatch('toast', message: '✓ '.$count.' photo'.($count > 1 ? 's' : '').' added to “Needs review”');
    }

    #[On('echo:photo-desk,MediaUploaded')]
    public function onMediaUploaded(array $payload): void
    {
        $this->dispatch('toast', message: 'New photo: '.($payload['title'] ?? 'upload'));
    }

    public function render()
    {
        $repo = app(MediaRepository::class);

        // 1. Live Tab Counts
        $counts = $repo->getWorkflowTabCounts();

        // 2. Field Batches (for field intake tab)
        $fieldBatches = $this->tab === 'field' ? $repo->getPendingBatches() : null;

        // 3. Asset Query (for photo grid)
        $assets = $this->tab === 'field' ? collect([]) : $repo->paginateAssets(
            $this->tab,
            $this->search,
            $this->photographer,
            $this->category,
            $this->unattachedOnly,
            $this->sort,
        );

        // Photographers list for filter
        $photographersList = $repo->getPhotographersList();

        // Categories list for filter
        $categoriesList = $repo->getRootCategoriesList();

        // Selected Asset for Inspector
        $selected = $this->selectedAssetId ? $repo->findWithRelations($this->selectedAssetId) : null;

        return view('livewire.admin.photo-manager', compact(
            'counts',
            'fieldBatches',
            'assets',
            'photographersList',
            'categoriesList',
            'selected'
        ));
    }
}
