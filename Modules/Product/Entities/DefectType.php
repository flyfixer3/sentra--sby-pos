<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;

class DefectType extends Model
{
    protected $table = 'defect_types';

    protected $fillable = [
        'name',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];
}
