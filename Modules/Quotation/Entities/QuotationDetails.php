<?php

namespace Modules\Quotation\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\People\Entities\CustomerVehicle;
use Modules\Product\Entities\Product;

class QuotationDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $with = ['product'];

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function quotation() {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'id');
    }

    public function customerVehicle() {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id', 'id');
    }

    public function getPriceAttribute($value) {
        return $value / 1;
    }

    public function getUnitPriceAttribute($value) {
        return $value / 1;
    }

    public function getSubTotalAttribute($value) {
        return $value / 1;
    }

    public function getProductDiscountAmountAttribute($value) {
        return $value / 1;
    }

    public function getProductTaxAmountAttribute($value) {
        return $value / 1;
    }}
