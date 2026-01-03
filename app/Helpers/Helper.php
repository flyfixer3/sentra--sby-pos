<?php

namespace App\Helpers;

use App\Models\AccountingSubaccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingTransactionDetail;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Helper
{
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

            $transaction = AccountingTransaction::create([
                'date' => $data['date'] ?? now()->toDateString(),
                'automated' => true,
                'label' => $data['label'],
                'description' => $data['description'],
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
