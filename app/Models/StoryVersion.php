<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'version',
        'snapshot',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
