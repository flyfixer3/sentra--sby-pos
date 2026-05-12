<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            // 0 = clean, 1–2 = low suspicion, 3+ = high suspicion
            $table->unsignedTinyInteger('suspicious_score')->default(0)->after('last_clicked_at');
            // JSON array of triggered signals: ["ip_velocity", "bot_ua", "session_velocity"]
            $table->json('suspicious_flags')->nullable()->after('suspicious_score');
            // Allow manual override by admin
            $table->boolean('manually_flagged')->default(false)->after('suspicious_flags');
            $table->index('suspicious_score');
        });
    }

    public function down(): void
    {
        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            $table->dropColumn(['suspicious_score', 'suspicious_flags', 'manually_flagged']);
        });
    }
};
