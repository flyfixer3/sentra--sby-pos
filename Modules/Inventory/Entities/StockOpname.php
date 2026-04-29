<?php

namespace Modules\Inventory\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;

class StockOpname extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'reference',
        'opname_date',
        'title',
        'status',
        'note',
        'generated_at',
        'imported_at',
        'reviewed_at',
        'reviewed_by',
        'finalized_at',
        'adjustment_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'imported_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Adjustment::class, 'adjustment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }
}
