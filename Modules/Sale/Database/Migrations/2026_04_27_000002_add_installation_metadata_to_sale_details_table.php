<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInstallationMetadataToSaleDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'installation_type')) {
                $table->string('installation_type')->default('item_only')->after('warehouse_id');
            }

            if (!Schema::hasColumn('sale_details', 'customer_vehicle_id')) {
                $table->unsignedBigInteger('customer_vehicle_id')->nullable()->after('installation_type')->index();
                $table->foreign('customer_vehicle_id')
                    ->references('id')
                    ->on('customer_vehicles')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'customer_vehicle_id')) {
                $table->dropForeign(['customer_vehicle_id']);
                $table->dropColumn('customer_vehicle_id');
            }

            if (Schema::hasColumn('sale_details', 'installation_type')) {
                $table->dropColumn('installation_type');
            }
        });
    }
}
