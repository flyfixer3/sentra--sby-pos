<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountingSubaccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'accounting_account_id',
        'subaccount_number',
        'subaccount_name',
        'description',
        'total_debit',
        'total_credit'
    ];

    public function account(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
