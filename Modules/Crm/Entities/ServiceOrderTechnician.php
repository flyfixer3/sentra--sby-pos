<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderTechnician extends BaseModel
{
    protected $table = 'crm_service_order_technicians';
    protected $guarded = [];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function serviceOrder(): BelongsTo { return $this->belongsTo(ServiceOrder::class, 'service_order_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}