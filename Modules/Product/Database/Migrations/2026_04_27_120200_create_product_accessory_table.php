<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_accessory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('accessory_id');
            $table->timestamps();

            $table->unique(['product_id', 'accessory_id'], 'product_accessory_unique');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('accessory_id')->references('id')->on('accessories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_accessory');
    }
};
