<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\MediaReview;
use App\Models\Package;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaRepository
{
    // ─── Read Methods ───────────────────────────────────────────

    public function findById(int $id): ?MediaAsset
    {
        return MediaAsset::find($id);
    }

    public function findByPublicId(string $publicId): ?MediaAsset
    {
        return MediaAsset::where('public_id', $publicId)->first();
    }

    public function findWithRelations(int $id): ?MediaAsset
    {
        return MediaAsset::with(['category', 'photographer', 'packages', 'stories'])->find($id);
    }

    public function libraryAssets(int $limit = 24): Collection
    {
        return MediaAsset::where('status', 'library')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getWorkflowTabCounts(): array
    {
        return [
            'all' => MediaAsset::whereNotIn('status', ['field', 'rejected', 'reedit'])->count(),
            'field' => MediaAsset::where('status', 'field')->whereNotNull('batch_id')->count(),
            'review' => MediaAsset::where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('status', 'field')->whereNull('batch_id');
                })->orWhere(function ($sq) {
                    $sq->where('status', 'library')->whereNull('approved_at');
                });
            })->count(),
            'library' => MediaAsset::where('status', 'library')->whereNotNull('approved_at')->count(),
            'packaged' => MediaAsset::whereHas('packages')->count(),
            'published' => MediaAsset::whereHas('stories', fn ($q) => $q->where('stories.status', 'published'))->count(),
            'embargo' => MediaAsset::whereNotNull('embargo_until')->where('embargo_until', '>', now())->count(),
        ];
    }

    public function paginateAssets(
        string $tab,
        string $search,
        string $photographer,
        string $category,
        bool $unattachedOnly,
        string $sort,
        int $perPage = 36,
    ): LengthAwarePaginator {
        $query = MediaAsset::with(['category', 'photographer', 'packages', 'stories']);

        // Tab conditions
        if ($tab === 'all') {
            $query->whereNotIn('status', ['field', 'rejected', 'reedit']);
        } elseif ($tab === 'review') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('status', 'field')->whereNull('batch_id');
                })->orWhere(function ($sq) {
                    $sq->where('status', 'library')->whereNull('approved_at');
                });
            });
        } elseif ($tab === 'library') {
            $query->where('status', 'library')->whereNotNull('approved_at');
        } elseif ($tab === 'packaged') {
            $query->whereHas('packages');
        } elseif ($tab === 'published') {
            $query->whereHas('stories', fn ($q) => $q->where('stories.status', 'published'));
        } elseif ($tab === 'embargo') {
            $query->whereNotNull('embargo_until')->where('embargo_until', '>', now());
        }

        // Search query
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('caption', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('credit_line', 'like', $term)
                    ->orWhere('event_label', 'like', $term)
                    ->orWhere('location_city', 'like', $term);
            });
        }

        // Photographer filter
        if ($photographer !== 'all') {
            $query->where(function ($q) use ($photographer) {
                $q->where('credit_line', 'like', '%'.$photographer.'%')
                    ->orWhereHas('photographer', fn ($pq) => $pq->where('name', $photographer));
            });
        }

        // Category filter
        if ($category !== 'all') {
            $query->whereHas('category', fn ($cq) => $cq->where('name_en', $category)->orWhere('slug', Str::slug($category)));
        }

        // Unattached only filter
        if ($unattachedOnly) {
            $query->doesntHave('stories');
        }

        // Sorting
        if ($sort === 'dl') {
            $query->orderByDesc('download_count')->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query->paginate($perPage);
    }

    public function getPendingBatches(): Collection
    {
        return MediaBatch::with([
            'uploader',
            'assets' => fn ($q) => $q->where('status', 'field')->orderBy('id'),
        ])
            ->whereHas('assets', fn ($q) => $q->where('status', 'field'))
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->get();
    }

    public function getPhotographersList(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'like', '%Uploader%'))
            ->orWhereNotNull('name')
            ->pluck('name')
            ->unique()
            ->filter()
            ->values();
    }

    public function getRootCategoriesList(): Collection
    {
        return Category::whereNull('parent_id')->pluck('name_en')->filter()->values();
    }

    // ─── Write Methods ─────────────────────────────────────────

    public function create(array $data): MediaAsset
    {
        return MediaAsset::create($data);
    }

    public function update(MediaAsset $asset, array $data): MediaAsset
    {
        $asset->update($data);

        return $asset->refresh();
    }

    public function bulkUpdateStatus(array $ids, string $status, ?int $approvedBy = null): int
    {
        $data = ['status' => $status];
        if ($status === 'library') {
            $data['approved_at'] = now();
            $data['approved_by'] = $approvedBy;
        }

        return MediaAsset::whereIn('id', $ids)
            ->where(function ($q) {
                $q->where('status', 'field')->orWhereNull('approved_at');
            })
            ->update($data);
    }

    public function syncPackages(int $assetId, ?string $packageName): void
    {
        DB::table('package_media')->where('asset_id', $assetId)->delete();
        if ($packageName && $packageName !== '—') {
            $pkgCode = $packageName === 'Exclusive' ? 'PREMIUM-BUNDLE' : 'STANDARD-NEWS';
            $pkg = Package::where('code', $pkgCode)->first();
            if ($pkg) {
                DB::table('package_media')->insert([
                    'package_id' => $pkg->id,
                    'asset_id' => $assetId,
                    'added_at' => now(),
                ]);
            }
        }
    }

    public function bulkSyncPackages(array $assetIds, string $packageName): void
    {
        $pkgCode = $packageName === 'Exclusive' ? 'PREMIUM-BUNDLE' : 'STANDARD-NEWS';
        $pkg = Package::where('code', $pkgCode)->first();
        if (! $pkg) {
            return;
        }

        foreach ($assetIds as $assetId) {
            DB::table('package_media')->updateOrInsert(
                ['package_id' => $pkg->id, 'asset_id' => $assetId],
                ['added_at' => now()]
            );
        }
    }

    public function createReview(array $data): MediaReview
    {
        return MediaReview::create($data);
    }

    public function linkToStory(int $storyId, int $assetId, string $role = 'featured'): void
    {
        DB::table('story_media')->updateOrInsert(
            ['story_id' => $storyId, 'asset_id' => $assetId],
            ['role' => $role, 'sort_order' => 1]
        );
    }

    public function findBatchWithAssets(int $batchId): ?MediaBatch
    {
        return MediaBatch::with('assets')->find($batchId);
    }

    public function updateBatch(MediaBatch $batch, array $data): MediaBatch
    {
        $batch->update($data);

        return $batch;
    }
}
