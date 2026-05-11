<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            $table->string('condition_status', 20)->nullable()->after('technician_note');  // 'good' | 'defect'
            $table->text('handover_notes')->nullable()->after('condition_status');
            $table->decimal('packing_length', 8, 2)->nullable()->after('handover_notes');
            $table->decimal('packing_width', 8, 2)->nullable()->after('packing_length');
            $table->decimal('packing_height', 8, 2)->nullable()->after('packing_width');
        });
    }

    public function down(): void
    {
        Schema::table('crm_service_orders', function (Blueprint $table) {
            $table->dropColumn(['condition_status', 'handover_notes', 'packing_length', 'packing_width', 'packing_height']);
        });
    }
};
