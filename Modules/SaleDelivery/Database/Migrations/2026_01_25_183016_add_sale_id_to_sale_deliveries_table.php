<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {

            // sale_id nullable karena sale delivery bisa berasal dari quotation
            $table->unsignedBigInteger('sale_id')->nullable()->after('quotation_id');

            // index biar cepat
            $table->index('sale_id', 'sale_deliveries_sale_id_index');

            // FK optional (kalau kamu biasa pakai FK di project)
            // Kalau kamu takut migrasi fail karena data lama, bisa comment dulu FK nya.
            $table->foreign('sale_id', 'sale_deliveries_sale_id_fk')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {

            // drop FK kalau ada
            if (Schema::hasColumn('sale_deliveries', 'sale_id')) {
                $table->dropForeign('sale_deliveries_sale_id_fk');
                $table->dropIndex('sale_deliveries_sale_id_index');
                $table->dropColumn('sale_id');
            }
        });
    }
};
