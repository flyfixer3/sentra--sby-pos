<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('sale_order_id');
            $table->unsignedBigInteger('product_id');

            // Ordered qty (anchor)
            $table->integer('quantity')->default(0);

            // Optional pricing snapshot (kalau kamu perlu)
            $table->integer('price')->nullable();

            $table->timestamps();

            $table->index(['sale_order_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_order_items');
    }
};
