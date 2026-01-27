<?php

namespace Modules\SaleDelivery\Entities;

use App\Models\User;
use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Sale\Entities\Sale;

class SaleDelivery extends Model
{
    use HasFactory, HasBranchScope;

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
    }
}
