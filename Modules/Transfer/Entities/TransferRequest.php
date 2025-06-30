<?php

namespace Modules\Transfer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'date', 'from_warehouse_id', 'to_warehouse_id',
        'note', 'status', 'confirmed_by', 'confirmed_at', 'branch_id', 'created_by'
    ];

    public function items() {
        return $this->hasMany(TransferRequestItem::class);
    }

    public function fromWarehouse() {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse() {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer() {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
