<?php

namespace Modules\Transfer\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Modules\Product\Entities\Warehouse;
use Modules\Branch\Entities\Branch;

class TransferRequest extends BaseModel
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

        // NEW
        'delivery_code',

        'delivery_proof_path',
        'confirmed_by',
        'confirmed_at',
        'printed_at',
        'printed_by',

        // NEW cancel
        'cancelled_by',
        'cancelled_at',
        'cancel_note',
    ];

    protected $dates = ['date', 'printed_at', 'confirmed_at', 'cancelled_at'];

    public function printLogs()
    {
        return $this->hasMany(PrintLog::class);
    }

    protected static function booted()
    {
        static::created(function ($model) {
            if (empty($model->reference)) {
                $model->reference = make_reference_id('TRF', $model->id);
                $model->saveQuietly();
            }
        });
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withoutGlobalScopes();
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
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withoutGlobalScopes();
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
