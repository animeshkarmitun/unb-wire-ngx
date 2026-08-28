<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'user_id',
        'kind',
        'prompt_version',
        'model',
        'input_hash',
        'pack',
        'new_facts',
        'tokens_in',
        'tokens_out',
        'cost_micros',
        'applied',
        'created_at',
    ];

    protected $casts = [
        'pack' => 'array',
        'new_facts' => 'array',
        'applied' => 'array',
        'created_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
