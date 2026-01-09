<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductDefectItem extends Model
{
    use HasFactory;

    protected $table = 'product_defect_items';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'product_id',
        'reference_id',
        'reference_type',
        'quantity',
        'defect_type',
        'description',
        'photo_path',
        'created_by',
    ];

    protected $casts = [
        'branch_id'    => 'integer',
        'warehouse_id' => 'integer',
        'product_id'   => 'integer',
        'reference_id' => 'integer',
        'quantity'     => 'integer',
        'created_by'   => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Relasi user dibuat generic biar nggak asumsi namespace User kamu.
     * Akan pakai model user dari config auth.
     */
    public function creator()
    {
        $userModel = config('auth.providers.users.model');
        return $this->belongsTo($userModel, 'created_by');
    }

    public function scopeAvailable($q)
    {
        return $q->whereNull('moved_out_at');
    }

}
