<?php

namespace Modules\People\Entities;

use App\Traits\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{

    use HasFactory, LogsActivity, LogsModelChanges;

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

    public function vehicles()
    {
        return $this->hasMany(CustomerVehicle::class, 'customer_id');
    }

}
