<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_cta_clicks')) {
            return;
        }

        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_cta_clicks', 'gclid')) {
                $table->string('gclid', 150)->nullable()->after('utm_content');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_cta_clicks')) {
            return;
        }

        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('crm_cta_clicks', 'gclid')) {
                $table->dropColumn('gclid');
            }
        });
    }
};
