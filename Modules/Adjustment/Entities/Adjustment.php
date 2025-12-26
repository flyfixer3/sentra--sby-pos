<?php

namespace Modules\Adjustment\Entities;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Adjustment extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'reference',
        'date',
        'note',
        'status',
        'branch_id',
        'warehouse_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Warehouse::class, 'warehouse_id');
    }

    public function adjustedProducts(): HasMany
    {
        return $this->hasMany(AdjustedProduct::class, 'adjustment_id', 'id');
    }

    public function getDateAttribute($value)
    {
        return Carbon::parse($value)->format('d M, Y');
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $number = Adjustment::max('id') + 1;
            $model->reference = make_reference_id('ADJ', $number);
        });
    }
}
