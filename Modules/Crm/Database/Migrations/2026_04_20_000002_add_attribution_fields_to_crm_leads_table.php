<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'ref_code')) {
                $table->string('ref_code', 50)->nullable()->after('source');
                $table->index('ref_code');
            }
            if (!Schema::hasColumn('crm_leads', 'utm_source')) {
                $table->string('utm_source', 100)->nullable()->after('ref_code');
            }
            if (!Schema::hasColumn('crm_leads', 'utm_medium')) {
                $table->string('utm_medium', 100)->nullable()->after('utm_source');
            }
            if (!Schema::hasColumn('crm_leads', 'utm_campaign')) {
                $table->string('utm_campaign', 150)->nullable()->after('utm_medium');
            }
            if (!Schema::hasColumn('crm_leads', 'utm_term')) {
                $table->string('utm_term', 150)->nullable()->after('utm_campaign');
            }
            if (!Schema::hasColumn('crm_leads', 'utm_content')) {
                $table->string('utm_content', 150)->nullable()->after('utm_term');
            }
            if (!Schema::hasColumn('crm_leads', 'gclid')) {
                $table->string('gclid', 150)->nullable()->after('utm_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (Schema::hasColumn('crm_leads', 'ref_code')) {
                $table->dropIndex(['ref_code']);
            }

            foreach (['ref_code', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'] as $column) {
                if (Schema::hasColumn('crm_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
