<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function saleDetails() {
        return $this->hasMany(SaleDetails::class, 'sale_id', 'id');
    }

    public function salePayments() {
        return $this->hasMany(SalePayment::class, 'sale_id', 'id');
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
