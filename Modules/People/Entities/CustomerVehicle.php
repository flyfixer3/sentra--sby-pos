<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sale\Entities\SaleDetails;

class CustomerVehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $dates = ['deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetails::class, 'customer_vehicle_id');
    }
}
