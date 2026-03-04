<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToPurchaseDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {
            $table->softDeletes(); // adds deleted_at
        });
    }

    public function down()
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
