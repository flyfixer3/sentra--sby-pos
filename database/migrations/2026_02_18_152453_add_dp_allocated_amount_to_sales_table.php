<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'dp_allocated_amount')) {
                $table->unsignedBigInteger('dp_allocated_amount')
                    ->default(0)
                    ->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'dp_allocated_amount')) {
                $table->dropColumn('dp_allocated_amount');
            }
        });
    }
};
