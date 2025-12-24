<?php

namespace Modules\Inventory\Entities;

use App\Models\BaseModel;
use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends BaseModel
{
    use HasBranchScope;

    protected $table = 'stocks';

    protected $fillable = [
        'product_id',
        'branch_id',
        'warehouse_id',
        'qty_available',
        'qty_reserved',
        'qty_incoming',
        'min_stock',
        'note',
        'created_by',
        'updated_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Product::class, 'product_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id');
    }


    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Warehouse::class, 'warehouse_id');
    }

}
