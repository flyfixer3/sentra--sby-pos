<?php

namespace Modules\PurchaseDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Inventory\Entities\Rack;
use Modules\Product\Entities\Product;

class PurchaseDeliveryDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_delivery_details';

    protected $fillable = [
        'purchase_delivery_id',
        'product_id',
        'product_name',
        'product_code',
        'rack_id',          // ✅ NEW
        'quantity',
        'qty_received',
        'qty_defect',
        'qty_damaged',
        'unit_price',
        'sub_total',
    ];

    protected $casts = [
        'purchase_delivery_id' => 'integer',
        'product_id'           => 'integer',
        'rack_id'              => 'integer',   // ✅ NEW
        'quantity'             => 'integer',
        'qty_received'         => 'integer',
        'qty_defect'           => 'integer',
        'qty_damaged'          => 'integer',
        'unit_price'           => 'decimal:2',
        'sub_total'            => 'decimal:2',
    ];

    public function purchaseDelivery()
    {
        return $this->belongsTo(PurchaseDelivery::class, 'purchase_delivery_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withoutGlobalScopes();
    }

    // ✅ NEW
    public function rack()
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }
}
