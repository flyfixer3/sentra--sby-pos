<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResolutionColumnsToStockOpnameItemsTable extends Migration
{
    public function up()
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_opname_items', 'review_status')) {
                $table->string('review_status', 20)->default('pending')->after('diff_qty');
            }
            if (!Schema::hasColumn('stock_opname_items', 'resolution_type')) {
                $table->string('resolution_type', 50)->nullable()->after('review_status');
            }
            if (!Schema::hasColumn('stock_opname_items', 'resolution_reference')) {
                $table->string('resolution_reference')->nullable()->after('resolution_type');
            }
            if (!Schema::hasColumn('stock_opname_items', 'resolution_note')) {
                $table->text('resolution_note')->nullable()->after('resolution_reference');
            }
            if (!Schema::hasColumn('stock_opname_items', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolution_note');
            }
            if (!Schema::hasColumn('stock_opname_items', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_opname_items', 'resolved_by')) {
                try { $table->dropConstrainedForeignId('resolved_by'); } catch (\Throwable $e) {}
            }
            foreach (['resolved_at', 'resolution_note', 'resolution_reference', 'resolution_type', 'review_status'] as $column) {
                if (Schema::hasColumn('stock_opname_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
