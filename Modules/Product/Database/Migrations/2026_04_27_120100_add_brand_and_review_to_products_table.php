<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('branch_id')->index();
                $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'needs_review')) {
                $table->boolean('needs_review')->default(false)->after('product_stock_alert');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'brand_id')) {
                try {
                    $table->dropForeign(['brand_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('brand_id');
            }

            if (Schema::hasColumn('products', 'needs_review')) {
                $table->dropColumn('needs_review');
            }
        });
    }
};
