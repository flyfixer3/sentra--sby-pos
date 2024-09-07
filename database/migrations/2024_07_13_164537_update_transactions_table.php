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
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->constrained()->references('id')
            ->on('purchases')->cascadeOnDelete();
            $table->foreignId('purchase_return_id')->nullable()->constrained()->references('id')
            ->on('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_payment_id')->nullable()->constrained()->references('id')
            ->on('purchase_payments')->cascadeOnDelete();
            $table->foreignId('purchase_return_payment_id')->nullable()->constrained()->references('id')
            ->on('purchase_return_payments')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->references('id')
            ->on('sales')->cascadeOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->references('id')
            ->on('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->constrained()->references('id')
            ->on('sale_payments')->cascadeOnDelete();
            $table->foreignId('sale_return_payment_id')->nullable()->constrained()->references('id')
            ->on('sale_return_payments')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
