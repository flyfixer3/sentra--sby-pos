<?php

namespace Modules\Inventory\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Entities\Product;

class RackMovementItem extends BaseModel
{
    protected $table = 'rack_movement_items';

    protected $fillable = [
        'rack_movement_id',
        'product_id',
        'condition',
        'quantity',
        'created_by',
        'updated_by',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(RackMovement::class, 'rack_movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}