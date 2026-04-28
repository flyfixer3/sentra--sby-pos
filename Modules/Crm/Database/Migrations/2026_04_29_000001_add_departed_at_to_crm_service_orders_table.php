<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_service_orders', 'departed_at')) {
                $table->dateTime('departed_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            if (Schema::hasColumn('crm_service_orders', 'departed_at')) {
                $table->dropColumn('departed_at');
            }
        });
    }
};
