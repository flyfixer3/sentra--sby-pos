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
            if (!Schema::hasColumn('crm_cta_clicks', 'cta_source')) {
                $table->string('cta_source', 100)->nullable()->after('cta_type');
                $table->index('cta_source');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_cta_clicks')) {
            return;
        }

        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('crm_cta_clicks', 'cta_source')) {
                $table->dropIndex(['cta_source']);
                $table->dropColumn('cta_source');
            }
        });
    }
};
