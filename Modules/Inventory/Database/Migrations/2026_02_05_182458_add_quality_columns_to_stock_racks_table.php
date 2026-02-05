<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_racks', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_racks', 'qty_good')) {
                $table->integer('qty_good')->default(0)->after('qty_available');
            }
            if (!Schema::hasColumn('stock_racks', 'qty_defect')) {
                $table->integer('qty_defect')->default(0)->after('qty_good');
            }
            if (!Schema::hasColumn('stock_racks', 'qty_damaged')) {
                $table->integer('qty_damaged')->default(0)->after('qty_defect');
            }

            // prevent duplicate per rack per product per warehouse per branch
            // (kalau ternyata di db kamu sudah ada duplicate, nanti migrate akan gagal -> beresin dulu)
            $table->unique(
                ['branch_id', 'warehouse_id', 'rack_id', 'product_id'],
                'stock_racks_unique_branch_wh_rack_product'
            );
        });

        // Data lama: anggap semuanya good
        DB::statement("UPDATE stock_racks SET qty_good = qty_available WHERE qty_good = 0 OR qty_good IS NULL");
    }

    public function down(): void
    {
        Schema::table('stock_racks', function (Blueprint $table) {
            // drop unique
            try { $table->dropUnique('stock_racks_unique_branch_wh_rack_product'); } catch (\Throwable $e) {}

            if (Schema::hasColumn('stock_racks', 'qty_damaged')) $table->dropColumn('qty_damaged');
            if (Schema::hasColumn('stock_racks', 'qty_defect')) $table->dropColumn('qty_defect');
            if (Schema::hasColumn('stock_racks', 'qty_good')) $table->dropColumn('qty_good');
        });
    }
};
