<?php

namespace Modules\Adjustment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Entities\Rack;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class AdjustmentRequestItem extends Model
{
    use HasFactory;

    protected $table = 'adjustment_request_items';

    protected $fillable = [
        'adjustment_id',
        'line_no',
        'product_id',
        'warehouse_id',
        'rack_id',
        'quantity',
        'condition_from',
        'condition_to',
        'payload',
    ];

    protected $casts = [
        'adjustment_id' => 'integer',
        'line_no' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'rack_id' => 'integer',
        'quantity' => 'integer',
        'payload' => 'array',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class, 'adjustment_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id')->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id', 'id');
    }
}
