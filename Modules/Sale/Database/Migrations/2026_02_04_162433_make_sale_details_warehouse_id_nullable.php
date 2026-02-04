<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pastikan table ada dulu
        if (!Schema::hasTable('sale_details')) {
            return;
        }

        // kalau kolomnya ga ada, skip
        if (!Schema::hasColumn('sale_details', 'warehouse_id')) {
            return;
        }

        // Ubah jadi nullable TANPA doctrine/dbal
        // MySQL syntax: MODIFY kolom + tipe lengkap
        DB::statement("ALTER TABLE `sale_details` MODIFY `warehouse_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('sale_details')) {
            return;
        }

        if (!Schema::hasColumn('sale_details', 'warehouse_id')) {
            return;
        }

        // Balikin jadi NOT NULL
        DB::statement("ALTER TABLE `sale_details` MODIFY `warehouse_id` BIGINT UNSIGNED NOT NULL");
    }
};
