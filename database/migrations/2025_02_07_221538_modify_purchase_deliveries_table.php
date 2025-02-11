<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPurchaseDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {
            Schema::table('purchase_deliveries', function (Blueprint $table) {
                $table->string('tracking_number')->nullable()->after('date'); // Add tracking number
                $table->string('ship_via')->nullable()->after('tracking_number'); // Add ship_via)
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'ship_via']); // Remove added columns
        });
    }
}
