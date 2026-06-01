<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Store extends Authenticatable implements JWTSubject
{
    use HasFactory;

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
