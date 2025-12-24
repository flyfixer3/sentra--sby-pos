<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterStatusEnumOnTransferRequests extends Migration
{
    public function up()
    {
        // Allow statuses used by application logic, including shipped/confirmed flow
        DB::statement("ALTER TABLE transfer_requests MODIFY COLUMN status ENUM('pending','shipped','confirmed','completed','rejected') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        // Revert to the original (older) set if needed
        DB::statement("ALTER TABLE transfer_requests MODIFY COLUMN status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending'");
    }
}

