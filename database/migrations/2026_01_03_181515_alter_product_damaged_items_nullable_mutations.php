<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: ubah mutation_out_id jadi nullable
        DB::statement("ALTER TABLE product_damaged_items MODIFY mutation_out_id BIGINT(20) UNSIGNED NULL");
    }

    public function down(): void
    {
        // balik lagi NOT NULL (HATI-HATI: kalau sudah ada NULL, ini bakal gagal)
        DB::statement("ALTER TABLE product_damaged_items MODIFY mutation_out_id BIGINT(20) UNSIGNED NOT NULL");
    }
};
