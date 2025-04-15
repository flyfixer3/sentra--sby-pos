<?php

namespace Modules\PurchaseDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'date',
        'status',
        'note',
        'tracking_number',
        'ship_via'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(\Modules\PurchaseOrder\Entities\PurchaseOrder::class);
    }

    public function purchaseDeliveryDetails()
    {
        return $this->hasMany(PurchaseDeliveryDetails::class);
    }
}
