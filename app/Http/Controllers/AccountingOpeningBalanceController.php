<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingTransactionCollection;
use App\Http\Resources\AccountingTransactionCollectionResource;
use App\Models\AccountingSubaccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingTransactionDetail;
use App\Services\AccountingPeriodLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Branch\Entities\Branch;

class AccountingOpeningBalanceController extends Controller
{
    public function index(Request $request)
    {
        $limit = max((int) $request->query('limit', 20), 1);
        $branchId = $request->query('branch_id');
        $entityId = $request->query('entity_id');

        $query = AccountingTransaction::query()
            ->with(['details.subaccount.account', 'branch', 'entity'])
            ->where('source_type', 'opening_balance')
            ->when($entityId, fn ($builder) => $builder->where('entity_id', (int) $entityId))
            ->when($branchId, fn ($builder) => $builder->where('branch_id', (int) $branchId))
            ->orderBy('date', 'desc');

        $paginator = $query->paginate($limit);

        return [
            'page' => new AccountingTransactionCollection($paginator),
            'meta' => [
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $entityId = $payload['entity_id'] ?? $this->resolveEntityIdFromBranch($payload['branch_id'] ?? null);

        if (AccountingPeriodLockService::isLocked(\Carbon\Carbon::parse($payload['date']), $payload['branch_id'] ?? null, $entityId)) {
            return response()->json([
                'message' => 'The selected accounting period is locked.',
            ], 422);
        }

        $exists = AccountingTransaction::query()
            ->where('source_type', 'opening_balance')
            ->whereDate('date', $payload['date'])
            ->when($entityId, fn ($builder) => $builder->where('entity_id', $entityId), fn ($builder) => $builder->whereNull('entity_id'))
            ->when(isset($payload['branch_id']) && $payload['branch_id'] !== null, fn ($builder) => $builder->where('branch_id', (int) $payload['branch_id']), fn ($builder) => $builder->whereNull('branch_id'))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Opening balance already exists for the selected scope and date.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($payload, $entityId) {
            $transaction = AccountingTransaction::create([
                'entity_id' => $entityId,
                'branch_id' => $payload['branch_id'] ?? null,
                'label' => $payload['label'],
                'date' => $payload['date'],
                'description' => $payload['description'] ?? null,
                'automated' => false,
                'source_type' => 'opening_balance',
                'source_id' => null,
                'status' => 'posted',
                'posted_at' => now(),
                'reversed_at' => null,
            ]);

            foreach ($payload['details'] as $detail) {
                $subaccount = AccountingSubaccount::query()->find($detail['subaccountId']);

                if ($subaccount === null) {
                    throw new \RuntimeException('Subaccount not found: ' . $detail['subaccountId']);
                }

                if ($detail['type'] === 'debit') {
                    $subaccount->total_debit += (float) $detail['amount'];
                } else {
                    $subaccount->total_credit += (float) $detail['amount'];
                }

                $subaccount->save();

                AccountingTransactionDetail::create([
                    'accounting_transaction_id' => $transaction->id,
                    'accounting_subaccount_id' => $detail['subaccountId'],
                    'amount' => $detail['amount'],
                    'type' => $detail['type'],
                ]);
            }

            return $transaction->load(['details.subaccount.account', 'branch', 'entity']);
        });

        return response()->json([
            'message' => 'Opening balance saved successfully',
            'data' => new AccountingTransactionCollectionResource($transaction),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'label' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:2'],
            'details.*.subaccountId' => ['required', 'integer', 'distinct'],
            'details.*.amount' => ['required', 'numeric', 'gt:0'],
            'details.*.type' => ['required', 'in:debit,credit'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $details = $request->input('details', []);
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($details as $detail) {
                $amount = (float) ($detail['amount'] ?? 0);

                if (($detail['type'] ?? null) === 'debit') {
                    $totalDebit += $amount;
                }

                if (($detail['type'] ?? null) === 'credit') {
                    $totalCredit += $amount;
                }
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add('details', 'Total debit and credit must be equal.');
            }
        });

        return $validator->validate();
    }

    private function resolveEntityIdFromBranch(?int $branchId): ?int
    {
        if ($branchId === null) {
            return null;
        }

        return Branch::query()->where('id', $branchId)->value('entity_id');
    }
}
