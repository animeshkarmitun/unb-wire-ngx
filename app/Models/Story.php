<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Story extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'language',
        'mirror_of_id',
        'status',
        'headline',
        'sub_head',
        'brief',
        'body_html',
        'body_text',
        'category_id',
        'sub_category_id',
        'dateline_city',
        'dateline_at',
        'published_at',
        'embargo_until',
        'is_breaking',
        'priority',
        'source',
        'owner_id',
        'assigned_editor_id',
        'locked_by',
        'locked_at',
        'version',
        'ai_touched',
        'word_count',
        'created_by',
    ];

    protected $casts = [
        'dateline_at' => 'datetime',
        'published_at' => 'datetime',
        'embargo_until' => 'datetime',
        'locked_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_breaking' => 'boolean',
        'ai_touched' => 'array',
        'version' => 'integer',
        'word_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Story $story) {
            if (empty($story->public_id)) {
                $story->public_id = (string) Str::ulid();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function mirrorOf(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'mirror_of_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_editor_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StoryVersion::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(StoryNote::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StoryEvent::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'story_tag');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'deliverable_id')->where('deliverable_type', 'story');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'story_media', 'story_id', 'asset_id')->withPivot(['role', 'sort_order', 'caption_override']);
    }
}
