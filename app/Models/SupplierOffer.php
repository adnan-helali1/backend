<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_product_id',
        'offer_price',
        'offer_stock',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'offer_price' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_product_id');
    }
}
