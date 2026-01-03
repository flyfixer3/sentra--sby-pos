<?php

namespace Modules\PurchaseOrder\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
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
     * ✅ Remaining quantity (rule kamu):
     * remaining dihitung per item: max(0, quantity - fulfilled_quantity)
     * supaya gak minus dan sesuai konsep "remaining masih ada kalau ada item belum terpenuhi".
     */
    public function remainingQuantity(): int
    {
        if ($this->relationLoaded('purchaseOrderDetails')) {
            return (int) $this->purchaseOrderDetails->sum(function ($d) {
                return max(0, (int) $d->quantity - (int) $d->fulfilled_quantity);
            });
        }

        // query mode (lebih aman pakai get)
        $details = $this->purchaseOrderDetails()->get(['quantity', 'fulfilled_quantity']);

        return (int) $details->sum(function ($d) {
            return max(0, (int) $d->quantity - (int) $d->fulfilled_quantity);
        });
    }

    /**
     * ✅ Persist header fulfilled_quantity (kalau kolomnya ada)
     * Return total fulfilled (selalu)
     */
    public function calculateFulfilledQuantity(): int
    {
        $total = (int) $this->purchaseOrderDetails()->sum('fulfilled_quantity');

        // Kalau kamu memang punya kolom fulfilled_quantity di header PO, update sekalian
        if (Schema::hasColumn($this->getTable(), 'fulfilled_quantity')) {
            $this->update(['fulfilled_quantity' => $total]);
        }

        return $total;
    }

    public function isFullyFulfilled(): bool
    {
        return $this->remainingQuantity() <= 0 && $this->totalOrderedQuantity() > 0;
    }

    /**
     * ✅ Update status PO sesuai rule yang kamu mau:
     * - Pending  : fulfilled == 0
     * - Partial  : fulfilled > 0 dan remaining > 0
     * - Completed: remaining == 0 (dan ordered > 0)
     */
    public function markAsCompleted(): void
    {
        // pastikan fulfilled header (kalau ada kolom) ikut ke-update
        $fulfilled = $this->calculateFulfilledQuantity();
        $remaining = $this->remainingQuantity();
        $ordered   = $this->totalOrderedQuantity();

        $status = 'Pending';

        if ($ordered > 0 && $remaining <= 0) {
            $status = 'Completed';
        } elseif ($fulfilled > 0 && $remaining > 0) {
            $status = 'Partial';
        } else {
            $status = 'Pending';
        }

        // update status saja (fulfilled_quantity sudah diupdate di calculateFulfilledQuantity bila ada kolom)
        $this->update([
            'status' => $status,
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
