<?php

namespace Modules\PurchaseOrder\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Purchase\Entities\Purchase;
use Modules\People\Entities\Supplier;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

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

    public function hasInvoice(): bool
    {
        if ($this->relationLoaded('purchases')) {
            return $this->purchases->whereNull('deleted_at')->count() > 0;
        }

        return $this->purchases()
            ->whereNull('deleted_at')
            ->exists();
    }

    public function hasActiveDeliveries(): bool
    {
        if ($this->relationLoaded('purchaseDeliveries')) {
            return $this->purchaseDeliveries->count() > 0;
        }

        return $this->purchaseDeliveries()->exists();
    }

    public function allocatedDeliveryQtyByProduct(): array
    {
        $rows = DB::table('purchase_delivery_details as pdd')
            ->join('purchase_deliveries as pd', 'pd.id', '=', 'pdd.purchase_delivery_id')
            ->where('pd.purchase_order_id', (int) $this->id)
            ->whereNull('pd.deleted_at')
            ->selectRaw('pdd.product_id, COALESCE(SUM(pdd.quantity), 0) as allocated_qty')
            ->groupBy('pdd.product_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row->product_id ?? 0)] = (int) ($row->allocated_qty ?? 0);
        }

        return $map;
    }

    public function purchaseOrderDetailsWithDeliveryRemaining()
    {
        $details = $this->relationLoaded('purchaseOrderDetails')
            ? $this->purchaseOrderDetails
            : $this->purchaseOrderDetails()->get();

        $allocatedMap = $this->allocatedDeliveryQtyByProduct();

        return $details->map(function ($detail) use (&$allocatedMap) {
            $productId = (int) ($detail->product_id ?? 0);
            $orderedQty = (int) ($detail->quantity ?? 0);
            $allocatedQty = (int) ($allocatedMap[$productId] ?? 0);

            $remainingQty = max($orderedQty - $allocatedQty, 0);

            $detail->allocated_delivery_quantity = $allocatedQty;
            $detail->delivery_remaining_quantity = $remainingQty;

            return $detail;
        });
    }

    public function hasRemainingDeliveryQuantity(): bool
    {
        return $this->purchaseOrderDetailsWithDeliveryRemaining()
            ->contains(function ($detail) {
                return (int) ($detail->delivery_remaining_quantity ?? 0) > 0;
            });
    }

    public function hasLegacyPurchaseInvoiceConflict(?int $excludePurchaseDeliveryId = null): bool
    {
        $q = Purchase::query()
            ->leftJoin('purchase_deliveries as pd', 'pd.id', '=', 'purchases.purchase_delivery_id')
            ->where('purchases.purchase_order_id', (int) $this->id)
            ->whereNull('purchases.deleted_at')
            ->where(function ($w) {
                $w->whereNull('purchases.purchase_delivery_id')
                    ->orWhere('pd.note', PurchaseDelivery::AUTO_CREATED_FROM_PURCHASE_NOTE);
            });

        if (!empty($excludePurchaseDeliveryId)) {
            $q->where(function ($w) use ($excludePurchaseDeliveryId) {
                $w->whereNull('purchases.purchase_delivery_id')
                    ->orWhere('purchases.purchase_delivery_id', '!=', (int) $excludePurchaseDeliveryId);
            });
        }

        return $q->exists();
    }

    public function hasAllActiveDeliveriesInvoiced(): bool
    {
        $deliveryIds = $this->relationLoaded('purchaseDeliveries')
            ? $this->purchaseDeliveries->pluck('id')->filter()->values()
            : $this->purchaseDeliveries()->pluck('id');

        $deliveryCount = $deliveryIds->count();
        if ($deliveryCount <= 0) {
            return false;
        }

        $invoicedCount = Purchase::query()
            ->whereNull('deleted_at')
            ->whereIn('purchase_delivery_id', $deliveryIds->all())
            ->distinct('purchase_delivery_id')
            ->count('purchase_delivery_id');

        return $invoicedCount >= $deliveryCount;
    }

    public function isFullyInvoiced(): bool
    {
        if ($this->hasLegacyPurchaseInvoiceConflict()) {
            return true;
        }

        if ($this->hasActiveDeliveries()) {
            return $this->hasAllActiveDeliveriesInvoiced();
        }

        return $this->hasInvoice();
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
        $this->refreshStatus();
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $number = PurchaseOrder::max('id') + 1;
            $model->reference = make_reference_id('PO', $number);
        });
    }

    public function refreshStatus(): void
    {
        $fulfilled = $this->calculateFulfilledQuantity();
        $remaining = $this->remainingQuantity();
        $ordered   = $this->totalOrderedQuantity();

        // ✅ status tetap fulfillment-driven, bukan invoice-driven
        $status = 'Pending';

        if ($ordered > 0 && $remaining <= 0) {
            $status = $this->isFullyInvoiced() ? 'Completed' : 'Delivered';
        } elseif ($fulfilled > 0 && $remaining > 0) {
            $status = 'Partial';
        } else {
            $status = 'Pending';
        }

        $this->update(['status' => $status]);
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
