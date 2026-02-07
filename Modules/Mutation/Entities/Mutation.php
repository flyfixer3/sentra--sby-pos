<?php

namespace Modules\Mutation\Entities;

use App\Models\BaseModel;
use App\Traits\HasBranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Inventory\Entities\Rack;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class Mutation extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $with = ['product','warehouse','rack'];

    public function warehouse() {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function rack() {
        return $this->belongsTo(Rack::class, 'rack_id', 'id');
    }

    // public static function boot() {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         $number = Mutation::max('id') + 1;
    //         $model->reference = make_reference_id('MT', $number);
    //     });
    // }
}
