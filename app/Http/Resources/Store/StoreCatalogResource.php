<?php

namespace App\Http\Resources\Store;

use App\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoreProduct */
class StoreCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $supplierProduct = $this->supplierProduct;
        $product = $supplierProduct?->product;
        $buyPrice = (float) ($supplierProduct?->buy_price ?? 0);
        $sellPrice = (float) ($this->sell_price ?? 0);
        $stock = (int) ($supplierProduct?->stock_quantity ?? 0);
        $profitPerUnit = round($sellPrice - $buyPrice, 2);
        $profitPercentage = $buyPrice > 0
            ? round(($profitPerUnit / $buyPrice) * 100, 2)
            : 0.0;
        $totalProfit = round($profitPerUnit * $stock, 2);

        return [
            'id' => $this->id,
            'name' => $product?->name ?? '',
            'supplier_name' => $supplierProduct?->supplier?->name ?? '',
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'profit_per_unit' => $profitPerUnit,
            'profit_percentage' => $profitPercentage,
            'stock' => $stock,
            'total_profit' => $totalProfit,
            'is_active' => (bool) $this->is_active,
            'image_url' => $product?->category?->image_url,
        ];
    }
}
