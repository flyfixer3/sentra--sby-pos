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
            if (!Schema::hasColumn('crm_cta_clicks', 'page_path')) {
                $table->string('page_path', 2048)->nullable()->after('landing_page_url');
            }

            if (!Schema::hasColumn('crm_cta_clicks', 'referrer_url')) {
                $table->string('referrer_url', 2048)->nullable()->after('page_path');
            }

            if (!Schema::hasColumn('crm_cta_clicks', 'gbraid')) {
                $table->string('gbraid', 150)->nullable()->after('gclid');
            }

            if (!Schema::hasColumn('crm_cta_clicks', 'wbraid')) {
                $table->string('wbraid', 150)->nullable()->after('gbraid');
            }

            foreach ([
                'device_type' => 50,
                'browser_name' => 100,
                'browser_version' => 50,
                'os_name' => 100,
                'os_version' => 50,
                'language' => 50,
                'timezone' => 100,
                'ip_city' => 120,
                'ip_region' => 120,
                'ip_country' => 2,
            ] as $column => $length) {
                if (!Schema::hasColumn('crm_cta_clicks', $column)) {
                    $table->string($column, $length)->nullable()->after('user_agent');
                }
            }

            if (!Schema::hasColumn('crm_cta_clicks', 'screen_width')) {
                $table->unsignedInteger('screen_width')->nullable()->after('user_agent');
            }

            if (!Schema::hasColumn('crm_cta_clicks', 'screen_height')) {
                $table->unsignedInteger('screen_height')->nullable()->after('screen_width');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_cta_clicks')) {
            return;
        }

        Schema::table('crm_cta_clicks', function (Blueprint $table) {
            foreach ([
                'page_path',
                'referrer_url',
                'gbraid',
                'wbraid',
                'device_type',
                'browser_name',
                'browser_version',
                'os_name',
                'os_version',
                'screen_width',
                'screen_height',
                'language',
                'timezone',
                'ip_city',
                'ip_region',
                'ip_country',
            ] as $column) {
                if (Schema::hasColumn('crm_cta_clicks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
