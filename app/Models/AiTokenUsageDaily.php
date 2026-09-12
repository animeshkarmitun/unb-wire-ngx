<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTokenUsageDaily extends Model
{
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'date',
        'scope',
        'kind',
        'tokens',
        'cost_micros',
    ];

    protected $casts = [
        'date' => 'date',
        'tokens' => 'integer',
        'cost_micros' => 'integer',
    ];
}
