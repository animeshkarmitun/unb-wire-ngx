<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'name',
        'code',
        'type',
        'country',
        'timezone',
        'status',
        'billing_email',
        'notes',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->public_id)) {
                $client->public_id = (string) Str::ulid();
            }
        });
    }

    public function clientUsers(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ClientApiKey::class);
    }
}
