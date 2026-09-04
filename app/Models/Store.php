<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Store extends Authenticatable implements HasMedia, JWTSubject
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'owner_name',
        'phone',
        'email',
        'password',
        'address',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $with = [
        'media',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('image') ?: null;
    }

    public function catalog(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
