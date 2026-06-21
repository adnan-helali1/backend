<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventory extends Model
{
    protected $fillable = [
        'store_id',
        'store_product_id',
        'quantity',
        'min_stock',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'min_stock' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function storeProduct()
    {
        return $this->belongsTo(StoreProduct::class);
    }
}