<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = ['title','description','shot_list','location','due_at','priority','status','assignee_id','created_by'];
    protected $casts = ['shot_list' => 'array', 'due_at' => 'datetime'];

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function batches(): HasMany { return $this->hasMany(MediaBatch::class, 'assignment_id'); }
}
