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
        Schema::create('store_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('type', ['debit', 'credit']);
            $table->enum('source_type', ['order', 'payment', 'adjustment']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
            $table->index(['store_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_ledger_entries');
    }
};
