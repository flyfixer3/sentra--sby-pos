<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPostingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'accounting_posting_id',
        'accounting_subaccount_id',
        'amount',
    ];
}
