<?php

namespace Modules\Transfer\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use Modules\Inventory\Entities\Rack;

class TransferRequestItem extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'transfer_request_id',
        'product_id',
        'from_rack_id', // ✅ NEW
        'condition',
        'to_rack_id',   // ✅ NEW
        'quantity',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(TransferRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function fromRack()
    {
        return $this->belongsTo(Rack::class, 'from_rack_id')->withoutGlobalScopes();
    }

    public function toRack()
    {
        return $this->belongsTo(Rack::class, 'to_rack_id')->withoutGlobalScopes();
    }

    protected static function newFactory()
    {
        return \Modules\Transfer\Database\factories\TransferRequestItemFactory::new();
    }
}
