<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            // FK is added in a later migration to avoid ordering issues on MySQL
            // when this migration runs before `sales_orders`.
            $table->unsignedBigInteger('sales_order_id');

            $table->foreignId('store_product_id')->constrained('store_products');
            $table->foreignId('supplier_product_id')->constrained('supplier_products');

            $table->integer('quantity');
            $table->decimal('unit_sell_price', 10, 2);
            $table->decimal('unit_buy_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->decimal('line_cost', 10, 2);
            $table->timestamps();

            $table->index(['sales_order_id', 'store_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_items');
    }
};
