<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_order_items', 'installation_type')) {
                $table->string('installation_type')->default('item_only')->after('sub_total');
            }

            if (!Schema::hasColumn('sale_order_items', 'customer_vehicle_id')) {
                $table->unsignedBigInteger('customer_vehicle_id')->nullable()->after('installation_type')->index();
                $table->foreign('customer_vehicle_id')
                    ->references('id')
                    ->on('customer_vehicles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_order_items', 'customer_vehicle_id')) {
                $table->dropForeign(['customer_vehicle_id']);
                $table->dropColumn('customer_vehicle_id');
            }

            if (Schema::hasColumn('sale_order_items', 'installation_type')) {
                $table->dropColumn('installation_type');
            }
        });
    }
};
