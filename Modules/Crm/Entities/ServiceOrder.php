<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ServiceOrder extends BaseModel implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $table = 'crm_service_orders';
    protected $guarded = [];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class, 'lead_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(\Modules\People\Entities\Customer::class, 'customer_id'); }

    public function technicians(): HasMany { return $this->hasMany(ServiceOrderTechnician::class, 'service_order_id'); }
    public function photos(): HasMany { return $this->hasMany(ServiceOrderPhoto::class, 'service_order_id'); }
    public function warranty(): HasOne { return $this->hasOne(Warranty::class, 'service_order_id'); }
}