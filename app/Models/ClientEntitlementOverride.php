<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientEntitlementOverride extends Model
{
    protected $fillable = [
        'client_id',
        'override_stories_quota',
        'stories_quota',
        'override_media_quota',
        'media_quota',
        'updated_by',
    ];

    protected $casts = [
        'override_stories_quota' => 'boolean',
        'stories_quota' => 'integer',
        'override_media_quota' => 'boolean',
        'media_quota' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
