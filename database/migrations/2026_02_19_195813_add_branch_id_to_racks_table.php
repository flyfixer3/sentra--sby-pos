<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            if (!Schema::hasColumn('racks', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('warehouse_id');
                $table->index(['branch_id']);
            }
        });

        // backfill: branch_id mengikuti warehouse.branch_id
        DB::statement("
            UPDATE racks r
            INNER JOIN warehouses w ON w.id = r.warehouse_id
            SET r.branch_id = w.branch_id
            WHERE r.branch_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            if (Schema::hasColumn('racks', 'branch_id')) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
