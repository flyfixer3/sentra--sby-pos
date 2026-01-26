<?php

namespace Modules\SaleOrder\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Entities\Product;

class SaleOrderItem extends BaseModel
{
    protected $table = 'sale_order_items';

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'price',
    ];

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
