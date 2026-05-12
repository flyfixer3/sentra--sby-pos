<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('product_price_item_only')->nullable()->after('product_price');
            $table->integer('installation_service_price')->nullable()->after('product_price_item_only');
            $table->integer('product_price_package')->nullable()->after('installation_service_price');
        });

        DB::table('products')->update([
            'product_price_item_only' => DB::raw('product_price'),
            'product_price_package' => DB::raw('product_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_price_item_only',
                'installation_service_price',
                'product_price_package',
            ]);
        });
    }
};
