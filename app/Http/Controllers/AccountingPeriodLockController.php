<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingPeriodLockCollection;
use App\Http\Resources\AccountingPeriodLockCollectionResource;
use App\Models\AccountingPeriodLock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Branch\Entities\Branch;

class AccountingPeriodLockController extends Controller
{
    public function locks(Request $request)
    {
        $limit = max((int) $request->query('limit', 100), 1);
        $entityId = $request->query('entity_id');
        $branchId = $request->query('branch_id');

        $query = AccountingPeriodLock::query()
            ->with(['entity', 'branch'])
            ->when($entityId, fn ($builder) => $builder->where('entity_id', (int) $entityId))
            ->when($branchId, fn ($builder) => $builder->where('branch_id', (int) $branchId))
            ->orderBy('start_date', 'desc');

        $paginator = $query->paginate($limit);

        return [
            'page' => new AccountingPeriodLockCollection($paginator),
            'meta' => [
                'total' => $paginator->total(),
            ],
        ];
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:accounting_period_locks,id'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (!empty($data['branch_id']) && empty($data['entity_id'])) {
            $data['entity_id'] = Branch::query()
                ->where('id', (int) $data['branch_id'])
                ->value('entity_id');
        }

        $overlapQuery = AccountingPeriodLock::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($data) {
                if (($data['entity_id'] ?? null) !== null) {
                    $builder->where('entity_id', $data['entity_id']);
                } else {
                    $builder->whereNull('entity_id');
                }

                if (($data['branch_id'] ?? null) !== null) {
                    $builder->where('branch_id', $data['branch_id']);
                } else {
                    $builder->whereNull('branch_id');
                }
            })
            ->where(function ($builder) use ($data) {
                $builder
                    ->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                    ->orWhere(function ($subQuery) use ($data) {
                        $subQuery
                            ->where('start_date', '<=', $data['start_date'])
                            ->where('end_date', '>=', $data['end_date']);
                    });
            });

        if (!empty($data['id'])) {
            $overlapQuery->where('id', '!=', $data['id']);
        }

        if ($overlapQuery->exists()) {
            return response()->json([
                'message' => 'A period lock already overlaps with the selected scope and date range.',
            ], 422);
        }

        $lock = AccountingPeriodLock::query()->updateOrCreate(
            ['id' => $data['id'] ?? null],
            $data
        );

        return response()->json([
            'message' => 'Period lock saved successfully',
            'data' => new AccountingPeriodLockCollectionResource($lock->load(['entity', 'branch'])),
        ]);
    }
}
