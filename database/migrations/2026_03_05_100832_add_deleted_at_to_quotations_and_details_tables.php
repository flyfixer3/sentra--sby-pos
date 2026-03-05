<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // quotations
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at');
            }
        });

        // quotation_details
        Schema::table('quotation_details', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_details', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
                $table->index('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_details', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });

        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
    }
};