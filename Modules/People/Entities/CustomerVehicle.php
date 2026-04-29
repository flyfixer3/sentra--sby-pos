<?php

namespace Modules\People\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Quotation\Entities\QuotationDetails;
use Modules\Sale\Entities\SaleDetails;
use Modules\SaleOrder\Entities\SaleOrderItem;

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

    public function saleOrderItems()
    {
        return $this->hasMany(SaleOrderItem::class, 'customer_vehicle_id');
    }

    public function quotationDetails()
    {
        return $this->hasMany(QuotationDetails::class, 'customer_vehicle_id');
    }
}
