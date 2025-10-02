<?php

namespace Modules\Transfer\Entities;

use App\Models\BaseModel;
use App\Models\User;

class PrintLog extends BaseModel
{
    protected $fillable = [
        'user_id',
        'transfer_request_id',
        'printed_at',
        'ip_address',
    ];
    protected $dates = ['printed_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transfer()
    {
        return $this->belongsTo(TransferRequest::class, 'transfer_request_id');
    }
}
