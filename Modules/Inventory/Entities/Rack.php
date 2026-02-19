<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Entities\Warehouse;
use Modules\Branch\Entities\Branch;

class Rack extends BaseModel
{
    use HasFactory;

    protected $table = 'racks';

    protected $fillable = [
        'warehouse_id',
        'branch_id',
        'code',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function stockRacks(): HasMany
    {
        return $this->hasMany(StockRack::class, 'rack_id');
    }
}
