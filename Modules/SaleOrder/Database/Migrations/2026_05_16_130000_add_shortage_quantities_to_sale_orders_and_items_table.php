<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_orders', 'shortage_quantity')) {
                $table->unsignedInteger('shortage_quantity')->nullable()->after('has_shortage');
            }
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_order_items', 'sellable_stock_at_order')) {
                $table->integer('sellable_stock_at_order')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('sale_order_items', 'shortage_quantity')) {
                $table->unsignedInteger('shortage_quantity')->nullable()->after('sellable_stock_at_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            foreach (['shortage_quantity', 'sellable_stock_at_order'] as $column) {
                if (Schema::hasColumn('sale_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sale_orders', 'shortage_quantity')) {
                $table->dropColumn('shortage_quantity');
            }
        });
    }
};
