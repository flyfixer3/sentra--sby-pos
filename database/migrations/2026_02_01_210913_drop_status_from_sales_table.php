<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropStatusFromSalesTable extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            // balikin lagi kalau rollback
            if (!Schema::hasColumn('sales', 'status')) {
                $table->string('status')->nullable()->after('payment_status');
            }
        });
    }
}
