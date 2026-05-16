<?php

namespace Modules\SaleOrder\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\People\Entities\CustomerVehicle;
use Modules\Product\Entities\Product;

class SaleOrderItem extends BaseModel
{
    use SoftDeletes;
    protected $table = 'sale_order_items';

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'sellable_stock_at_order',
        'shortage_quantity',
        'unit_price',
        'price',
        'product_discount_amount',
        'product_discount_type',
        'sub_total',
        'installation_type',
        'customer_vehicle_id',
    ];

    protected $casts = [
        'sellable_stock_at_order' => 'integer',
        'shortage_quantity' => 'integer',
    ];

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withoutGlobalScopes();
    }

    public function customerVehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }
}
