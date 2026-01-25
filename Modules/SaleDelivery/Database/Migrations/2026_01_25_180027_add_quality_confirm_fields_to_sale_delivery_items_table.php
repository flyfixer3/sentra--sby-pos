<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_delivery_items', 'qty_good')) {
                $table->integer('qty_good')->default(0)->after('quantity');
            }

            if (!Schema::hasColumn('sale_delivery_items', 'qty_defect')) {
                $table->integer('qty_defect')->default(0)->after('qty_good');
            }

            if (!Schema::hasColumn('sale_delivery_items', 'qty_damaged')) {
                $table->integer('qty_damaged')->default(0)->after('qty_defect');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_delivery_items', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('sale_delivery_items', 'qty_good')) $drop[] = 'qty_good';
            if (Schema::hasColumn('sale_delivery_items', 'qty_defect')) $drop[] = 'qty_defect';
            if (Schema::hasColumn('sale_delivery_items', 'qty_damaged')) $drop[] = 'qty_damaged';
            if (!empty($drop)) $table->dropColumn($drop);
        });
    }
};
