<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_orders', 'has_shortage')) {
                $table->boolean('has_shortage')->default(false)->after('deposit_code');
            }

            if (!Schema::hasColumn('sale_orders', 'shortage_detected_at')) {
                $table->timestamp('shortage_detected_at')->nullable()->after('has_shortage');
            }

            if (!Schema::hasColumn('sale_orders', 'shortage_resolved_at')) {
                $table->timestamp('shortage_resolved_at')->nullable()->after('shortage_detected_at');
            }

            if (!Schema::hasColumn('sale_orders', 'estimated_arrival_days')) {
                $table->unsignedInteger('estimated_arrival_days')->nullable()->after('shortage_resolved_at');
            }

            if (!Schema::hasColumn('sale_orders', 'estimated_arrival_date')) {
                $table->date('estimated_arrival_date')->nullable()->after('estimated_arrival_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $columns = [
                'estimated_arrival_date',
                'estimated_arrival_days',
                'shortage_resolved_at',
                'shortage_detected_at',
                'has_shortage',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sale_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
