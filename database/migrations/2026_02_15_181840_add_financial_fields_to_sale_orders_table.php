<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            // Financial fields (mirip Sales)
            $table->integer('tax_percentage')->default(0)->after('date');
            $table->integer('tax_amount')->default(0)->after('tax_percentage');

            $table->integer('discount_percentage')->default(0)->after('tax_amount');
            $table->integer('discount_amount')->default(0)->after('discount_percentage');

            $table->integer('shipping_amount')->default(0)->after('discount_amount');
            $table->integer('fee_amount')->default(0)->after('shipping_amount'); // platform fee

            $table->integer('subtotal_amount')->default(0)->after('fee_amount');
            $table->integer('total_amount')->default(0)->after('subtotal_amount');

            // Deposit / DP at Sale Order
            $table->integer('deposit_percentage')->default(0)->after('total_amount');
            $table->integer('deposit_amount')->default(0)->after('deposit_percentage');

            $table->string('deposit_payment_method')->nullable()->after('deposit_amount');
            $table->string('deposit_code')->nullable()->after('deposit_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn([
                'tax_percentage',
                'tax_amount',
                'discount_percentage',
                'discount_amount',
                'shipping_amount',
                'fee_amount',
                'subtotal_amount',
                'total_amount',
                'deposit_percentage',
                'deposit_amount',
                'deposit_payment_method',
                'deposit_code',
            ]);
        });
    }
};
