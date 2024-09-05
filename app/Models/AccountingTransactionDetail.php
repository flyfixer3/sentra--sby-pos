<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingTransactionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'accounting_transaction_id',
        'accounting_subaccount_id',
        'amount',
        'type'
    ];

    public function transaction() {
        return $this->belongsTo(AccountingTransaction::class);
    }

    public function subaccount() {
        return $this->belongsTo(AccountingSubaccount::class, 'accounting_subaccount_id');
    }
}
