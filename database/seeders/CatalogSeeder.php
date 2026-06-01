<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::where('email', 'store@client.test')->first();
        if (! $store) {
            return;
        }

        $offers = SupplierProduct::query()
            ->where('status', 'available')
            ->with(['product', 'supplier'])
            ->get()
            ->take(4);

        foreach ($offers as $offer) {
            $sellPrice = round(((float) $offer->buy_price) * 1.35, 2);

            StoreProduct::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'supplier_product_id' => $offer->id,
                ],
                [
                    'product_id' => $offer->product_id,
                    'sell_price' => $sellPrice,
                    'is_active' => true,
                ],
            );
        }
    }
}
