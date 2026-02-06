<?php

namespace Modules\Transfer\Entities;

use Illuminate\Database\Eloquent\Model;

class TransferReceiveAllocation extends Model
{
    protected $table = 'transfer_receive_allocations';

    protected $fillable = [
        'transfer_request_id',
        'transfer_request_item_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'rack_id',
        'qty_good',
        'qty_defect',
        'qty_damaged',
        'created_by',
    ];
}
