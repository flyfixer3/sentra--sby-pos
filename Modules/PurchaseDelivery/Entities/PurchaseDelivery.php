<?php

namespace Modules\PurchaseDelivery\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;

class PurchaseDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'branch_id',       // ✅ kalau kolomnya ada
        'warehouse_id',    // ✅ wajib
        'date',
        'tracking_number',
        'ship_via',
        'status',
        'note',
        'created_by',      // ✅ kalau kolomnya ada
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(\Modules\PurchaseOrder\Entities\PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseDeliveryDetails()
    {
        return $this->hasMany(PurchaseDeliveryDetails::class, 'purchase_delivery_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
