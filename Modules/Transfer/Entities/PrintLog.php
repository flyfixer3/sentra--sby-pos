<?php

namespace Modules\Transfer\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PrintLog extends Model
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
