<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['public_id', 'client_id', 'period_start', 'period_end', 'subtotal', 'discount', 'total', 'status', 'issued_at', 'due_at', 'paid_at'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'issued_at' => 'datetime', 'due_at' => 'datetime', 'paid_at' => 'datetime'];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(fn (Invoice $m) => $m->public_id = $m->public_id ?: (string) Str::ulid());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
