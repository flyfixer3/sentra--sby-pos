<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rack_movement_items', function (Blueprint $table) {
            if (!Schema::hasColumn('rack_movement_items', 'defect_item_ids')) {
                $table->json('defect_item_ids')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('rack_movement_items', 'damaged_item_ids')) {
                $table->json('damaged_item_ids')->nullable()->after('defect_item_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rack_movement_items', function (Blueprint $table) {
            if (Schema::hasColumn('rack_movement_items', 'damaged_item_ids')) {
                $table->dropColumn('damaged_item_ids');
            }

            if (Schema::hasColumn('rack_movement_items', 'defect_item_ids')) {
                $table->dropColumn('defect_item_ids');
            }
        });
    }
};
