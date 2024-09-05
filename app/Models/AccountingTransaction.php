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
        'order_header_id',
    ];

    public function details() {
        return $this->hasMany(AccountingTransactionDetail::class);
    }
}
