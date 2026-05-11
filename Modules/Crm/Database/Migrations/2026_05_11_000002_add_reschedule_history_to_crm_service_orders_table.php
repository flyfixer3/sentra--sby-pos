<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            // JSON array of reschedule records: [{from, to, reason, user_id, rescheduled_at}]
            $table->json('reschedule_history')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            $table->dropColumn('reschedule_history');
        });
    }
};
