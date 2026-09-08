<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryEvent extends Model
{
    use HasFactory;

    public const ACTIONS = [
        'created' => 'Created',
        'sent_to_review' => 'Sent to review',
        'changes_requested' => 'Changes requested',
        'approved' => 'Approved',
        'published' => 'Published',
        'auto_published' => 'Auto-published',
        'killed' => 'Killed',
        'archived' => 'Archived',
        'handover' => 'Handover',
        'ai_applied' => 'AI applied',
        'note_added' => 'Note added',
        'restored' => 'Restored',
    ];

    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
