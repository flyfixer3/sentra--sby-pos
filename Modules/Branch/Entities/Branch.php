<?php

namespace Modules\Branch\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Models\BaseModel;

class Branch extends BaseModel
{
    protected $fillable = ['name', 'address', 'phone', 'is_active'];

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'branch_user');
    }

    public function warehouses()
    {
        return $this->hasMany(\Modules\Product\Entities\ProductWarehouse::class);
    }

}
