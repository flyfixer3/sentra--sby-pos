<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBranchAndRackToSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('sales', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('warehouse_id');
            }
            if (!Schema::hasColumn('sales', 'rack_id')) {
                $table->unsignedBigInteger('rack_id')->nullable()->after('branch_id');
            }

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('rack_id')->references('id')->on('racks')->onDelete('set null');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
}
