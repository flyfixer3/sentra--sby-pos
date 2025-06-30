<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends Model
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
