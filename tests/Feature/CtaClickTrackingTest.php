<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class CtaClickTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->dropIfExists('crm_cta_clicks');
        Schema::connection('sqlite')->create('crm_cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 128);
            $table->string('ref_code', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('gclid', 150)->nullable();
            $table->string('gbraid', 150)->nullable();
            $table->string('wbraid', 150)->nullable();
            $table->string('landing_page_url', 2048)->nullable();
            $table->string('page_path', 2048)->nullable();
            $table->string('referrer_url', 2048)->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->string('cta_source', 100)->nullable();
            $table->string('target_whatsapp_number', 50)->nullable();
            $table->unsignedInteger('click_count')->default(1);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser_name', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('os_name', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->unsignedInteger('screen_width')->nullable();
            $table->unsignedInteger('screen_height')->nullable();
            $table->string('language', 50)->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('ip_city', 120)->nullable();
            $table->string('ip_region', 120)->nullable();
            $table->string('ip_country', 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_legacy_payload_without_cta_source_is_still_accepted(): void
    {
        $payload = [
            'session_id' => 'sess-legacy',
            'ref_code' => 'SA-GA-TEST1',
            'source' => 'google',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'brand',
            'landing_page_url' => 'https://sentraautoglass.com/',
            'cta_type' => 'whatsapp',
            'target_whatsapp_number' => '6281281161200',
        ];

        $response = $this->postJson('/api/tracking/cta-click', $payload);

        $response->assertCreated()->assertJson([
            'success' => true,
            'deduped' => false,
        ]);

        $this->assertDatabaseHas('crm_cta_clicks', [
            'session_id' => 'sess-legacy',
            'cta_type' => 'whatsapp',
            'cta_source' => null,
            'target_whatsapp_number' => '6281281161200',
        ], 'sqlite');
    }

    public function test_payload_with_cta_source_is_accepted_and_persisted(): void
    {
        $payload = [
            'session_id' => 'sess-modern',
            'ref_code' => 'SA-GA-TEST2',
            'source' => 'google',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'brand',
            'landing_page_url' => 'https://sentraautoglass.com/lokasi',
            'cta_type' => 'whatsapp',
            'cta_source' => 'wa_hero_primary',
            'target_whatsapp_number' => '6281281161200',
        ];

        $response = $this->postJson('/api/tracking/cta-click', $payload);

        $response->assertCreated()->assertJson([
            'success' => true,
            'deduped' => false,
        ]);

        $this->assertDatabaseHas('crm_cta_clicks', [
            'session_id' => 'sess-modern',
            'cta_type' => 'whatsapp',
            'cta_source' => 'wa_hero_primary',
            'target_whatsapp_number' => '6281281161200',
        ], 'sqlite');
    }

    public function test_different_cta_source_creates_distinct_tracking_rows(): void
    {
        $basePayload = [
            'session_id' => 'sess-split',
            'ref_code' => 'SA-GA-TEST3',
            'source' => 'google',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'brand',
            'landing_page_url' => 'https://sentraautoglass.com/',
            'cta_type' => 'whatsapp',
            'target_whatsapp_number' => '6281281161200',
        ];

        $this->postJson('/api/tracking/cta-click', $basePayload + ['cta_source' => 'wa_header'])
            ->assertCreated();

        $this->postJson('/api/tracking/cta-click', $basePayload + ['cta_source' => 'wa_footer'])
            ->assertCreated();

        $this->assertSame(2, DB::connection('sqlite')->table('crm_cta_clicks')->count());
    }

    public function test_visitor_context_payload_is_accepted_and_persisted(): void
    {
        $payload = [
            'session_id' => 'sess-context',
            'ref_code' => 'SA-GA-CTX1',
            'source' => 'google',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'brand',
            'gclid' => 'test-gclid',
            'gbraid' => 'test-gbraid',
            'wbraid' => 'test-wbraid',
            'landing_page_url' => 'https://sentraautoglass.com/lokasi?utm_source=google',
            'page_path' => '/lokasi',
            'referrer_url' => 'https://www.google.com/',
            'cta_type' => 'whatsapp',
            'cta_source' => 'wa_header',
            'target_whatsapp_number' => '6281281161200',
            'device_type' => 'mobile',
            'browser_name' => 'Chrome',
            'browser_version' => '125.0',
            'os_name' => 'Android',
            'os_version' => '14',
            'screen_width' => 390,
            'screen_height' => 844,
            'language' => 'id-ID',
            'timezone' => 'Asia/Jakarta',
        ];

        $response = $this
            ->withHeader('CF-IPCity', 'Surabaya')
            ->withHeader('CF-Region', 'East Java')
            ->withHeader('CF-IPCountry', 'ID')
            ->postJson('/api/tracking/cta-click', $payload);

        $response->assertCreated()->assertJson([
            'success' => true,
            'deduped' => false,
        ]);

        $this->assertDatabaseHas('crm_cta_clicks', [
            'session_id' => 'sess-context',
            'page_path' => '/lokasi',
            'referrer_url' => 'https://www.google.com/',
            'gclid' => 'test-gclid',
            'gbraid' => 'test-gbraid',
            'wbraid' => 'test-wbraid',
            'device_type' => 'mobile',
            'browser_name' => 'Chrome',
            'os_name' => 'Android',
            'screen_width' => 390,
            'screen_height' => 844,
            'language' => 'id-ID',
            'timezone' => 'Asia/Jakarta',
            'ip_city' => 'Surabaya',
            'ip_region' => 'East Java',
            'ip_country' => 'ID',
        ], 'sqlite');
    }

    public function test_cta_source_migration_adds_nullable_column(): void
    {
        Schema::connection('sqlite')->dropIfExists('crm_cta_clicks');
        Schema::connection('sqlite')->create('crm_cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 128);
            $table->string('cta_type', 50)->nullable();
            $table->string('target_whatsapp_number', 50)->nullable();
            $table->timestamps();
        });

        $migration = require base_path('Modules/Crm/Database/Migrations/2026_05_08_000001_add_cta_source_to_crm_cta_clicks_table.php');
        $migration->up();

        $this->assertTrue(Schema::connection('sqlite')->hasColumn('crm_cta_clicks', 'cta_source'));

        DB::connection('sqlite')->table('crm_cta_clicks')->insert([
            'session_id' => 'sess-migration',
            'cta_type' => 'whatsapp',
            'target_whatsapp_number' => '6281281161200',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('crm_cta_clicks', [
            'session_id' => 'sess-migration',
            'cta_source' => null,
        ], 'sqlite');
    }
}
