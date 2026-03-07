<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_order_items', 'unit_price')) {
                $table->integer('unit_price')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('sale_order_items', 'product_discount_amount')) {
                $table->integer('product_discount_amount')->default(0)->after('price');
            }

            if (!Schema::hasColumn('sale_order_items', 'product_discount_type')) {
                $table->string('product_discount_type')->default('fixed')->after('product_discount_amount');
            }

            if (!Schema::hasColumn('sale_order_items', 'sub_total')) {
                $table->integer('sub_total')->default(0)->after('product_discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['unit_price', 'product_discount_amount', 'product_discount_type', 'sub_total'] as $column) {
                if (Schema::hasColumn('sale_order_items', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
