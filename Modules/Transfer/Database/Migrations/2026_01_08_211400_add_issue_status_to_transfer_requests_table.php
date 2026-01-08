<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE transfer_requests
            MODIFY status ENUM(
                'pending',
                'shipped',
                'confirmed',
                'issue',
                'cancelled',
                'completed',
                'rejected'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // ⚠️ Pastikan TIDAK ADA row status = 'issue' sebelum rollback
        DB::statement("
            ALTER TABLE transfer_requests
            MODIFY status ENUM(
                'pending',
                'shipped',
                'confirmed',
                'cancelled',
                'completed',
                'rejected'
            ) NOT NULL
        ");
    }
};
