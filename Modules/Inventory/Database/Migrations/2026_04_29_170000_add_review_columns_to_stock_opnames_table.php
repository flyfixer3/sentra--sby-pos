<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opnames', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('imported_at');
            }

            if (!Schema::hasColumn('stock_opnames', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            if (Schema::hasColumn('stock_opnames', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn('reviewed_by');
            }

            if (Schema::hasColumn('stock_opnames', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
        });
    }
};
