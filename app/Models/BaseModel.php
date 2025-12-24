<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\HasBranchScope;
use Illuminate\Support\Facades\Schema;

class BaseModel extends Model
{
    use LogsActivity, HasBranchScope;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $table = $model->getTable();
                if (Schema::hasColumn($table, 'created_by')) {
                    $model->created_by = auth()->id();
                }
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $table = $model->getTable();
                if (Schema::hasColumn($table, 'updated_by')) {
                    $model->updated_by = auth()->id();
                }
            }
        });

        static::deleting(function ($model) {
            activity()
                ->performedOn($model)
                ->causedBy(auth()->user())
                ->withProperties(['id' => $model->id])
                ->log('Deleted record from ' . $model->getTable());
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * ✅ Log only key attributes, excluding `created_by`, `updated_by`, timestamps
     */
    public function getActivitylogOptions(): LogOptions
    {
        $excludeFields = ['created_by', 'updated_by', 'created_at', 'updated_at'];

        return LogOptions::defaults()
            ->logOnly(array_diff(array_keys($this->getAttributes()), $excludeFields)) // ✅ Exclude system fields
            ->logOnlyDirty() // ✅ Log only changed values
            ->dontSubmitEmptyLogs() // ✅ Prevent empty logs
            ->setDescriptionForEvent(fn(string $eventName) => "{$eventName} record with ID: " . $this->id);
    }
}
