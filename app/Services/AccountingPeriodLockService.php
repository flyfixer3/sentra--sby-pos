<?php

namespace App\Services;

use App\Models\AccountingPeriodLock;
use Carbon\CarbonInterface;
use Modules\Branch\Entities\Branch;

class AccountingPeriodLockService
{
    public static function resolveEntityId(?int $branchId, ?int $entityId = null): ?int
    {
        if ($entityId !== null) {
            return $entityId;
        }

        if ($branchId === null) {
            return null;
        }

        return Branch::query()->where('id', $branchId)->value('entity_id');
    }

    public static function isLocked(CarbonInterface $date, ?int $branchId = null, ?int $entityId = null): bool
    {
        $resolvedEntityId = self::resolveEntityId($branchId, $entityId);

        $locks = AccountingPeriodLock::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date->format('Y-m-d'))
            ->whereDate('end_date', '>=', $date->format('Y-m-d'))
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw('CASE WHEN entity_id IS NULL THEN 1 ELSE 0 END ASC')
            ->get();

        foreach ($locks as $lock) {
            $branchMatches = $lock->branch_id === null || (int) $lock->branch_id === (int) $branchId;
            $entityMatches = $lock->entity_id === null || (int) $lock->entity_id === (int) $resolvedEntityId;

            if ($branchMatches && $entityMatches) {
                return true;
            }
        }

        return false;
    }
}
