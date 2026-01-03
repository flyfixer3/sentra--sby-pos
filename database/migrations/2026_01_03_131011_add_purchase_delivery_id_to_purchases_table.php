<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('purchase_delivery_id')
                ->nullable()
                ->unique()
                ->after('purchase_order_id')
                ->constrained('purchase_deliveries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_delivery_id');
        });
    }
};
