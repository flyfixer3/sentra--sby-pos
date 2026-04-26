<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warranty extends BaseModel
{
    protected $table = 'crm_warranties';
    protected $guarded = [];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function serviceOrder(): BelongsTo { return $this->belongsTo(ServiceOrder::class, 'service_order_id'); }
}