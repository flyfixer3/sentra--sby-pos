<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingAccountMappingCollection;
use App\Http\Resources\AccountingAccountMappingCollectionResource;
use App\Models\AccountingAccountMapping;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingAccountMappingController extends Controller
{
    public function mappings(Request $request)
    {
        $limit = max((int) $request->query('limit', 100), 1);
        $search = trim((string) $request->query('search', ''));
        $entityId = $request->query('entity_id');
        $branchId = $request->query('branch_id');
        $module = trim((string) $request->query('module', ''));
        $isActive = $request->query('is_active');

        $query = AccountingAccountMapping::query()
            ->with(['entity', 'branch', 'subaccount.account'])
            ->when($entityId, function ($builder) use ($entityId) {
                $builder->where(function ($scopeQuery) use ($entityId) {
                    $scopeQuery
                        ->whereNull('entity_id')
                        ->orWhere('entity_id', (int) $entityId);
                });
            })
            ->when($branchId, function ($builder) use ($branchId) {
                $builder->where(function ($scopeQuery) use ($branchId) {
                    $scopeQuery
                        ->whereNull('branch_id')
                        ->orWhere('branch_id', (int) $branchId);
                });
            })
            ->when($module !== '', fn ($builder) => $builder->where('module', $module))
            ->when($isActive !== null && $isActive !== '', fn ($builder) => $builder->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('label', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('module', 'like', '%' . $search . '%')
                        ->orWhere('event', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw('CASE WHEN entity_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('module')
            ->orderBy('event');

        $paginator = $query->paginate($limit);

        return [
            'page' => new AccountingAccountMappingCollection($paginator),
            'meta' => [
                'total' => $paginator->total(),
            ],
        ];
    }

    public function mapping($id)
    {
        $mapping = AccountingAccountMapping::query()
            ->with(['entity', 'branch', 'subaccount.account'])
            ->find($id);

        if ($mapping === null) {
            return response()->json(['message' => 'Mapping not found'], 404);
        }

        return [
            'data' => new AccountingAccountMappingCollectionResource($mapping),
        ];
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:accounting_account_mappings,id'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'module' => ['required', 'string', 'max:100'],
            'event' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'accounting_subaccount_id' => ['required', 'integer', 'exists:accounting_subaccounts,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (!empty($data['branch_id']) && empty($data['entity_id'])) {
            $data['entity_id'] = \Modules\Branch\Entities\Branch::query()
                ->where('id', (int) $data['branch_id'])
                ->value('entity_id');
        }

        $scopeRule = Rule::unique('accounting_account_mappings')
            ->where(function ($query) use ($data) {
                $query
                    ->where('module', $data['module'])
                    ->where('event', $data['event']);

                if (array_key_exists('entity_id', $data) && $data['entity_id'] !== null) {
                    $query->where('entity_id', $data['entity_id']);
                } else {
                    $query->whereNull('entity_id');
                }

                if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null) {
                    $query->where('branch_id', $data['branch_id']);
                } else {
                    $query->whereNull('branch_id');
                }
            });

        if (!empty($data['id'])) {
            $scopeRule->ignore($data['id']);
        }

        validator($data, [
            'module' => [$scopeRule],
        ], [
            'module.unique' => 'Mapping for the selected scope already exists.',
        ])->validate();

        $mapping = AccountingAccountMapping::query()->updateOrCreate(
            ['id' => $data['id'] ?? null],
            $data
        );

        return response()->json([
            'message' => 'Mapping saved successfully',
            'data' => new AccountingAccountMappingCollectionResource(
                $mapping->load(['entity', 'branch', 'subaccount.account'])
            ),
        ]);
    }
}
