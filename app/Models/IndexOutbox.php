<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexOutbox extends Model
{
    public $timestamps = false;

    protected $table = 'index_outbox';

    protected $fillable = [
        'index_name',
        'op',
        'document_id',
        'status',
        'attempts',
        'created_at',
        'processed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
