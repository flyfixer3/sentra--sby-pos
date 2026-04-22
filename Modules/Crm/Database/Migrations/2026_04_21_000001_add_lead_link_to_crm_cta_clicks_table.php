<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_cta_clicks', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('target_whatsapp_number');
                $table->index('lead_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('crm_cta_clicks', 'lead_id')) {
                $table->dropIndex(['lead_id']);
                $table->dropColumn('lead_id');
            }
        });
    }
};
