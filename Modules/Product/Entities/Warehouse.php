<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class Warehouse extends BaseModel
{
    use HasFactory;

    protected $guarded = [];
    protected $fillable = [
        'warehouse_code',
        'warehouse_name',
        'branch_id',
        'is_main',
    ];


    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class);
    }    
}
