<?php

namespace Modules\Purchase\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Support\LegacyImport\ReferenceCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $guarded = [];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseDetails() {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id', 'id');
    }

    public function purchasePayments() {
        return $this->hasMany(PurchasePayment::class, 'purchase_id', 'id');
    }

    public function purchaseDelivery()
    {
        return $this->belongsTo(\Modules\PurchaseDelivery\Entities\PurchaseDelivery::class, 'purchase_delivery_id');
    }


    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            $ref = trim((string) ($model->reference ?? ''));
            if ($ref === '' || strtoupper($ref) === 'AUTO') {
                $model->reference = ReferenceCodeGenerator::generatePurchaseReference(
                    optional($model->branch)->name,
                    $model->date ?? now()
                );
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class);
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'Completed');
    }

    public static function resolvePaymentSnapshot($totalAmount, $paidAmount): array
    {
        $totalAmount = (float) ($totalAmount ?? 0);
        $paidAmount = (float) ($paidAmount ?? 0);

        $dueAmount = max($totalAmount - $paidAmount, 0);
        $overpaidAmount = max($paidAmount - $totalAmount, 0);

        if ($paidAmount <= 0) {
            $paymentStatus = 'Unpaid';
        } elseif ($overpaidAmount > 0) {
            $paymentStatus = 'Overpaid';
        } elseif ($dueAmount > 0) {
            $paymentStatus = 'Partial';
        } else {
            $paymentStatus = 'Paid';
        }

        return [
            'due_amount' => $dueAmount,
            'overpaid_amount' => $overpaidAmount,
            'payment_status' => $paymentStatus,
        ];
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

    public function getEffectiveDueAmountAttribute()
    {
        return (float) static::resolvePaymentSnapshot($this->total_amount, $this->paid_amount)['due_amount'];
    }

    public function getOverpaidAmountAttribute()
    {
        return (float) static::resolvePaymentSnapshot($this->total_amount, $this->paid_amount)['overpaid_amount'];
    }

    public function getEffectivePaymentStatusAttribute()
    {
        return static::resolvePaymentSnapshot($this->total_amount, $this->paid_amount)['payment_status'];
    }

    public function getTaxAmountAttribute($value) {
        return $value / 1;
    }

    public function getDiscountAmountAttribute($value) {
        return $value / 1;
    }
}
