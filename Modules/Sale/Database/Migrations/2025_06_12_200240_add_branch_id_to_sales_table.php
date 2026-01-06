<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBranchIdToSalesTable extends Migration
{
    public function up()
    {
        // sales
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id')->index();
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            }
        });

        // sale_details (penting kalau kamu filter list detail per cabang / report)
        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id')->index();
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            }
        });

        // sale_payments (kalau kamu mau laporan kas per cabang)
        Schema::table('sale_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_payments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id')->index();
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        // sale_payments
        Schema::table('sale_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sale_payments', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });

        // sale_details
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });

        // sales
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
}
