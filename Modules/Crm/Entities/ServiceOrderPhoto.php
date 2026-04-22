<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ServiceOrderPhoto extends BaseModel
{
    protected $table = 'crm_service_order_photos';
    protected $guarded = [];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function serviceOrder(): BelongsTo { return $this->belongsTo(ServiceOrder::class, 'service_order_id'); }
    public function media(): BelongsTo { return $this->belongsTo(Media::class, 'media_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'uploaded_by'); }
}