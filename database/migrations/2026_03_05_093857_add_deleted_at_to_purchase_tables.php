<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // 2) purchase_order_details
        Schema::table('purchase_order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_details', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // 3) purchase_details
        Schema::table('purchase_details', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_details', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // 4) purchase_delivery_details
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_delivery_details', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        // 1) purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // 2) purchase_order_details
        Schema::table('purchase_order_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_details', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // 3) purchase_details
        Schema::table('purchase_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_details', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // 4) purchase_delivery_details
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_delivery_details', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};