<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\Lead;
use Modules\Crm\Http\Controllers\Api\LeadsController;
use Tests\TestCase;

class LeadCtaClickRelationTest extends TestCase
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('crm_cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->string('cta_source', 100)->nullable();
            $table->string('ref_code', 50)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('gclid', 150)->nullable();
            $table->string('landing_page_url', 2048)->nullable();
            $table->string('target_whatsapp_number', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_lead_detail_cta_click_eager_load_uses_unambiguous_columns(): void
    {
        $leadId = DB::connection('sqlite')->table('crm_leads')->insertGetId([
            'contact_name' => 'Tracking Lead',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('sqlite')->table('crm_cta_clicks')->insert([
            'lead_id' => $leadId,
            'cta_type' => 'whatsapp',
            'cta_source' => 'wa_hero_primary',
            'ref_code' => 'SA-GA-TEST',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'test_ads',
            'gclid' => 'test-gclid-123',
            'landing_page_url' => 'https://sentraautoglass.com/',
            'target_whatsapp_number' => '6281281161200',
            'source' => 'google',
            'last_clicked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new class extends LeadsController {
            public function exposedLeadRelations(): array
            {
                return $this->leadRelations();
            }
        };

        $relations = $controller->exposedLeadRelations();
        $lead = Lead::withoutGlobalScopes()
            ->with(['ctaClick' => $relations['ctaClick']])
            ->findOrFail($leadId);

        $this->assertSame('SA-GA-TEST', $lead->ctaClick?->ref_code);
        $this->assertSame('test-gclid-123', $lead->ctaClick?->gclid);
    }
}
