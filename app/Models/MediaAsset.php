<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'kind',
        'status',
        'batch_id',
        'title',
        'caption',
        'credit_line',
        'photographer_id',
        'source',
        'category_id',
        'event_label',
        'location_city',
        'location_country',
        'captured_at',
        'en_tags',
        'width',
        'height',
        'duration_ms',
        'mime',
        'size_bytes',
        'checksum',
        'storage_disk',
        'original_path',
        'derivatives',
        'exif',
        'embargo_until',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'download_count',
    ];

    protected $casts = [
        'en_tags' => 'array',
        'derivatives' => 'array',
        'exif' => 'array',
        'captured_at' => 'datetime',
        'embargo_until' => 'datetime',
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MediaAsset $asset) {
            if (empty($asset->public_id)) {
                $asset->public_id = (string) Str::ulid();
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MediaBatch::class, 'batch_id');
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEmbargoed(): bool
    {
        return $this->embargo_until !== null && $this->embargo_until->isFuture();
    }

    public function thumbUrl(): ?string
    {
        $path = $this->derivatives['thumb']['path'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return $this->fileUrl('thumb', $path);
    }

    /**
     * Staff-authenticated URL for a variant (default original) — used by admin
     * previews. Private disks are never web-served directly.
     */
    public function staffUrl(string $variant = 'original'): ?string
    {
        $path = $variant === 'original'
            ? $this->original_path
            : ($this->derivatives[$variant]['path'] ?? null);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return $this->fileUrl($variant, $path);
    }

    private function fileUrl(string $variant, string $path): ?string
    {
        $disk = $this->storage_disk ?: 's3';
        try {
            if ($disk === 'local') {
                return route('admin.media.file', ['asset' => $this->public_id, 'variant' => $variant]);
            }

            return Storage::disk($disk)->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function scopeClientVisible($query)
    {
        return $query->where(fn ($q) => $q->whereNull('embargo_until')->orWhere('embargo_until', '<=', now()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MediaReview::class, 'asset_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'media_tag', 'asset_id', 'tag_id');
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_media', 'asset_id', 'story_id')->withPivot(['role', 'sort_order', 'caption_override']);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_media', 'asset_id', 'package_id')->withPivot('added_at');
    }
}
