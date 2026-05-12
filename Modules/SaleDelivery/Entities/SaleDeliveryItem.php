<?php

namespace Modules\SaleDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Sale\Entities\SaleDetails;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleDeliveryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_delivery_id',
        'warehouse_id',
        'product_id',
        'sale_order_item_id',
        'sale_item_id',
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
        'sale_order_item_id' => 'int',
        'sale_item_id' => 'int',
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
        return $this->belongsTo(Product::class, 'product_id')->withoutGlobalScopes();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleDetails::class, 'sale_item_id');
    }
}
