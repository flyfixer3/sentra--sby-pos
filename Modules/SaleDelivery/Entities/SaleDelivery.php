<?php

namespace Modules\SaleDelivery\Entities;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Sale\Entities\Sale;

class SaleDelivery extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'quotation_id',
        'sale_order_id',
        'sale_id',
        'delivery_no',

        'customer_id',
        'reference',
        'date',
        'warehouse_id',
        'status',
        'note',

        'confirm_note',
        'confirm_note_updated_by',
        'confirm_note_updated_role',
        'confirm_note_updated_at',

        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'confirmed_at' => 'datetime',
        'confirm_note_updated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SaleDeliveryItem::class, 'sale_delivery_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function saleOrder()
    {
        return $this->belongsTo(\Modules\SaleOrder\Entities\SaleOrder::class, 'sale_order_id');
    }

    public function printLogs()
    {
        return $this->hasMany(\Modules\SaleDelivery\Entities\SaleDeliveryPrintLog::class, 'sale_delivery_id');
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
                $model->reference = 'SDO-TMP-' . strtoupper(bin2hex(random_bytes(4)));
            }
        });

        static::created(function ($model) {
            if (is_string($model->reference) && str_starts_with($model->reference, 'SDO-TMP-')) {
                $model->reference = make_reference_id('SDO', (int) $model->id);
                $model->saveQuietly();
            }
        });

        /**
         * ✅ NEW: cascade soft delete items (walk-in package lifecycle)
         * Ini aman untuk kasus manual delete delivery, atau delete via SaleController.
         */
        static::deleting(function (self $delivery) {
            if ($delivery->isForceDeleting()) {
                $delivery->items()->withTrashed()->forceDelete();
                return;
            }

            $delivery->items()->delete();
        });

        /**
         * ✅ NEW: cascade restore items
         * Kalau SaleDelivery direstore (misal via restore Sale walk-in),
         * items ikut balik.
         */
        static::restoring(function (self $delivery) {
            $delivery->items()->withTrashed()->restore();
        });
    }
}