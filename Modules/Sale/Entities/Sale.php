<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\SaleDelivery\Entities\SaleDelivery;

class Sale extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $dates = ['deleted_at'];

    public function saleDetails() {
        return $this->hasMany(SaleDetails::class, 'sale_id', 'id');
    }

    public function salePayments() {
        return $this->hasMany(SalePayment::class, 'sale_id', 'id');
    }

    public function saleDeliveries() {
        return $this->hasMany(SaleDelivery::class, 'sale_id', 'id');
    }

    public function editLockReason(): ?string
    {
        if (strtolower(trim((string) ($this->payment_status ?? ''))) !== 'unpaid') {
            return 'This sale cannot be edited because its payment status is not Unpaid.';
        }

        if ((int) ($this->paid_amount ?? 0) > 0) {
            return 'This sale cannot be edited because it already has payment amount.';
        }

        $paymentsCount = array_key_exists('sale_payments_count', $this->attributes)
            ? (int) $this->attributes['sale_payments_count']
            : ($this->relationLoaded('salePayments') ? $this->salePayments->count() : (int) $this->salePayments()->count());

        if ($paymentsCount > 0) {
            return 'This sale cannot be edited because it already has payment records.';
        }

        $deliveriesCount = array_key_exists('sale_deliveries_count', $this->attributes)
            ? (int) $this->attributes['sale_deliveries_count']
            : ($this->relationLoaded('saleDeliveries') ? $this->saleDeliveries->count() : (int) $this->saleDeliveries()->count());

        if ($deliveriesCount > 0) {
            return 'This sale cannot be edited because it already has related Sale Delivery records.';
        }

        return null;
    }

    public function isEditableInvoice(): bool
    {
        return $this->editLockReason() === null;
    }

    private function activeSaleDeliveries()
    {
        return $this->relationLoaded('saleDeliveries')
            ? $this->saleDeliveries
            : $this->saleDeliveries()->with('saleOrder')->get();
    }

    private function normalizeDeliveryStatus(?string $status): string
    {
        $status = strtolower(trim((string) ($status ?: 'pending')));

        if ($status === 'canceled') {
            return 'cancelled';
        }

        return $status !== '' ? $status : 'pending';
    }

    private function formatStatusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public function getDerivedDeliveryStatusLabelAttribute(): string
    {
        $deliveries = $this->activeSaleDeliveries();

        if ($deliveries->isEmpty()) {
            return 'No Delivery';
        }

        $statuses = $deliveries
            ->map(fn ($delivery) => $this->normalizeDeliveryStatus($delivery->status ?? 'pending'))
            ->unique()
            ->values();

        if ($statuses->count() > 1) {
            return 'Mixed';
        }

        return $this->formatStatusLabel((string) $statuses->first());
    }

    public function getDerivedDeliveryStatusClassAttribute(): string
    {
        $status = strtolower((string) $this->derived_delivery_status_label);

        if ($status === 'pending') {
            return 'badge-warning';
        }

        if (in_array($status, ['confirmed', 'completed', 'delivered'], true)) {
            return 'badge-success';
        }

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return 'badge-danger';
        }

        if ($status === 'mixed') {
            return 'badge-info';
        }

        return 'badge-secondary';
    }

    public function getDerivedSourceLabelAttribute(): string
    {
        $deliveries = $this->activeSaleDeliveries();

        if ($deliveries->isEmpty()) {
            return 'No Delivery';
        }

        $sources = $deliveries->map(function ($delivery) {
            $saleOrderId = (int) ($delivery->sale_order_id ?? 0);

            if ($saleOrderId > 0) {
                $reference = $delivery->saleOrder->reference ?? null;
                return $reference ?: ('SO-' . $saleOrderId);
            }

            return 'Walk-in';
        })->unique()->values();

        if ($sources->count() > 1) {
            return 'Mixed';
        }

        return (string) $sources->first();
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // kalau sudah ada reference dari luar, biarkan
            $ref = trim((string) ($model->reference ?? ''));

            // kalau kosong atau "AUTO" maka isi dummy dulu supaya DB tidak null
            if ($ref === '' || strtoupper($ref) === 'AUTO') {
                $model->reference = 'TMP-' . strtoupper(\Illuminate\Support\Str::random(10));
            }
        });

        static::created(function ($model) {
            // kalau sudah INV-... skip
            $ref = (string) ($model->reference ?? '');
            if (str_starts_with($ref, 'INV-')) return;

            $prefix = 'INV';

            // pakai helper kalau ada
            if (function_exists('make_reference_id')) {
                $newRef = make_reference_id($prefix, (int) $model->id);
            } else {
                // fallback aman
                $newRef = 'INV-' . str_pad((string) $model->id, 6, '0', STR_PAD_LEFT);
            }

            $model->reference = $newRef;
            $model->saveQuietly();
        });
    }

    public function branch() { return $this->belongsTo(\Modules\Branch\Entities\Branch::class); }
    public function rack() { return $this->belongsTo(\Modules\Inventory\Entities\Rack::class); }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeCompleted($query) {
        return $query->where('payment_status', 'Paid');
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
