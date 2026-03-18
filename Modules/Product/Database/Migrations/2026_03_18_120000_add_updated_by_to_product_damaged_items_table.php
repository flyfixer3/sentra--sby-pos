<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_damaged_items') || Schema::hasColumn('product_damaged_items', 'updated_by')) {
            return;
        }

        Schema::table('product_damaged_items', function (Blueprint $table) {
            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_damaged_items') || !Schema::hasColumn('product_damaged_items', 'updated_by')) {
            return;
        }

        Schema::table('product_damaged_items', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });
    }
};
