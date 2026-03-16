<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('note');
                $table->index('created_by');
            }

            if (!Schema::hasColumn('quotations', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->index('updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'updated_by')) {
                $table->dropIndex(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('quotations', 'created_by')) {
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
