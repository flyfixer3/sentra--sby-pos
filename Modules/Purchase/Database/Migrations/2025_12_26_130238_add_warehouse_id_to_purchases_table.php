<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1. Tambah kolom warehouse_id
         */
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')
                    ->nullable()
                    ->after('branch_id')
                    ->index();
            }
        });

        /**
         * 2. BACKFILL DATA LAMA
         *    purchase lama yang belum punya warehouse_id
         *    otomatis isi main warehouse dari branch_id
         */
        $purchases = DB::table('purchases')
            ->whereNull('warehouse_id')
            ->whereNotNull('branch_id')
            ->select('id', 'branch_id')
            ->get();

        foreach ($purchases as $purchase) {
            $mainWarehouseId = DB::table('warehouses')
                ->where('branch_id', $purchase->branch_id)
                ->where('is_main', 1)
                ->value('id');

            if ($mainWarehouseId) {
                DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->update([
                        'warehouse_id' => $mainWarehouseId
                    ]);
            }
        }

        /**
         * 3. Foreign Key (optional tapi direkomendasikan)
         */
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'warehouse_id')) {
                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->onDelete('set null');
            }
        });

        /**
         * 4. (OPSIONAL) Kalau kamu sudah yakin semua data aman,
         *    kamu bisa paksa NOT NULL (butuh doctrine/dbal)
         */
        // Schema::table('purchases', function (Blueprint $table) {
        //     $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
        // });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'warehouse_id')) {

                try {
                    $table->dropForeign(['warehouse_id']);
                } catch (\Throwable $e) {
                    // ignore kalau FK tidak ada
                }

                $table->dropColumn('warehouse_id');
            }
        });
    }
};
