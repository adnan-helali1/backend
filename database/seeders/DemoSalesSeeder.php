<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\SalesItem;
use App\Models\SalesOrder;
use App\Models\SalesPayment;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSalesSeeder extends Seeder
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

        $storeProduct = StoreProduct::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->with('supplierProduct')
            ->first();

        if (! $storeProduct || ! $storeProduct->supplierProduct) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($store, $storeProduct, $now) {
            $customer = Customer::firstOrCreate(
                ['store_id' => $store->id, 'name' => 'Walk-in Customer'],
                ['phone' => null, 'email' => null, 'address' => null]
            );

            $qty = 2;
            $unitSell = $storeProduct->sell_price !== null ? (float) $storeProduct->sell_price : 1.50;
            $unitBuy = (float) $storeProduct->supplierProduct->buy_price;

            $lineTotal = $unitSell * $qty;
            $lineCost = $unitBuy * $qty;

            $sale = SalesOrder::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'status' => 'draft',
                'total' => $lineTotal,
                'total_cost' => $lineCost,
                'profit' => $lineTotal - $lineCost,
                'paid_amount' => 0,
                'notes' => 'Seeded demo sale',
            ]);

            SalesItem::create([
                'sales_order_id' => $sale->id,
                'store_product_id' => $storeProduct->id,
                'supplier_product_id' => $storeProduct->supplierProduct->id,
                'quantity' => $qty,
                'unit_sell_price' => $unitSell,
                'unit_buy_price' => $unitBuy,
                'line_total' => $lineTotal,
                'line_cost' => $lineCost,
            ]);

            // Full payment
            SalesPayment::create([
                'sales_order_id' => $sale->id,
                'amount' => $lineTotal,
                'method' => 'cash',
                'occurred_at' => $now,
                'notes' => 'Seeded payment',
            ]);

            $sale->update([
                'paid_amount' => $lineTotal,
                'status' => 'paid',
            ]);
        });
    }
}
