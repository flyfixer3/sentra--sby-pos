<?php

use App\Models\Entity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('id')->constrained('entities')->nullOnDelete();
        });

        $defaultEntityId = DB::table('entities')->insertGetId([
            'name' => 'Default Entity',
            'code' => 'DEFAULT',
            'description' => 'Auto-generated default entity for existing branches.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')
            ->whereNull('entity_id')
            ->update(['entity_id' => $defaultEntityId]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
        });

        DB::table('entities')
            ->where('code', 'DEFAULT')
            ->delete();
    }
};
