<?php

namespace Modules\Mutation\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class Mutation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $with = ['product','warehouse'];

    public function warehouse() {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // public static function boot() {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         $number = Mutation::max('id') + 1;
    //         $model->reference = make_reference_id('MT', $number);
    //     });
    // }
}
