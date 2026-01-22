<?php

namespace Modules\SaleDelivery\Entities;

use App\Models\User;
use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;

class SaleDelivery extends Model
{
    use HasFactory, HasBranchScope;

   protected $fillable = [
        'branch_id',
        'quotation_id',
        'customer_id',
        'reference',
        'date',
        'warehouse_id',
        'status',
        'note',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'confirmed_at' => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // reference auto
            $next = (int) (static::max('id') ?? 0) + 1;
            $model->reference = $model->reference ?: make_reference_id('SDO', $next);

            // created_by
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }
}
