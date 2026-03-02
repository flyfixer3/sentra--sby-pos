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
        'avg_cost',
        'last_purchase_cost',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'product_id' => 'integer',
        'avg_cost' => 'decimal:2',
        'last_purchase_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}