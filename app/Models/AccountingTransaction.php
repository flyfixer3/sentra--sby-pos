<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'branch_id',
        'label',
        'description',
        'date',
        'automated',
        'source_type',
        'source_id',
        'status',
        'posted_at',
        'reversed_at',
        'purchase_id',
        'purchase_payment_id',
        'purchase_return_id',
        'purchase_return_payment_id',
        'sale_id',
        'sale_payment_id',
        'sale_return_id',
        'sale_return_payment_id'
    ];

    protected $casts = [
        'automated' => 'boolean',
        'date' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function details() {
        return $this->hasMany(AccountingTransactionDetail::class);
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }
}
