<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockOpnameItemsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained('racks')->nullOnDelete();
            $table->string('product_code_snapshot');
            $table->string('product_name_snapshot');
            $table->string('rack_code_snapshot')->nullable();
            $table->string('rack_name_snapshot')->nullable();
            $table->integer('system_qty')->default(0);
            $table->integer('physical_qty')->nullable();
            $table->integer('diff_qty')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id'], 'stock_opname_items_unique_opname_product');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opname_items');
    }
}
