<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sale_deliveries')) {
            return;
        }
        if (!Schema::hasColumn('sale_deliveries', 'warehouse_id')) {
            return;
        }

        // Ubah jadi NULLABLE
        DB::statement("ALTER TABLE `sale_deliveries` MODIFY `warehouse_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('sale_deliveries')) {
            return;
        }
        if (!Schema::hasColumn('sale_deliveries', 'warehouse_id')) {
            return;
        }

        // Balikin NOT NULL
        DB::statement("ALTER TABLE `sale_deliveries` MODIFY `warehouse_id` BIGINT UNSIGNED NOT NULL");
    }
};
