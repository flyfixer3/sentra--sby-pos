<?php

namespace Modules\Transfer\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;

class TransferRequestItem extends BaseModel
{
    use HasFactory;

    protected $fillable = ['transfer_request_id', 'product_id', 'quantity'];

    // ✅ relasi ke header TransferRequest
    public function transferRequest()
    {
        return $this->belongsTo(TransferRequest::class);
    }

    // ✅ relasi ke produk
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory()
    {
        return \Modules\Transfer\Database\factories\TransferRequestItemFactory::new();
    }
}
