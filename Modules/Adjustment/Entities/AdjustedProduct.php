<?php

namespace Modules\Adjustment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use Modules\Inventory\Entities\Rack;
use Illuminate\Database\Eloquent\Model;

class AdjustedProduct extends Model
{
    use HasFactory;

    protected $table = 'adjusted_products';

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'warehouse_id',
        'rack_id',
        'quantity',
        'type',
        'note',
    ];

    protected $guarded = [];

    protected $with = ['product', 'rack'];

    public function adjustment()
    {
        return $this->belongsTo(Adjustment::class, 'adjustment_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }
}
