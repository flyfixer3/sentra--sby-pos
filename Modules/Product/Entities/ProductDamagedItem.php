<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Mutation\Entities\Mutation;

class ProductDamagedItem extends Model
{
    use HasFactory;

    protected $table = 'product_damaged_items';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'rack_id',              // ✅ FIX: allow mass-assign
        'product_id',
        'reference_id',
        'reference_type',
        'quantity',

        // new
        'damage_type',          // damaged | missing
        'cause',                // transfer | employee | supplier | unknown
        'responsible_user_id',
        'resolution_status',    // pending | resolved | compensated | waived
        'resolution_note',

        // existing
        'reason',
        'photo_path',
        'mutation_in_id',
        'mutation_out_id',
        'created_by',
    ];

    protected $casts = [
        'branch_id'            => 'integer',
        'warehouse_id'         => 'integer',
        'rack_id'              => 'integer', // ✅ FIX
        'product_id'           => 'integer',
        'reference_id'         => 'integer',
        'quantity'             => 'integer',
        'mutation_in_id'       => 'integer',
        'mutation_out_id'      => 'integer',
        'created_by'           => 'integer',
        'responsible_user_id'  => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function mutationIn()
    {
        return $this->belongsTo(Mutation::class, 'mutation_in_id');
    }

    public function mutationOut()
    {
        return $this->belongsTo(Mutation::class, 'mutation_out_id');
    }

    public function creator()
    {
        $userModel = config('auth.providers.users.model');
        return $this->belongsTo($userModel, 'created_by');
    }

    public function responsibleUser()
    {
        $userModel = config('auth.providers.users.model');
        return $this->belongsTo($userModel, 'responsible_user_id');
    }

    public function scopeAvailable($q)
    {
        return $q->whereNull('moved_out_at')
                ->where('resolution_status', 'pending');
    }
}
