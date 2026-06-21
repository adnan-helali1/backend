<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'supplier_product_id',
        'sell_price',
        'is_active',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }public function inventory(): HasOne
{
    return $this->hasOne(StoreInventory::class);
}
}
