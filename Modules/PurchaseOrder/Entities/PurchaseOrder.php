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

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetails::class, 'purchase_order_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /**
     * ✅ Total ordered qty
     */
    public function totalOrderedQuantity(): int
    {
        // pastikan relation loaded aman
        if ($this->relationLoaded('purchaseOrderDetails')) {
            return (int) $this->purchaseOrderDetails->sum('quantity');
        }

        return (int) $this->purchaseOrderDetails()->sum('quantity');
    }

    /**
     * ✅ Total fulfilled qty (sum of detail.fulfilled_quantity)
     */
    public function totalFulfilledQuantity(): int
    {
        if ($this->relationLoaded('purchaseOrderDetails')) {
            return (int) $this->purchaseOrderDetails->sum('fulfilled_quantity');
        }

        return (int) $this->purchaseOrderDetails()->sum('fulfilled_quantity');
    }

    /**
     * ✅ Remaining = ordered - fulfilled
     */
    public function remainingQuantity(): int
    {
        return $this->totalOrderedQuantity() - $this->totalFulfilledQuantity();
    }

    /**
     * ✅ Persist header fulfilled_quantity (optional field di PO)
     */
   public function calculateFulfilledQuantity(): int
    {
        return (int) $this->purchaseOrderDetails()->sum('fulfilled_quantity');
    }


    public function isFullyFulfilled(): bool
    {
        return $this->remainingQuantity() <= 0;
    }

    /**
     * ✅ Update status PO berdasar remaining
     * - Completed kalau remaining <= 0
     * - Partially Sent kalau fulfilled > 0 dan remaining > 0
     * - Pending kalau fulfilled == 0
     */
    public function markAsCompleted(): void
    {
        $remaining = $this->purchaseOrderDetails()
            ->whereColumn('quantity', '>', 'fulfilled_quantity')
            ->count();

        $this->update([
            'status' => ($remaining > 0) ? 'Pending' : 'Completed',
        ]);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $number = PurchaseOrder::max('id') + 1;
            $model->reference = make_reference_id('PO', $number);
        });
    }

    // Accessors yang sudah ada tetap
    public function getDateAttribute($value)
    {
        return Carbon::parse($value)->format('d M, Y');
    }

    public function getShippingAmountAttribute($value)
    {
        return $value / 1;
    }

    public function getPaidAmountAttribute($value)
    {
        return $value / 1;
    }

    public function getTotalAmountAttribute($value)
    {
        return $value / 1;
    }

    public function getDueAmountAttribute($value)
    {
        return $value / 1;
    }

    public function getTaxAmountAttribute($value)
    {
        return $value / 1;
    }

    public function getDiscountAmountAttribute($value)
    {
        return $value / 1;
    }
}
