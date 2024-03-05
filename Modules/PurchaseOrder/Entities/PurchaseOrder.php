<?php

namespace Modules\PurchaseOrder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\People\Entities\Supplier;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function purchaseOrderDetails() {
        return $this->hasMany(PurchaseOrderDetails::class, 'purchase_order_id', 'id');
    }

    public function supplier() {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            $number = PurchaseOrder::max('id') + 1;
            $model->reference = make_reference_id('PO', $number);
        });
    }

    public function getDateAttribute($value) {
        return Carbon::parse($value)->format('d M, Y');
    }

    public function getShippingAmountAttribute($value) {
        return $value / 1;
    }

    public function getPaidAmountAttribute($value) {
        return $value / 1;
    }

    public function getTotalAmountAttribute($value) {
        return $value / 1;
    }

    public function getDueAmountAttribute($value) {
        return $value / 1;
    }

    public function getTaxAmountAttribute($value) {
        return $value / 1;
    }

    public function getDiscountAmountAttribute($value) {
        return $value / 1;
    }
}
