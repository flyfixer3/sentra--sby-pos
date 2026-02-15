<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            // Konsisten dengan kolom lain yang pakai int(11)
            $table->integer('deposit_received_amount')->default(0)->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn('deposit_received_amount');
        });
    }
};
