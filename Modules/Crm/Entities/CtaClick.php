<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CtaClick extends Model
{
    protected $table = 'crm_cta_clicks';

    protected $guarded = [];

    protected $casts = [
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'click_count' => 'integer',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
