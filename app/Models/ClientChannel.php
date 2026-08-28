<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientChannel extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'config',
        'status',
        'last_success_at',
        'failure_count',
    ];

    protected $casts = [
        'config' => 'array',
        'last_success_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'channel_id');
    }
}
