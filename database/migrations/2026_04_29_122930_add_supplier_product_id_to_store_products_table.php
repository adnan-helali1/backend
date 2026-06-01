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
        Schema::table('store_products', function (Blueprint $table) {
            $table->foreignId('supplier_product_id')
                ->nullable()
                ->after('product_id')
                ->constrained('supplier_products')
                ->cascadeOnDelete();

            $table->index(['store_id', 'supplier_product_id']);
            $table->unique(['store_id', 'supplier_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'supplier_product_id']);
            $table->dropIndex(['store_id', 'supplier_product_id']);
            $table->dropConstrainedForeignId('supplier_product_id');
        });
    }
};
