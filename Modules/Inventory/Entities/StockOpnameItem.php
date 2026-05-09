<?php

namespace Modules\Inventory\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Entities\Product;

class StockOpnameItem extends Model
{
    protected $casts = [
        'counted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'rack_id',
        'product_code_snapshot',
        'product_name_snapshot',
        'rack_code_snapshot',
        'rack_name_snapshot',
        'system_qty',
        'physical_qty',
        'diff_qty',
        'review_status',
        'resolution_type',
        'resolution_reference',
        'resolution_note',
        'resolved_at',
        'resolved_by',
        'note',
        'counted_at',
    ];

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withoutGlobalScopes();
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }
}
