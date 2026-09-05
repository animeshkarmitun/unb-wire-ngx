<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use SoftDeletes;

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
