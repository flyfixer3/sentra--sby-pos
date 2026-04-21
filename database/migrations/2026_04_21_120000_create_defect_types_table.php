<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('product_defect_items')) {
            $now = now();
            $existing = DB::table('product_defect_items')
                ->select('defect_type')
                ->whereNotNull('defect_type')
                ->where('defect_type', '!=', '')
                ->distinct()
                ->pluck('defect_type');

            foreach ($existing as $label) {
                $name = trim((string) $label);
                if ($name === '') {
                    continue;
                }

                DB::table('defect_types')->insertOrIgnore([
                    'name' => $name,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_types');
    }
};
