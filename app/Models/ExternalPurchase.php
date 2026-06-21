<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalPurchase extends Model
{
    protected $fillable = [
        'store_id',
        'store_product_id',
        'quantity',
        'unit_price',
        'seller_name',
        'occurred_at',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }
}
