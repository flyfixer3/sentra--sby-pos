<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'description',
        'date',
        'automated',
        'purchase_id',
        'purchase_payment_id',
        'purchase_return_id',
        'purchase_return_payment_id',
        'sale_id',
        'sale_payment_id',
        'sale_return_id',
        'sale_return_payment_id'
    ];

    public function details() {
        return $this->hasMany(AccountingTransactionDetail::class);
    }
}
