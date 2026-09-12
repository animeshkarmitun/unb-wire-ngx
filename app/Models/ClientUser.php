<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class ClientUser extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'email',
        'password',
        'client_role_id',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'client_role_id');
    }
}
