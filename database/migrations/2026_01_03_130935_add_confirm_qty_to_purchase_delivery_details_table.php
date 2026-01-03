<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            $table->integer('qty_received')->default(0)->after('quantity');
            $table->integer('qty_defect')->default(0)->after('qty_received');
            $table->integer('qty_damaged')->default(0)->after('qty_defect');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            $table->dropColumn([
                'qty_received',
                'qty_defect',
                'qty_damaged',
            ]);
        });
    }
};
