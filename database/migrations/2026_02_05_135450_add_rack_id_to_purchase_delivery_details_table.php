<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            // nullable karena phase 1: hanya wajib kalau qty_received > 0 (enforced di controller + JS)
            $table->unsignedBigInteger('rack_id')->nullable()->after('product_code');

            // index biar cepat
            $table->index('rack_id');

            // FK (pastikan tabel racks memang ada dan pk-nya id)
            $table->foreign('rack_id')
                ->references('id')
                ->on('racks')
                ->nullOnDelete(); // kalau rack dihapus, data PD tetap aman
        });
    }

    public function down(): void
    {
        Schema::table('purchase_delivery_details', function (Blueprint $table) {
            $table->dropForeign(['rack_id']);
            $table->dropIndex(['rack_id']);
            $table->dropColumn('rack_id');
        });
    }
};
