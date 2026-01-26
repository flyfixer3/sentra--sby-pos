<?php

namespace Modules\SaleOrder\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\People\Entities\Customer;

class SaleOrder extends BaseModel
{
    protected $table = 'sale_orders';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'reference',
        'date',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }
}
