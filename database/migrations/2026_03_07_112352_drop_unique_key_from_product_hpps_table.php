<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_hpps')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `product_hpps` DROP INDEX `product_hpps_unique_branch_product`');
        } catch (\Throwable $e) {
            // index mungkin sudah tidak ada, abaikan
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_hpps')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `product_hpps` ADD UNIQUE KEY `product_hpps_unique_branch_product` (`branch_id`, `product_id`)');
        } catch (\Throwable $e) {
            // kalau gagal rollback, abaikan
        }
    }
};