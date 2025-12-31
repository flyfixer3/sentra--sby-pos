<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transfer_requests
            MODIFY COLUMN status ENUM(
                'pending',
                'shipped',
                'confirmed',
                'cancelled',
                'completed',
                'rejected'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE transfer_requests
            MODIFY COLUMN status ENUM(
                'pending',
                'shipped',
                'confirmed',
                'completed',
                'rejected'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
