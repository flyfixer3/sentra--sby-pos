<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Branch\Entities\Branch;

class Entity extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'entity_id');
    }
}
