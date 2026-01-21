<?php

namespace Modules\SaleDelivery\Entities;

use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class SaleDelivery extends Model
{
    use HasFactory, HasBranchScope;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(SaleDeliveryItem::class, 'sale_delivery_id');
    }

    // optional relation kalau kamu mau
    // public function customer() { return $this->belongsTo(\Modules\People\Entities\Customer::class); }
    // public function warehouse() { return $this->belongsTo(\Modules\Product\Entities\Warehouse::class); }

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
