<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            if (!Schema::hasColumn('product_defect_items', 'rack_id')) {
                $table->unsignedBigInteger('rack_id')->nullable()->after('warehouse_id');
                $table->index(['branch_id', 'warehouse_id', 'product_id', 'rack_id'], 'pdi_branch_wh_product_rack_idx');
            }
        });

        Schema::table('product_damaged_items', function (Blueprint $table) {
            if (!Schema::hasColumn('product_damaged_items', 'rack_id')) {
                $table->unsignedBigInteger('rack_id')->nullable()->after('warehouse_id');
                $table->index(['branch_id', 'warehouse_id', 'product_id', 'rack_id'], 'pdam_branch_wh_product_rack_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            if (Schema::hasColumn('product_defect_items', 'rack_id')) {
                $table->dropIndex('pdi_branch_wh_product_rack_idx');
                $table->dropColumn('rack_id');
            }
        });

        Schema::table('product_damaged_items', function (Blueprint $table) {
            if (Schema::hasColumn('product_damaged_items', 'rack_id')) {
                $table->dropIndex('pdam_branch_wh_product_rack_idx');
                $table->dropColumn('rack_id');
            }
        });
    }
};
