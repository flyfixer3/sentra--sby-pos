<?php

namespace Modules\PurchaseDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseDeliveryDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_delivery_id',
        'product_id',
        'product_name',
        'product_code',
        'quantity',
        'unit_price',
        'sub_total'
    ];

    public function purchaseDelivery()
    {
        return $this->belongsTo(PurchaseDelivery::class);
    }
}
