<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'store_product_id',
        'supplier_product_id',
        'quantity',
        'unit_sell_price',
        'unit_buy_price',
        'line_total',
        'line_cost',
    ];

    protected $casts = [
        'unit_sell_price' => 'decimal:2',
        'unit_buy_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_cost' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
