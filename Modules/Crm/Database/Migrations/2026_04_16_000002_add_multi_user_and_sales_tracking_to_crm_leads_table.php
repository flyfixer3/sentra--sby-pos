<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'glass_types')) {
                $table->json('glass_types')->nullable()->after('glass_type');
            }
            if (!Schema::hasColumn('crm_leads', 'conversation_user_ids')) {
                $table->json('conversation_user_ids')->nullable()->after('conversation_with');
            }
            if (!Schema::hasColumn('crm_leads', 'sales_owner_user_ids')) {
                $table->json('sales_owner_user_ids')->nullable()->after('sales_chat_owner');
            }
            if (!Schema::hasColumn('crm_leads', 'realized_price')) {
                $table->unsignedBigInteger('realized_price')->nullable()->after('estimated_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            foreach (['glass_types', 'conversation_user_ids', 'sales_owner_user_ids', 'realized_price'] as $column) {
                if (Schema::hasColumn('crm_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
