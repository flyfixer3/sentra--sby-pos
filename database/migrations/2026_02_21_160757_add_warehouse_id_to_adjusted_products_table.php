<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) add column nullable
        DB::statement("
            ALTER TABLE adjusted_products
            ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER product_id
        ");

        // 2) index untuk query cepat
        DB::statement("
            CREATE INDEX adjusted_products_warehouse_id_index
            ON adjusted_products (warehouse_id)
        ");
    }

    public function down(): void
    {
        // drop index dulu baru drop column
        DB::statement("DROP INDEX adjusted_products_warehouse_id_index ON adjusted_products");
        DB::statement("ALTER TABLE adjusted_products DROP COLUMN warehouse_id");
    }
};
