<?php

namespace Modules\Expense\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // reference auto: EXP-00001 dst
            if (empty($model->reference)) {
                $number = (static::withoutGlobalScopes()->max('id') ?? 0) + 1;
                $model->reference = make_reference_id('EXP', $number);
            }

            // default type
            if (empty($model->type)) {
                $model->type = 'credit';
            }
        });
    }
}