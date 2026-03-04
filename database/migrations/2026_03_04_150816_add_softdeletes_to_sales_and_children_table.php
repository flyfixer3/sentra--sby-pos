<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at');
            }
        });

        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};