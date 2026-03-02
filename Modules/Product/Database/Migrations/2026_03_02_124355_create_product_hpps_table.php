<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_hpps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_id');

            // HPP moving average saat ini
            $table->decimal('avg_cost', 18, 2)->default(0);

            // optional: last purchase cost (buat audit ringan)
            $table->decimal('last_purchase_cost', 18, 2)->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'product_id'], 'product_hpps_unique_branch_product');

            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_hpps');
    }
};