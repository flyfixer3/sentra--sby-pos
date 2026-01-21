<?php

namespace Modules\SaleDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleDeliveryItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function delivery()
    {
        return $this->belongsTo(SaleDelivery::class, 'sale_delivery_id');
    }
}
