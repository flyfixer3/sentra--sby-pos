<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('purchase_orders', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('purchase_orders', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
