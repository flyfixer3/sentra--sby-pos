<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_delivery_items', 'sale_order_item_id')) {
                $table->unsignedBigInteger('sale_order_item_id')->nullable()->after('product_id');
                $table->index('sale_order_item_id', 'sdi_sale_order_item_id_idx');
            }

            if (!Schema::hasColumn('sale_delivery_items', 'sale_item_id')) {
                $table->unsignedBigInteger('sale_item_id')->nullable()->after('sale_order_item_id');
                $table->index('sale_item_id', 'sdi_sale_item_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_delivery_items', 'sale_item_id')) {
                $table->dropIndex('sdi_sale_item_id_idx');
                $table->dropColumn('sale_item_id');
            }

            if (Schema::hasColumn('sale_delivery_items', 'sale_order_item_id')) {
                $table->dropIndex('sdi_sale_order_item_id_idx');
                $table->dropColumn('sale_order_item_id');
            }
        });
    }
};
