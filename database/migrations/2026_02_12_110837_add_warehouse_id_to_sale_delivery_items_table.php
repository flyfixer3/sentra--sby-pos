<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_delivery_items', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('sale_delivery_id');
                $table->index(['warehouse_id'], 'sdi_warehouse_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_delivery_items', 'warehouse_id')) {
                $table->dropIndex('sdi_warehouse_id_idx');
                $table->dropColumn('warehouse_id');
            }
        });
    }
};
