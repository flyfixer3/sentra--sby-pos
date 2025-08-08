<?php

namespace Modules\Transfer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Modules\Product\Entities\Warehouse;
use Modules\Branch\Entities\Branch;

class TransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'date',
        'from_warehouse_id',
        'to_branch_id',
        'to_warehouse_id',
        'note',
        'status',
        'branch_id',
        'created_by',
        'delivery_proof_path',
        'confirmed_by',
        'confirmed_at',
        'printed_at',
        'printed_by',
    ];

    protected $dates = ['date', 'printed_at', 'confirmed_at'];

    public function printLogs()
    {
        return $this->hasMany(PrintLog::class);
    }


    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(TransferRequestItem::class);
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
