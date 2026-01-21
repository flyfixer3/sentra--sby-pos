<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{

    use HasFactory;

    protected $guarded = [];

    protected static function newFactory() {
        return \Modules\People\Database\factories\CustomerFactory::new();
    }

    public function scopeForActiveBranch($q, int $branchId)
    {
        return $q->where(function($qq) use ($branchId){
            $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
        });
    }

}
