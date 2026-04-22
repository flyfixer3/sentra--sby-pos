<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadComment extends BaseModel
{
    protected $table = 'crm_lead_comments';
    protected $guarded = [];
    protected $casts = [
        'mentions' => 'array',
    ];

    public function setMentionsAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['mentions'] = null;
            return;
        }

        $items = is_array($value) ? array_values($value) : [$value];
        $this->attributes['mentions'] = json_encode($items);
    }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class, 'lead_id'); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
}
