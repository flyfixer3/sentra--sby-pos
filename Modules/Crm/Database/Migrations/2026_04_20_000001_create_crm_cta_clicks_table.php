<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_cta_clicks')) {
            return;
        }

        Schema::create('crm_cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 128);
            $table->string('ref_code', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('landing_page_url', 2048)->nullable();
            $table->string('cta_type', 50)->nullable();
            $table->string('target_whatsapp_number', 50)->nullable();
            $table->unsignedInteger('click_count')->default(1);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'cta_type', 'last_clicked_at'], 'crm_cta_clicks_session_cta_last_index');
            $table->index(['ref_code', 'source'], 'crm_cta_clicks_ref_source_index');
            $table->index('target_whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_cta_clicks');
    }
};
