<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('crm_lead_products')) {
            return;
        }

        Schema::create('crm_lead_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_code', 100)->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('total_price')->default(0);
            $table->integer('available_qty')->default(0);
            $table->integer('incoming_qty')->default(0);
            $table->string('stock_status', 50)->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_products');
    }
};
