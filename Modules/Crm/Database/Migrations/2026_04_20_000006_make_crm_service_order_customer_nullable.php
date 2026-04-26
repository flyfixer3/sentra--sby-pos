<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE crm_service_orders DROP FOREIGN KEY crm_service_orders_customer_id_foreign');
        DB::statement('ALTER TABLE crm_service_orders MODIFY customer_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE crm_service_orders ADD CONSTRAINT crm_service_orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE crm_service_orders DROP FOREIGN KEY crm_service_orders_customer_id_foreign');
        DB::statement('UPDATE crm_service_orders SET customer_id = 0 WHERE customer_id IS NULL');
        DB::statement('ALTER TABLE crm_service_orders MODIFY customer_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE crm_service_orders ADD CONSTRAINT crm_service_orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT');
    }
};
