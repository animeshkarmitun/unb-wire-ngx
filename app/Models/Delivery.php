<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'deliverable_type',
        'deliverable_id',
        'client_id',
        'channel_id',
        'status',
        'attempt_count',
        'idempotency_key',
        'payload_hash',
        'response_code',
        'error',
        'sent_at',
        'delivered_at',
        'created_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ClientChannel::class, 'channel_id');
    }
}
