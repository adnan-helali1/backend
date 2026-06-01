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
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('supplier_product_id')
                ->nullable()
                ->after('product_id')
                ->constrained('supplier_products');

            $table->index(['order_id', 'supplier_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'supplier_product_id']);
            $table->dropConstrainedForeignId('supplier_product_id');
        });
    }
};
