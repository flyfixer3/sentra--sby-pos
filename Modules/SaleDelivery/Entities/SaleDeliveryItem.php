<?php

namespace Modules\SaleDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;

class SaleDeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_delivery_id',
        'product_id',
        'quantity',

        'qty_good',
        'qty_defect',
        'qty_damaged',

        'price',
    ];

    protected $casts = [
        'quantity' => 'int',
        'qty_good' => 'int',
        'qty_defect' => 'int',
        'qty_damaged' => 'int',
        'price' => 'int',
    ];

    public function saleDelivery()
    {
        return $this->belongsTo(SaleDelivery::class, 'sale_delivery_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
