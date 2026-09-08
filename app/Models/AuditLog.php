<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'diff',
        'ip',
        'user_agent',
        'correlation_id',
        'created_at',
    ];

    protected $casts = [
        'diff' => 'array',
        'created_at' => 'datetime',
        'correlation_id' => 'string',
    ];
}
