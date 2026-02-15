<?php

namespace Modules\SaleOrder\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Quotation\Entities\Quotation;
use Modules\Sale\Entities\Sale;

class SaleOrder extends BaseModel
{
    protected $table = 'sale_orders';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'quotation_id',
        'sale_id',
        'warehouse_id',
        'reference',
        'date',
        'status',
        'note',

        // NEW: financial
        'tax_percentage',
        'tax_amount',
        'discount_percentage',
        'discount_amount',
        'shipping_amount',
        'fee_amount',
        'subtotal_amount',
        'total_amount',

        // NEW: deposit
        'deposit_percentage',
        'deposit_amount',
        'deposit_received_amount',
        'deposit_payment_method',
        'deposit_code',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(\Modules\SaleDelivery\Entities\SaleDelivery::class, 'sale_order_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (empty($model->status)) {
                $model->status = 'pending';
            }

            if (empty($model->reference)) {
                $model->reference = 'SO-TMP-' . strtoupper(bin2hex(random_bytes(4)));
            }
        });

        static::created(function ($model) {
            if (is_string($model->reference) && str_starts_with($model->reference, 'SO-TMP-')) {
                $model->reference = make_reference_id('SO', (int) $model->id);
                $model->saveQuietly();
            }
        });
    }
}
