<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'sent_to_supplier_at')) {
                $table->timestamp('sent_to_supplier_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('purchase_orders', 'sent_to_supplier_by')) {
                $table->foreignId('sent_to_supplier_by')
                    ->nullable()
                    ->after('sent_to_supplier_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('purchase_orders', 'sent_to_supplier_note')) {
                $table->text('sent_to_supplier_note')->nullable()->after('sent_to_supplier_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'sent_to_supplier_by')) {
                try {
                    $table->dropForeign(['sent_to_supplier_by']);
                } catch (\Throwable $e) {
                    //
                }

                $table->dropColumn('sent_to_supplier_by');
            }

            if (Schema::hasColumn('purchase_orders', 'sent_to_supplier_note')) {
                $table->dropColumn('sent_to_supplier_note');
            }

            if (Schema::hasColumn('purchase_orders', 'sent_to_supplier_at')) {
                $table->dropColumn('sent_to_supplier_at');
            }
        });
    }
};
