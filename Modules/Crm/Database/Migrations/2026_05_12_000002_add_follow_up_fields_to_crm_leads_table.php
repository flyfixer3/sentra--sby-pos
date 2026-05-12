<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'next_follow_up_date')) {
                $table->date('next_follow_up_date')->nullable()->after('next_follow_up_at');
            }
            if (!Schema::hasColumn('crm_leads', 'next_follow_up_time')) {
                $table->string('next_follow_up_time', 5)->nullable()->after('next_follow_up_date');
            }
            if (!Schema::hasColumn('crm_leads', 'follow_up_note')) {
                $table->text('follow_up_note')->nullable()->after('next_follow_up_time');
            }
            if (!Schema::hasColumn('crm_leads', 'follow_up_status')) {
                $table->string('follow_up_status', 20)->nullable()->after('follow_up_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['next_follow_up_date', 'next_follow_up_time', 'follow_up_note', 'follow_up_status']);
        });
    }
};
