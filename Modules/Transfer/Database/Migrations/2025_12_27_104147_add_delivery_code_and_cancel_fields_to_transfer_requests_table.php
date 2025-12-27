<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('transfer_requests', 'delivery_code')) {
                $table->string('delivery_code', 6)->nullable()->index()->after('delivery_proof_path');
            }

            if (!Schema::hasColumn('transfer_requests', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('printed_by');
            }
            if (!Schema::hasColumn('transfer_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('transfer_requests', 'cancel_note')) {
                $table->string('cancel_note', 1000)->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_requests', 'delivery_code')) {
                $table->dropColumn('delivery_code');
            }
            if (Schema::hasColumn('transfer_requests', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('transfer_requests', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('transfer_requests', 'cancel_note')) {
                $table->dropColumn('cancel_note');
            }
        });
    }
};
