<?php

namespace Modules\Adjustment\Entities;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Adjustment extends BaseModel
{
    use HasFactory;

    /**
     * Catatan:
     * - Tabel adjustments sudah punya kolom created_by (lihat phpMyAdmin).
     * - Jadi kita tinggal expose di model + relasi creator().
     */
    protected $fillable = [
        'reference',
        'date',
        'note',
        'branch_id',
        'warehouse_id',
        'created_by',
        'created_at',
        'updated_at',
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

    /**
     * Creator (audit trail)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /**
     * WARNING:
     * Kamu sebelumnya format date di accessor jadi string.
     * Aku biarkan supaya tidak merusak flow UI existing kamu.
     */
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
