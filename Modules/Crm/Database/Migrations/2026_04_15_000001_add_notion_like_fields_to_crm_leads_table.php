<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'vehicle_year')) {
                $table->string('vehicle_year', 20)->nullable()->after('vehicle_model');
            }
            if (!Schema::hasColumn('crm_leads', 'service_type')) {
                $table->string('service_type', 100)->nullable()->after('vehicle_plate');
            }
            if (!Schema::hasColumn('crm_leads', 'glass_type')) {
                $table->string('glass_type', 100)->nullable()->after('service_type');
            }
            if (!Schema::hasColumn('crm_leads', 'estimated_price')) {
                $table->unsignedBigInteger('estimated_price')->nullable()->after('glass_type');
            }
            if (!Schema::hasColumn('crm_leads', 'stock_status')) {
                $table->string('stock_status', 50)->nullable()->after('estimated_price');
            }
            if (!Schema::hasColumn('crm_leads', 'install_location')) {
                $table->text('install_location')->nullable()->after('stock_status');
            }
            if (!Schema::hasColumn('crm_leads', 'map_link')) {
                $table->string('map_link', 500)->nullable()->after('install_location');
            }
            if (!Schema::hasColumn('crm_leads', 'scheduled_at')) {
                $table->dateTime('scheduled_at')->nullable()->after('map_link');
                $table->index('scheduled_at');
            }
            if (!Schema::hasColumn('crm_leads', 'conversation_with')) {
                $table->string('conversation_with')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('crm_leads', 'sales_chat_owner')) {
                $table->string('sales_chat_owner')->nullable()->after('conversation_with');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $columns = [
                'vehicle_year',
                'service_type',
                'glass_type',
                'estimated_price',
                'stock_status',
                'install_location',
                'map_link',
                'scheduled_at',
                'conversation_with',
                'sales_chat_owner',
            ];

            if (Schema::hasColumn('crm_leads', 'scheduled_at')) {
                $table->dropIndex(['scheduled_at']);
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn('crm_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
