<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            if (!Schema::hasColumn('adjustments', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('branch_id')->index();
            }
        });

        // Backfill adjustment lama -> set ke main warehouse berdasarkan branch_id
        if (Schema::hasTable('warehouses')) {
            $adjustments = DB::table('adjustments')
                ->whereNull('warehouse_id')
                ->whereNotNull('branch_id')
                ->select('id', 'branch_id')
                ->get();

            foreach ($adjustments as $a) {
                $mainWarehouseId = DB::table('warehouses')
                    ->where('branch_id', $a->branch_id)
                    ->where('is_main', 1)
                    ->value('id');

                if ($mainWarehouseId) {
                    DB::table('adjustments')
                        ->where('id', $a->id)
                        ->update(['warehouse_id' => $mainWarehouseId]);
                }
            }
        }

        Schema::table('adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('adjustments', 'warehouse_id')) {
                $table->foreign('warehouse_id')
                    ->references('id')
                    ->on('warehouses')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('adjustments', 'warehouse_id')) {
                try {
                    $table->dropForeign(['warehouse_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('warehouse_id');
            }
        });
    }
};
