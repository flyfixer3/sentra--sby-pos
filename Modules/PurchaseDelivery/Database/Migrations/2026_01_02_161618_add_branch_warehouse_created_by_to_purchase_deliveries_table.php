<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {

            if (!Schema::hasColumn('purchase_deliveries', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('purchase_order_id');
                $table->index('branch_id');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('branch_id');
                $table->index('warehouse_id');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('note');
                $table->index('created_by');
            }

            // optional FK (kalau project kamu pakai FK)
            // $table->foreign('branch_id')->references('id')->on('branches');
            // $table->foreign('warehouse_id')->references('id')->on('warehouses');
            // $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {

            // kalau pakai foreign key, drop dulu foreign-nya

            if (Schema::hasColumn('purchase_deliveries', 'created_by')) {
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('purchase_deliveries', 'warehouse_id')) {
                $table->dropIndex(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }

            if (Schema::hasColumn('purchase_deliveries', 'branch_id')) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
