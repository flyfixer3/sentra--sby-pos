<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmUserAccessOverride extends Model
{
    protected $table = 'crm_user_access_overrides';

    protected $fillable = ['user_id', 'blocked', 'updated_by'];

    protected $casts = ['blocked' => 'boolean'];
}
