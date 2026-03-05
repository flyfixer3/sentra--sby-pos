<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'type')) {
                $table->string('type', 10)->default('credit')->after('reference'); // debit|credit
                $table->index('type');
            }

            if (!Schema::hasColumn('expenses', 'payment_method')) {
                // subaccount_number (Kas/Bank) yang dipakai bayar / yang menerima
                $table->string('payment_method', 255)->nullable()->after('type');
                $table->index('payment_method');
            }

            if (!Schema::hasColumn('expenses', 'from_account')) {
                // khusus DEBIT: sumber dana (Kas/Bank asal)
                $table->string('from_account', 255)->nullable()->after('payment_method');
                $table->index('from_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'from_account')) {
                $table->dropIndex(['from_account']);
                $table->dropColumn('from_account');
            }

            if (Schema::hasColumn('expenses', 'payment_method')) {
                $table->dropIndex(['payment_method']);
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('expenses', 'type')) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            }
        });
    }
};