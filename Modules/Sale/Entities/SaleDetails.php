<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use Modules\Warehouse\Entities\Warehouse;

class SaleDetails extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['product'];

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function warehouse() {
        return $this->belongsTo(Warehouse::class, 'product_id', 'id');
    }

    public function sale() {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
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
    }
}
