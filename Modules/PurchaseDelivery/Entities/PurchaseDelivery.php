<?php

namespace Modules\PurchaseDelivery\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;
use Modules\Purchase\Entities\Purchase;

class PurchaseDelivery extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'branch_id',
        'warehouse_id',
        'date',
        'tracking_number',
        'ship_via',
        'status',

        // note create/edit
        'note',
        'note_updated_by',
        'note_updated_role',
        'note_updated_at',

        // note confirm
        'confirm_note',
        'confirm_note_updated_by',
        'confirm_note_updated_role',
        'confirm_note_updated_at',

        'created_by',
    ];

    protected $casts = [
        'note_updated_at' => 'datetime',
        'confirm_note_updated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function noteUpdater()
    {
        return $this->belongsTo(\App\Models\User::class, 'note_updated_by');
    }

    public function confirmNoteUpdater()
    {
        return $this->belongsTo(\App\Models\User::class, 'confirm_note_updated_by');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(\Modules\PurchaseOrder\Entities\PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchase()
    {
        // purchases punya kolom purchase_delivery_id
        return $this->hasOne(Purchase::class, 'purchase_delivery_id', 'id');
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
