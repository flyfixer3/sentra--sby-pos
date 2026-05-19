<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdjustmentRequestItemsTable extends Migration
{
    public function up()
    {
        Schema::create('adjustment_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adjustment_id');
            $table->unsignedInteger('line_no')->default(1);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('rack_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('condition_from', 30)->nullable();
            $table->string('condition_to', 30)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('adjustment_id')->references('id')->on('adjustments')->onDelete('cascade');
            $table->index(['adjustment_id', 'line_no']);
            $table->index(['product_id', 'warehouse_id', 'rack_id'], 'adj_req_items_product_location_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('adjustment_request_items');
    }
}
