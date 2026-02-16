<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sales MODIFY discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sales MODIFY discount_percentage INT(11) NOT NULL DEFAULT 0");
    }
};
