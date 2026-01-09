<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {

            // ===== NOTE (create/edit PD) meta =====
            if (!Schema::hasColumn('purchase_deliveries', 'note_updated_by')) {
                $table->unsignedBigInteger('note_updated_by')->nullable()->after('note');
                $table->index('note_updated_by');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'note_updated_role')) {
                $table->string('note_updated_role', 255)->nullable()->after('note_updated_by');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'note_updated_at')) {
                $table->timestamp('note_updated_at')->nullable()->after('note_updated_role');
            }

            // ===== CONFIRM NOTE meta =====
            if (!Schema::hasColumn('purchase_deliveries', 'confirm_note')) {
                $table->text('confirm_note')->nullable()->after('note_updated_at');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'confirm_note_updated_by')) {
                $table->unsignedBigInteger('confirm_note_updated_by')->nullable()->after('confirm_note');
                $table->index('confirm_note_updated_by');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'confirm_note_updated_role')) {
                $table->string('confirm_note_updated_role', 255)->nullable()->after('confirm_note_updated_by');
            }

            if (!Schema::hasColumn('purchase_deliveries', 'confirm_note_updated_at')) {
                $table->timestamp('confirm_note_updated_at')->nullable()->after('confirm_note_updated_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_deliveries', function (Blueprint $table) {

            if (Schema::hasColumn('purchase_deliveries', 'confirm_note_updated_by')) {
                $table->dropIndex(['confirm_note_updated_by']);
            }

            if (Schema::hasColumn('purchase_deliveries', 'note_updated_by')) {
                $table->dropIndex(['note_updated_by']);
            }

            $cols = [
                'note_updated_by',
                'note_updated_role',
                'note_updated_at',
                'confirm_note',
                'confirm_note_updated_by',
                'confirm_note_updated_role',
                'confirm_note_updated_at',
            ];

            foreach ($cols as $c) {
                if (Schema::hasColumn('purchase_deliveries', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
