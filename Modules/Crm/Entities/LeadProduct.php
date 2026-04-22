<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProduct extends BaseModel
{
    protected $table = 'crm_lead_products';
    protected $guarded = [];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Product::class, 'product_id');
    }
}
