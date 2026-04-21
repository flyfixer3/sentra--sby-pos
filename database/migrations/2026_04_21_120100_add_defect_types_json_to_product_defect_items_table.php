<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            if (!Schema::hasColumn('product_defect_items', 'defect_types')) {
                $table->json('defect_types')->nullable()->after('defect_type');
            }
        });

        if (Schema::hasColumn('product_defect_items', 'defect_type')) {
            DB::table('product_defect_items')
                ->whereNotNull('defect_type')
                ->where('defect_type', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('product_defect_items')
                            ->where('id', (int) $row->id)
                            ->update([
                                'defect_types' => json_encode([trim((string) $row->defect_type)]),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('product_defect_items', function (Blueprint $table) {
            if (Schema::hasColumn('product_defect_items', 'defect_types')) {
                $table->dropColumn('defect_types');
            }
        });
    }
};
