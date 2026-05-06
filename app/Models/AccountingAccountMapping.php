<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Entities\Branch;

class AccountingAccountMapping extends BaseModel
{
    protected $fillable = [
        'entity_id',
        'branch_id',
        'module',
        'event',
        'label',
        'description',
        'accounting_subaccount_id',
        'is_active',
    ];

    protected $casts = [
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

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(AccountingSubaccount::class, 'accounting_subaccount_id');
    }
}
