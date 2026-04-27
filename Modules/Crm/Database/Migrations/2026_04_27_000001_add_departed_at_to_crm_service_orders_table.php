<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            $table->dateTime('departed_at')->nullable()->after('started_at');
            $table->string('install_location_type', 32)->nullable()->after('map_link_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            $table->dropColumn(['departed_at', 'install_location_type']);
        });
    }
};
