<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_deliveries', 'sale_order_id')) {
                $table->unsignedBigInteger('sale_order_id')->nullable()->after('quotation_id');
                $table->index(['sale_order_id']);
            }

            if (!Schema::hasColumn('sale_deliveries', 'sale_id')) {
                $table->unsignedBigInteger('sale_id')->nullable()->after('sale_order_id');
                $table->index(['sale_id']);
            }

            // optional: urutan delivery untuk satu SO/invoice
            if (!Schema::hasColumn('sale_deliveries', 'delivery_no')) {
                $table->integer('delivery_no')->nullable()->after('sale_id');
                $table->index(['delivery_no']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('sale_deliveries', 'delivery_no')) {
                $table->dropIndex(['delivery_no']);
                $table->dropColumn('delivery_no');
            }

            if (Schema::hasColumn('sale_deliveries', 'sale_id')) {
                $table->dropIndex(['sale_id']);
                $table->dropColumn('sale_id');
            }

            if (Schema::hasColumn('sale_deliveries', 'sale_order_id')) {
                $table->dropIndex(['sale_order_id']);
                $table->dropColumn('sale_order_id');
            }
        });
    }
};
