<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductStockAlertToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'product_stock_alert')) {
                // Default -1 = alert dimatikan
                $table->integer('product_stock_alert')->default(-1)->after('product_unit');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_stock_alert')) {
                $table->dropColumn('product_stock_alert');
            }
        });
    }
}