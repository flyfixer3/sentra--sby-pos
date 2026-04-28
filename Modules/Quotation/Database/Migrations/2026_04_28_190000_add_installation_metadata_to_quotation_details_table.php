<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInstallationMetadataToQuotationDetailsTable extends Migration
{
    public function up()
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_details', 'installation_type')) {
                $table->string('installation_type')->default('item_only')->after('product_tax_amount');
            }

            if (!Schema::hasColumn('quotation_details', 'customer_vehicle_id')) {
                $table->unsignedBigInteger('customer_vehicle_id')->nullable()->after('installation_type');
                $table->index('customer_vehicle_id');
                $table->foreign('customer_vehicle_id')
                    ->references('id')
                    ->on('customer_vehicles')
                    ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_details', 'customer_vehicle_id')) {
                $table->dropForeign(['customer_vehicle_id']);
                $table->dropIndex(['customer_vehicle_id']);
                $table->dropColumn('customer_vehicle_id');
            }

            if (Schema::hasColumn('quotation_details', 'installation_type')) {
                $table->dropColumn('installation_type');
            }
        });
    }
}
