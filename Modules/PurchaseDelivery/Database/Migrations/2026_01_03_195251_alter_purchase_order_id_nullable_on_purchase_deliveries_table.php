<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DROP FK kalau ada (biar aman)
        DB::statement("
            ALTER TABLE purchase_deliveries
            DROP FOREIGN KEY purchase_deliveries_purchase_order_id_foreign
        ");

        // ubah jadi nullable
        DB::statement("
            ALTER TABLE purchase_deliveries
            MODIFY purchase_order_id BIGINT(20) UNSIGNED NULL
        ");

        // tambahkan FK lagi dengan SET NULL (optional tapi recommended)
        DB::statement("
            ALTER TABLE purchase_deliveries
            ADD CONSTRAINT purchase_deliveries_purchase_order_id_foreign
            FOREIGN KEY (purchase_order_id)
            REFERENCES purchase_orders(id)
            ON DELETE SET NULL
        ");
    }

    public function down(): void
    {
        // rollback FK
        DB::statement("
            ALTER TABLE purchase_deliveries
            DROP FOREIGN KEY purchase_deliveries_purchase_order_id_foreign
        ");

        // balikin jadi NOT NULL
        DB::statement("
            ALTER TABLE purchase_deliveries
            MODIFY purchase_order_id BIGINT(20) UNSIGNED NOT NULL
        ");

        // balikin FK tanpa SET NULL
        DB::statement("
            ALTER TABLE purchase_deliveries
            ADD CONSTRAINT purchase_deliveries_purchase_order_id_foreign
            FOREIGN KEY (purchase_order_id)
            REFERENCES purchase_orders(id)
        ");
    }
};
