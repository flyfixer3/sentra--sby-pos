<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_categories', 'subaccount_number')) {
                $table->string('subaccount_number', 255)->nullable()->after('category_description');
                $table->index('subaccount_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            if (Schema::hasColumn('expense_categories', 'subaccount_number')) {
                $table->dropIndex(['subaccount_number']);
                $table->dropColumn('subaccount_number');
            }
        });
    }
};