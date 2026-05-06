<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Entities\Branch;

class AccountingPeriodLock extends BaseModel
{
    protected $fillable = [
        'entity_id',
        'branch_id',
        'start_date',
        'end_date',
        'label',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
