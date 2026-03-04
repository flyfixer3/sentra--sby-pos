<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sale_deliveries
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_deliveries', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // sale_delivery_items
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_delivery_items', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('sale_deliveries', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_delivery_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};