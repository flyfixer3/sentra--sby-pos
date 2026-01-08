<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_damaged_items')) {
            return;
        }

        // MySQL: ubah jadi nullable (tanpa doctrine/dbal)
        DB::statement("ALTER TABLE `product_damaged_items` MODIFY `mutation_in_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `product_damaged_items` MODIFY `mutation_out_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_damaged_items')) {
            return;
        }

        // Kalau rollback, pastikan tidak ada NULL dulu biar aman
        DB::statement("UPDATE `product_damaged_items` SET `mutation_in_id` = 0 WHERE `mutation_in_id` IS NULL");

        // Kembalikan: mutation_in_id NOT NULL (kita kasih DEFAULT 0 biar rollback gak meledak)
        DB::statement("ALTER TABLE `product_damaged_items` MODIFY `mutation_in_id` BIGINT UNSIGNED NOT NULL DEFAULT 0");

        // mutation_out_id biarkan nullable (lebih aman & sesuai kebutuhan sekarang)
        DB::statement("ALTER TABLE `product_damaged_items` MODIFY `mutation_out_id` BIGINT UNSIGNED NULL");
    }
};
