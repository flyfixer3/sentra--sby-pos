<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;

trait LogsModelChanges
{
    public function getActivitylogOptions(): LogOptions
    {
        $excludeFields = ['created_by', 'updated_by', 'created_at', 'updated_at'];

        return LogOptions::defaults()
            ->logOnly(array_diff(array_keys($this->getAttributes()), $excludeFields))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "{$eventName} record with ID: " . $this->id);
    }
}
