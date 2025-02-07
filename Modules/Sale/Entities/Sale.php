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

    public static function boot() {
        parent::boot();

        static::creating(function ($model) {
            // dd($model);
            $number = Sale::count()+1;
            $model->reference = make_sale_id('B', $number, $model);
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'Completed');
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
