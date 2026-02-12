<?php

namespace Modules\SaleDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class SaleDeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_delivery_id',
        'warehouse_id',
        'product_id',
        'quantity',

        'qty_good',
        'qty_defect',
        'qty_damaged',

        'price',
    ];

    protected $casts = [
        'sale_delivery_id' => 'int',
        'warehouse_id' => 'int',
        'product_id' => 'int',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
