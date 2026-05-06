<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('accounting_posting_id')->constrained('entities')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('entity_id')->constrained('branches')->nullOnDelete();
            $table->string('source_type')->nullable()->after('branch_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('status')->default('posted')->after('source_id');
            $table->timestamp('posted_at')->nullable()->after('status');
            $table->timestamp('reversed_at')->nullable()->after('posted_at');
        });

        $transactions = DB::table('accounting_transactions')->select('id')->get();

        foreach ($transactions as $transaction) {
            $branchId = null;
            $sourceType = null;
            $sourceId = null;

            $row = DB::table('accounting_transactions')->where('id', $transaction->id)->first([
                'sale_id',
                'sale_payment_id',
                'purchase_id',
                'purchase_payment_id',
                'sale_return_id',
                'sale_return_payment_id',
                'purchase_return_id',
                'purchase_return_payment_id',
                'automated',
                'created_at',
            ]);

            if (!empty($row->sale_id)) {
                $branchId = DB::table('sales')->where('id', $row->sale_id)->value('branch_id');
                $sourceType = 'sale';
                $sourceId = $row->sale_id;
            } elseif (!empty($row->sale_payment_id)) {
                $branchId = DB::table('sale_payments as sp')
                    ->leftJoin('sales as s', 's.id', '=', 'sp.sale_id')
                    ->where('sp.id', $row->sale_payment_id)
                    ->value('s.branch_id');
                $sourceType = 'sale_payment';
                $sourceId = $row->sale_payment_id;
            } elseif (!empty($row->purchase_id)) {
                $branchId = DB::table('purchases')->where('id', $row->purchase_id)->value('branch_id');
                $sourceType = 'purchase';
                $sourceId = $row->purchase_id;
            } elseif (!empty($row->purchase_payment_id)) {
                $branchId = DB::table('purchase_payments as pp')
                    ->leftJoin('purchases as p', 'p.id', '=', 'pp.purchase_id')
                    ->where('pp.id', $row->purchase_payment_id)
                    ->value('p.branch_id');
                $sourceType = 'purchase_payment';
                $sourceId = $row->purchase_payment_id;
            } elseif (!empty($row->sale_return_id)) {
                $branchId = DB::table('sale_returns')->where('id', $row->sale_return_id)->value('branch_id');
                $sourceType = 'sale_return';
                $sourceId = $row->sale_return_id;
            } elseif (!empty($row->sale_return_payment_id)) {
                $branchId = DB::table('sale_return_payments as srp')
                    ->leftJoin('sale_returns as sr', 'sr.id', '=', 'srp.sale_return_id')
                    ->where('srp.id', $row->sale_return_payment_id)
                    ->value('sr.branch_id');
                $sourceType = 'sale_return_payment';
                $sourceId = $row->sale_return_payment_id;
            } elseif (!empty($row->purchase_return_id)) {
                $branchId = DB::table('purchase_returns')->where('id', $row->purchase_return_id)->value('branch_id');
                $sourceType = 'purchase_return';
                $sourceId = $row->purchase_return_id;
            } elseif (!empty($row->purchase_return_payment_id)) {
                $branchId = DB::table('purchase_return_payments as prp')
                    ->leftJoin('purchase_returns as pr', 'pr.id', '=', 'prp.purchase_return_id')
                    ->where('prp.id', $row->purchase_return_payment_id)
                    ->value('pr.branch_id');
                $sourceType = 'purchase_return_payment';
                $sourceId = $row->purchase_return_payment_id;
            }

            $entityId = null;
            if ($branchId) {
                $entityId = DB::table('branches')->where('id', $branchId)->value('entity_id');
            }

            DB::table('accounting_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'branch_id' => $branchId,
                    'entity_id' => $entityId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'status' => 'posted',
                    'posted_at' => $row->automated ? ($row->created_at ?? now()) : null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('accounting_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'source_type',
                'source_id',
                'status',
                'posted_at',
                'reversed_at',
            ]);
        });
    }
};
