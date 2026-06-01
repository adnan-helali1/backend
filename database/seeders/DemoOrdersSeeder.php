<?php

namespace Database\Seeders;

use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrdersSeeder extends Seeder
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

        $offer = SupplierProduct::query()
            ->where('status', 'available')
            ->where('stock_quantity', '>=', 3)
            ->with(['supplier', 'product'])
            ->first();

        if (! $offer) {
            return;
        }

        $now = now();

        DB::transaction(function () use ($store, $offer, $now) {
            $order = PurchaseOrder::create([
                'store_id' => $store->id,
                'supplier_id' => $offer->supplier_id,
                'status' => 'submitted',
                'total_buy' => 0,
                'total_sell' => null,
                'notes' => 'Seeded demo purchase order',
            ]);

            $qty = 2;
            $unitBuy = (float) $offer->buy_price;
            $totalBuy = $unitBuy * $qty;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $offer->product_id,
                'supplier_product_id' => $offer->id,
                'quantity' => $qty,
                'unit_buy_price' => $unitBuy,
                'unit_sell_price' => null,
            ]);

            $offer->decrement('stock_quantity', $qty);

            $order->update(['total_buy' => $totalBuy]);

            StoreLedgerEntry::create([
                'store_id' => $store->id,
                'type' => 'debit',
                'source_type' => 'order',
                'source_id' => $order->id,
                'amount' => $totalBuy,
                'occurred_at' => $now,
                'notes' => 'Seeded order debit',
                'created_by_admin_id' => null,
            ]);
        });
    }
}
