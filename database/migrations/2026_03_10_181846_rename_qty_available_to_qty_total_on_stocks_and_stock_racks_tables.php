<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `stocks`
            CHANGE `qty_available` `qty_total` INT(11) NOT NULL DEFAULT 0
        ");

        DB::statement("
            ALTER TABLE `stock_racks`
            CHANGE `qty_available` `qty_total` INT(11) NOT NULL DEFAULT 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE `stocks`
            CHANGE `qty_total` `qty_available` INT(11) NOT NULL DEFAULT 0
        ");

        DB::statement("
            ALTER TABLE `stock_racks`
            CHANGE `qty_total` `qty_available` INT(11) NOT NULL DEFAULT 0
        ");
    }
};