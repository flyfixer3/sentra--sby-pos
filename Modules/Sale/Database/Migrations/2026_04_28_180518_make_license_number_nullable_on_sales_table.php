<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales', 'license_number')) {
            return;
        }

        DB::statement('ALTER TABLE `sales` MODIFY `license_number` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sales', 'license_number')) {
            return;
        }

        DB::table('sales')
            ->whereNull('license_number')
            ->update(['license_number' => '']);

        DB::statement("ALTER TABLE `sales` MODIFY `license_number` VARCHAR(255) NOT NULL");
    }
};
