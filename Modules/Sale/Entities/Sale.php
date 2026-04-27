<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Models\User;
use App\Support\LegacyImport\ReferenceCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $ref = trim((string) ($model->reference ?? ''));
            if ($ref === '' || strtoupper($ref) === 'AUTO') {
                $model->reference = ReferenceCodeGenerator::generateSaleReference(
                    optional($model->branch)->name,
                    $model->date ?? now()
                );
            }
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
