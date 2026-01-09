<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            $table->timestamp('moved_out_at')->nullable()->after('created_at');
            $table->unsignedBigInteger('moved_out_by')->nullable()->after('moved_out_at');
            $table->string('moved_out_reference_type')->nullable()->after('moved_out_by');
            $table->unsignedBigInteger('moved_out_reference_id')->nullable()->after('moved_out_reference_type');

            $table->index(['product_id', 'branch_id', 'warehouse_id', 'moved_out_at'], 'defect_items_moved_out_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            $table->dropIndex('defect_items_moved_out_idx');
            $table->dropColumn([
                'moved_out_at', 'moved_out_by',
                'moved_out_reference_type', 'moved_out_reference_id',
            ]);
        });
    }
};
