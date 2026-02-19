<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            if (!Schema::hasColumn('adjusted_products', 'rack_id')) {
                $table->unsignedBigInteger('rack_id')->nullable()->after('product_id');
                $table->index(['adjustment_id', 'product_id', 'rack_id'], 'adjprod_adj_product_rack_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            if (Schema::hasColumn('adjusted_products', 'rack_id')) {
                $table->dropIndex('adjprod_adj_product_rack_idx');
                $table->dropColumn('rack_id');
            }
        });
    }
};
