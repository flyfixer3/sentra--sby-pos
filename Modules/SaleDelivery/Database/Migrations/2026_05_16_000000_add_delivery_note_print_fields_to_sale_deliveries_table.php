<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_deliveries', 'delivery_code')) {
                $table->string('delivery_code', 6)->nullable()->unique()->after('note');
            }

            if (!Schema::hasColumn('sale_deliveries', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('delivery_code');
            }

            if (!Schema::hasColumn('sale_deliveries', 'printed_by')) {
                $table->unsignedBigInteger('printed_by')->nullable()->after('printed_at');
                $table->foreign('printed_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('sale_deliveries', 'printed_by')) {
                $table->dropForeign(['printed_by']);
                $table->dropColumn('printed_by');
            }

            if (Schema::hasColumn('sale_deliveries', 'printed_at')) {
                $table->dropColumn('printed_at');
            }

            if (Schema::hasColumn('sale_deliveries', 'delivery_code')) {
                $table->dropColumn('delivery_code');
            }
        });
    }
};
