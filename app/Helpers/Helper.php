<?php

namespace App\Helpers;

use App\Models\Entity;
use App\Models\AccountingAccountMapping;
use App\Models\AccountingSubaccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingTransactionDetail;
use App\Models\PaymentMethod;
use App\Services\AccountingPeriodLockService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Helper
{
    public static function resolveAccountingMapping(string $module, string $event, ?int $branchId = null, ?int $entityId = null, ?string $fallbackSubaccountNumber = null): string
    {
        if ($entityId === null && $branchId !== null) {
            $entityId = (int) DB::table('branches')
                ->where('id', $branchId)
                ->value('entity_id');
        }

        $mapping = AccountingAccountMapping::query()
            ->with('subaccount')
            ->where('module', $module)
            ->where('event', $event)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw('CASE WHEN entity_id IS NULL THEN 1 ELSE 0 END ASC')
            ->get()
            ->first(function ($item) use ($branchId, $entityId) {
                $branchMatches = $item->branch_id === null || (int) $item->branch_id === (int) $branchId;
                $entityMatches = $item->entity_id === null || (int) $item->entity_id === (int) $entityId;

                return $branchMatches && $entityMatches;
            });

        if ($mapping && $mapping->subaccount) {
            return (string) $mapping->subaccount->subaccount_number;
        }

        return (string) $fallbackSubaccountNumber;
    }

    public static function convertToSnakeCase($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        return array_map(function ($item) {
            return is_array($item) ? self::convertToSnakeCase($item) : $item;
        }, collect($data)->map(function ($value, $key) {
            if (is_array($value) && Arr::isAssoc($value)) {
                $value = self::convertToSnakeCase($value);
            }

            return [Str::snake($key) => $value];
        })->flatMap(function ($value) {
            return $value;
        })->toArray());
    }

    public static function getStatusFromDeletedAt($deletedAt)
    {
        return $deletedAt === null ? 'ACTIVE' : 'INACTIVE';
    }

    public static function isBankIdRequired($paymentMethodId)
    {
        if (is_null($paymentMethodId)) {
            return false;
        }

        $paymentMethodData = PaymentMethod::find($paymentMethodId);

        return $paymentMethodData->paymentType->payment_type_name == "CASHLESS";
    }

    public static function addNewTransaction($data, $entries)
    {
        // Pakai transaksi yang aman: kalau error otomatis rollback
        DB::transaction(function () use ($data, $entries) {
            $branchId = isset($data['branch_id']) && $data['branch_id'] !== null
                ? (int) $data['branch_id']
                : null;
            $entityId = isset($data['entity_id']) && $data['entity_id'] !== null
                ? (int) $data['entity_id']
                : null;

            if ($entityId === null && $branchId !== null) {
                $entityId = (int) DB::table('branches')
                    ->where('id', $branchId)
                    ->value('entity_id');
            }

            $transactionDate = Carbon::parse($data['date'] ?? now()->toDateString());

            if (AccountingPeriodLockService::isLocked($transactionDate, $branchId, $entityId ?: null)) {
                throw new \RuntimeException('The selected accounting period is locked.');
            }

            $transaction = AccountingTransaction::create([
                'date' => $transactionDate->toDateTimeString(),
                'automated' => true,
                'entity_id' => $entityId ?: null,
                'branch_id' => $branchId,
                'label' => $data['label'],
                'description' => $data['description'],
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => $data['status'] ?? 'posted',
                'posted_at' => $data['posted_at'] ?? now(),
                'purchase_id' => $data['purchase_id'],
                'purchase_payment_id' => $data['purchase_payment_id'],
                'purchase_return_id' => $data['purchase_return_id'],
                'purchase_return_payment_id' => $data['purchase_return_payment_id'],
                'sale_id' => $data['sale_id'],
                'sale_payment_id' => $data['sale_payment_id'],
                'sale_return_id' => $data['sale_return_id'],
                'sale_return_payment_id' => $data['sale_return_payment_id'],
            ]);

            foreach ($entries as $entry) {
                $subaccount = AccountingSubaccount::query()
                    ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                    ->where('accounting_accounts.is_active', '=', '1')
                    ->where('accounting_subaccounts.subaccount_number', '=', $entry['subaccount_number'])
                    ->select('accounting_subaccounts.*')
                    ->first();

                if ($subaccount == null) {
                    // ❌ Jangan dd() lagi. Lempar exception aja biar kebaca error-nya.
                    throw new \Exception('Subaccount not found: ' . $entry['subaccount_number']);
                }

                if ($entry['type'] == 'debit') {
                    $subaccount->total_debit += $entry['amount'];
                } else {
                    $subaccount->total_credit += $entry['amount'];
                }

                $subaccount->save();

                AccountingTransactionDetail::create([
                    'accounting_transaction_id' => $transaction->id,
                    'accounting_subaccount_id' => $subaccount->id,
                    'amount' => $entry['amount'],
                    'type' => $entry['type'],
                ]);
            }
        });
    }
}
