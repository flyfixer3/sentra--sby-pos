<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('customer_id');
                $table->index('product_id');
            }

            if (!Schema::hasColumn('crm_leads', 'product_code')) {
                $table->string('product_code', 100)->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('crm_leads', 'product_name')) {
                $table->string('product_name')->nullable()->after('product_code');
            }

            if (!Schema::hasColumn('crm_leads', 'sale_order_id')) {
                $table->unsignedBigInteger('sale_order_id')->nullable()->after('product_name');
                $table->index('sale_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (Schema::hasColumn('crm_leads', 'sale_order_id')) {
                $table->dropIndex(['sale_order_id']);
                $table->dropColumn('sale_order_id');
            }

            if (Schema::hasColumn('crm_leads', 'product_id')) {
                $table->dropIndex(['product_id']);
            }

            foreach (['product_id', 'product_code', 'product_name'] as $column) {
                if (Schema::hasColumn('crm_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
