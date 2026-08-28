<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'description',
        'entitlement_filter',
        'price_monthly',
        'status',
    ];

    protected $casts = [
        'entitlement_filter' => 'array',
        'price_monthly' => 'decimal:2',
    ];

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'package_media', 'package_id', 'asset_id')->withPivot('added_at');
    }

    public function clientPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }
}
