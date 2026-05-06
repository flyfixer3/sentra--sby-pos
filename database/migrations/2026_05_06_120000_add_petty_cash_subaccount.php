<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accountId = DB::table('accounting_accounts')
            ->where('account_number', '1')
            ->value('id');

        if (!$accountId) {
            return;
        }

        $exists = DB::table('accounting_subaccounts')
            ->where('subaccount_number', '1-10003')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('accounting_subaccounts')->insert([
            'accounting_account_id' => $accountId,
            'subaccount_number' => '1-10003',
            'subaccount_name' => 'Petty Cash',
            'description' => 'Kas kecil operasional',
            'total_debit' => 0,
            'total_credit' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounting_subaccounts')
            ->where('subaccount_number', '1-10003')
            ->delete();
    }
};
