<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_deliveries', 'confirm_note')) {
                $table->text('confirm_note')->nullable()->after('note');
            }

            if (!Schema::hasColumn('sale_deliveries', 'confirm_note_updated_by')) {
                $table->unsignedBigInteger('confirm_note_updated_by')->nullable()->after('confirm_note');
            }

            if (!Schema::hasColumn('sale_deliveries', 'confirm_note_updated_role')) {
                $table->string('confirm_note_updated_role')->nullable()->after('confirm_note_updated_by');
            }

            if (!Schema::hasColumn('sale_deliveries', 'confirm_note_updated_at')) {
                $table->timestamp('confirm_note_updated_at')->nullable()->after('confirm_note_updated_role');
            }

            // optional: kalau belum ada status partial di enum/logic kamu, ini cukup di controller aja.
        });
    }

    public function down(): void
    {
        Schema::table('sale_deliveries', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('sale_deliveries', 'confirm_note')) $drop[] = 'confirm_note';
            if (Schema::hasColumn('sale_deliveries', 'confirm_note_updated_by')) $drop[] = 'confirm_note_updated_by';
            if (Schema::hasColumn('sale_deliveries', 'confirm_note_updated_role')) $drop[] = 'confirm_note_updated_role';
            if (Schema::hasColumn('sale_deliveries', 'confirm_note_updated_at')) $drop[] = 'confirm_note_updated_at';

            if (!empty($drop)) $table->dropColumn($drop);
        });
    }
};
