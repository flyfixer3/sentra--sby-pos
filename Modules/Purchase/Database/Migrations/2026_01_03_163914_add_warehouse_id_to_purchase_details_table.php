<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_details', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('product_id');
            }
        });

        // FK dibuat terpisah supaya aman (kadang tabel warehouses pakai engine/constraint tertentu)
        Schema::table('purchase_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_details', 'warehouse_id')) {
                // kalau sudah ada FK, skip
                // kita coba add FK dengan nama default
                try {
                    $table->foreign('warehouse_id')
                        ->references('id')
                        ->on('warehouses')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // kalau error karena constraint sudah ada / beda, biarkan
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_details', 'warehouse_id')) {
                try {
                    $table->dropForeign(['warehouse_id']);
                } catch (\Throwable $e) {
                    // kalau nama constraint beda, biarkan
                }
                $table->dropColumn('warehouse_id');
            }
        });
    }
};
