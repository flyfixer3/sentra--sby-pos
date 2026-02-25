<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_movement_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('rack_movement_id');
            $table->unsignedBigInteger('product_id');

            // bucket/condition mengikuti stock_racks: good|defect|damaged
            $table->string('condition', 20)->default('good');

            $table->unsignedInteger('quantity');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['rack_movement_id']);
            $table->index(['product_id']);

            $table->foreign('rack_movement_id')->references('id')->on('rack_movements')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_movement_items');
    }
};