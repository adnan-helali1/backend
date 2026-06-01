<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;

class SupplierCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Beverages'],
            ['name' => 'Snacks'],
            ['name' => 'Dairy'],
        ])->map(fn ($c) => Category::firstOrCreate(['name' => $c['name']], ['image' => null]));

        $suppliers = collect([
            ['name' => 'Supplier One', 'status' => 'active'],
            ['name' => 'Supplier Two', 'status' => 'active'],
        ])->map(function ($s) {
            return Supplier::firstOrCreate(
                ['name' => $s['name']],
                ['phone' => null, 'email' => null, 'address' => null, 'status' => $s['status']]
            );
        });

        // Assign categories to suppliers
        $suppliers[0]->categories()->sync($categories->pluck('id')->take(2)->all()); // Beverages, Snacks
        $suppliers[1]->categories()->sync($categories->pluck('id')->slice(1, 2)->all()); // Snacks, Dairy

        // Master products
        $products = collect([
            ['name' => 'Cola Can', 'category' => 'Beverages'],
            ['name' => 'Orange Juice', 'category' => 'Beverages'],
            ['name' => 'Chips Classic', 'category' => 'Snacks'],
            ['name' => 'Milk 1L', 'category' => 'Dairy'],
        ])->map(function ($p) use ($categories, $suppliers) {
            $category = $categories->firstWhere('name', $p['category']);

            // Keep legacy supplier_id filled (not used as source of truth anymore),
            // choose supplier that has this category.
            $fallbackSupplier = $suppliers->first(fn ($s) => $s->categories()->whereKey($category->id)->exists());

            return Product::firstOrCreate(
                ['name' => $p['name'], 'category_id' => $category->id],
                [
                    'supplier_id' => $fallbackSupplier->id,
                    'description' => null,
                    'buy_price' => 0,
                    'stock_quantity' => 0,
                    'status' => 'available',
                ]
            );
        });

        // Supplier offers (multi-supplier)
        $offers = [
            // Supplier One offers beverages + snacks
            ['supplier' => 'Supplier One', 'product' => 'Cola Can', 'buy_price' => 0.65, 'stock' => 200],
            ['supplier' => 'Supplier One', 'product' => 'Orange Juice', 'buy_price' => 1.10, 'stock' => 120],
            ['supplier' => 'Supplier One', 'product' => 'Chips Classic', 'buy_price' => 0.80, 'stock' => 160],

            // Supplier Two offers snacks + dairy (and also chips as a second supplier)
            ['supplier' => 'Supplier Two', 'product' => 'Chips Classic', 'buy_price' => 0.75, 'stock' => 90],
            ['supplier' => 'Supplier Two', 'product' => 'Milk 1L', 'buy_price' => 1.00, 'stock' => 70],
        ];

        foreach ($offers as $o) {
            $supplier = $suppliers->firstWhere('name', $o['supplier']);
            $product = $products->firstWhere('name', $o['product']);

            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                [
                    'buy_price' => $o['buy_price'],
                    'stock_quantity' => $o['stock'],
                    'status' => 'available',
                ],
            );
        }
    }
}
