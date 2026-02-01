<?php

namespace Modules\SaleDelivery\Entities;

use Illuminate\Database\Eloquent\Model;

class SaleDeliveryPrintLog extends Model
{
    protected $table = 'sale_delivery_print_logs';

    protected $fillable = [
        'sale_delivery_id',
        'user_id',
        'printed_at',
        'ip_address',
    ];

    public function saleDelivery()
    {
        return $this->belongsTo(SaleDelivery::class, 'sale_delivery_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
        // kalau user model kamu bukan App\Models\User, ganti sesuai project kamu
    }
}
