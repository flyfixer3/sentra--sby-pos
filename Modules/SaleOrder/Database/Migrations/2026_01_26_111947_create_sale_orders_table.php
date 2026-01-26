<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('customer_id');

            // Optional linkages
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable(); // invoice anchor (sales table)

            // Default warehouse for stock-out (can still be overridden on delivery if you want)
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->string('reference')->unique(); // e.g., SO-00001
            $table->date('date');

            // pending / partial_delivered / delivered / cancelled
            $table->string('status')->default('pending');

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'date']);
            $table->index(['customer_id']);
            $table->index(['quotation_id']);
            $table->index(['sale_id']);
            $table->index(['warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};
