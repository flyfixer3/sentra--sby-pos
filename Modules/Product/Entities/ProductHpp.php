<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;

class ProductHpp extends Model
{
    use HasFactory;

    protected $table = 'product_hpps';

    protected $fillable = [
        'branch_id',
        'product_id',

        // ledger key
        'effective_at',
        'source_type',
        'source_id',

        // snapshot values (legacy columns tetap dipakai)
        'avg_cost',
        'last_purchase_cost',

        // metadata perhitungan
        'incoming_qty',
        'incoming_unit_cost',
        'old_qty',
        'old_avg_cost',
        'new_avg_cost',
    ];

    protected $casts = [
        'branch_id'          => 'integer',
        'product_id'         => 'integer',

        'effective_at'       => 'datetime',

        'source_id'          => 'integer',

        'avg_cost'           => 'decimal:2',
        'last_purchase_cost' => 'decimal:2',

        'incoming_qty'       => 'integer',
        'incoming_unit_cost' => 'decimal:2',
        'old_qty'            => 'integer',
        'old_avg_cost'       => 'decimal:2',
        'new_avg_cost'       => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}