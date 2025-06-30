<?php

namespace Modules\Transfer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransferRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['transfer_request_id', 'product_id', 'quantity'];

    public function transferRequest() {
        return $this->belongsTo(TransferRequest::class);
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory()
    {
        return \Modules\Transfer\Database\factories\TransferRequestItemFactory::new();
    }
}
