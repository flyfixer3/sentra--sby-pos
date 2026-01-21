<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_delivery_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_delivery_id');
            $table->unsignedBigInteger('product_id');

            $table->integer('quantity');
            $table->integer('price')->nullable(); // optional (boleh null)

            $table->timestamps();

            $table->index(['sale_delivery_id']);
            $table->index(['product_id']);

            // foreign key optional (kalau project kamu biasa nggak pakai FK, boleh skip)
            // $table->foreign('sale_delivery_id')->references('id')->on('sale_deliveries')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_delivery_items');
    }
};
