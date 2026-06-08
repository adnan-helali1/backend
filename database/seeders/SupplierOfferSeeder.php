<?php

namespace Database\Seeders;

use App\Models\SupplierOffer;
use Illuminate\Database\Seeder;

class SupplierOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offers = [
            [
                'supplier_product_id' => 1, // Cola Can - Supplier One
                'offer_price' => 2.50,
                'offer_stock' => 150,
                'status' => 'available',
                'expires_at' => now()->addDays(30),
            ],
            [
                'supplier_product_id' => 2, // Orange Juice - Supplier One
                'offer_price' => 2.35,
                'offer_stock' => 200,
                'status' => 'available',
                'expires_at' => now()->addDays(25),
            ],
            [
                'supplier_product_id' => 3, // Chips Classic - Supplier One
                'offer_price' => 1.80,
                'offer_stock' => 80,
                'status' => 'available',
                'expires_at' => now()->addDays(10),
            ],
            [
                'supplier_product_id' => 4, // Chips Classic - Supplier Two
                'offer_price' => 4.20,
                'offer_stock' => 60,
                'status' => 'available',
                'expires_at' => now()->addDays(20),
            ],
            [
                'supplier_product_id' => 5, // Milk 1L - Supplier Two
                'offer_price' => 1.20,
                'offer_stock' => 0,
                'status' => 'unavailable',
                'expires_at' => now()->addDays(5),
            ],
        ];

        foreach ($offers as $offer) {
            SupplierOffer::create($offer);
        }
    }
}
