<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Http\Controllers\Api\ReportsController;
use Tests\TestCase;

class MarketingAttributionReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->dropIfExists('crm_cta_clicks');
        Schema::connection('sqlite')->dropIfExists('crm_leads');

        Schema::connection('sqlite')->create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('sale_order_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('crm_cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 128);
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('ref_code', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->string('cta_source', 100)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser_name', 100)->nullable();
            $table->string('os_name', 100)->nullable();
            $table->unsignedInteger('click_count')->default(1);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });

        Gate::shouldReceive('denies')->andReturn(false);
    }

    public function test_marketing_attribution_report_returns_owner_metrics(): void
    {
        $leadId = DB::connection('sqlite')->table('crm_leads')->insertGetId([
            'contact_name' => 'Lead Google',
            'status' => 'prospek_baru',
            'created_at' => '2026-05-18 08:20:00',
            'updated_at' => '2026-05-18 08:20:00',
        ]);

        DB::connection('sqlite')->table('crm_cta_clicks')->insert([
            [
                'session_id' => 'sess-1',
                'lead_id' => $leadId,
                'ref_code' => 'SA-GA-1111',
                'source' => 'google',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'ga_search_tangerang',
                'utm_term' => 'kaca depan',
                'utm_content' => 'ad-123',
                'cta_type' => 'whatsapp',
                'cta_source' => 'wa_header',
                'device_type' => 'mobile',
                'click_count' => 2,
                'first_clicked_at' => '2026-05-18 08:00:00',
                'last_clicked_at' => '2026-05-18 08:10:00',
                'created_at' => '2026-05-18 08:00:00',
                'updated_at' => '2026-05-18 08:10:00',
            ],
            [
                'session_id' => 'sess-2',
                'lead_id' => null,
                'ref_code' => 'SA-DR-2222',
                'source' => 'direct',
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_term' => null,
                'utm_content' => null,
                'cta_type' => 'whatsapp',
                'cta_source' => 'wa_footer',
                'device_type' => 'desktop',
                'click_count' => 1,
                'first_clicked_at' => '2026-05-18 09:00:00',
                'last_clicked_at' => '2026-05-18 09:00:00',
                'created_at' => '2026-05-18 09:00:00',
                'updated_at' => '2026-05-18 09:00:00',
            ],
        ]);

        $response = (new ReportsController())->marketingAttribution(Request::create('/api/crm/reports/marketing-attribution', 'GET', [
            'date_from' => '2026-05-18',
            'date_to' => '2026-05-18',
        ]));

        $payload = $response->getData(true);

        $this->assertSame(2, $payload['summary']['total_cta_clicks']);
        $this->assertSame(1, $payload['summary']['converted_leads']);
        $this->assertSame('Tangerang Area', $payload['executive_summary']['best_campaign_by_leads']['campaign_label']);
        $this->assertContains('Google Ads', array_column($payload['source_medium_performance'], 'source_label'));
        $this->assertSame('kaca depan', $payload['keyword_performance'][0]['keyword']);
        $this->assertNotEmpty($payload['action_insights']);
    }
}
