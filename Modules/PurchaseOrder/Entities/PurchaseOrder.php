<?php

namespace Modules\PurchaseOrder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Purchase\Entities\Purchase;
use Modules\People\Entities\Supplier;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'purchase_order_id');
    }

    public function purchaseDeliveries()
    {
        return $this->hasMany(PurchaseDelivery::class, 'purchase_order_id');
    }
    public function purchaseOrderDetails() {
        return $this->hasMany(PurchaseOrderDetails::class, 'purchase_order_id', 'id');
    }

    public function remainingQuantity()
    {
        return $this->purchaseOrderDetails->sum('quantity') - $this->fulfilled_quantity;;
    }

    public function calculateFulfilledQuantity()
    {
        $this->fulfilled_quantity = $this->purchaseOrderDetails->sum('fulfilled_quantity');
        $this->save();
    }

    public function isFullyFulfilled()
    {
        return $this->fulfilled_quantity >= $this->purchaseOrderDetails->sum('quantity');
    }


    public function markAsCompleted()
    {
        if ($this->remainingQuantity() <= 0) {
            $this->update(['status' => 'Completed']);
        } else {
            $this->update(['status' => 'Partially Sent']);
        }
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
