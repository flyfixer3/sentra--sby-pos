<?php

namespace Modules\Branch\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Models\BaseModel;

class Branch extends BaseModel
{
    protected $fillable = ['entity_id', 'name', 'address', 'phone', 'is_active'];

    public function entity()
    {
        return $this->belongsTo(\App\Models\Entity::class, 'entity_id');
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'branch_user');
    }

    public function warehouses()
    {
        return $this->hasMany(\Modules\Product\Entities\Warehouse::class, 'branch_id');
    }

}
