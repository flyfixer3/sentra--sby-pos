<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;

class StockRack extends BaseModel
{

    protected $table = 'stock_racks';

    protected $fillable = [
        'product_id',
        'rack_id',
        'warehouse_id',
        'branch_id',
        'qty_available',
        'qty_good',
        'qty_defect',
        'qty_damaged',
        'created_by',
        'updated_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Product::class, 'product_id');
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
