<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_receive_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('transfer_request_id');
            $table->unsignedBigInteger('transfer_request_item_id');

            $table->unsignedBigInteger('branch_id');        // receiver branch
            $table->unsignedBigInteger('warehouse_id');     // receiver warehouse
            $table->unsignedBigInteger('product_id');

            $table->unsignedBigInteger('rack_id')->nullable();

            $table->integer('qty_good')->default(0);
            $table->integer('qty_defect')->default(0);
            $table->integer('qty_damaged')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['transfer_request_id', 'transfer_request_item_id'], 'tra_trf_item_idx');
            $table->index(['branch_id', 'warehouse_id', 'product_id', 'rack_id'], 'tra_branch_wh_product_rack_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_receive_allocations');
    }
};
