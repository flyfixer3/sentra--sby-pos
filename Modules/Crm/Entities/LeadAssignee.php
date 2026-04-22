<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignee extends BaseModel
{
    protected $table = 'crm_lead_assignees';
    protected $guarded = [];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class, 'lead_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
