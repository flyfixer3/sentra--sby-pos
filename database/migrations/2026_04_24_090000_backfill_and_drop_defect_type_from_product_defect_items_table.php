<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_defect_items', 'defect_type')) {
            DB::table('product_defect_items')
                ->select('id', 'defect_type', 'defect_types')
                ->whereNotNull('defect_type')
                ->where('defect_type', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $existingTypes = json_decode((string) ($row->defect_types ?? '[]'), true);
                        $existingTypes = is_array($existingTypes) ? array_values(array_filter(array_map('strval', $existingTypes))) : [];

                        if (!empty($existingTypes)) {
                            continue;
                        }

                        DB::table('product_defect_items')
                            ->where('id', (int) $row->id)
                            ->update([
                                'defect_types' => json_encode([trim((string) $row->defect_type)]),
                            ]);
                    }
                });

            Schema::table('product_defect_items', function (Blueprint $table) {
                $table->dropColumn('defect_type');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('product_defect_items', 'defect_type')) {
            Schema::table('product_defect_items', function (Blueprint $table) {
                $table->string('defect_type')->nullable()->after('quantity');
            });
        }

        if (Schema::hasColumn('product_defect_items', 'defect_types')) {
            DB::table('product_defect_items')
                ->select('id', 'defect_types')
                ->whereNotNull('defect_types')
                ->where('defect_types', '!=', '')
                ->where('defect_types', '!=', '[]')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $types = json_decode((string) $row->defect_types, true);
                        $types = is_array($types) ? array_values(array_filter(array_map('strval', $types))) : [];

                        DB::table('product_defect_items')
                            ->where('id', (int) $row->id)
                            ->update([
                                'defect_type' => $types[0] ?? null,
                            ]);
                    }
                });
        }
    }
};
