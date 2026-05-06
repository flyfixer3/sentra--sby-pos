<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingTransactionCollection;
use App\Http\Resources\AccountingTransactionCollectionResource;
use App\Models\AccountingSubaccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingTransactionDetail;
use App\Services\AccountingPeriodLockService;
use Modules\Branch\Entities\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountingTransactionController extends Controller
{
    public function allTransactions(Request $request)
    {
        $transactions = $this->buildTransactionQuery($request)
            ->orderBy('date', 'asc')
            ->get();

        return [
            'data' => new AccountingTransactionCollection($transactions),
        ];
    }

    public function transactions(Request $request)
    {
        $limit = max((int) $request->query('limit', 20), 1);
        $query = $this->buildTransactionQuery($request)->orderBy('date', 'desc');
        $paginator = $query->paginate($limit);

        return [
            'page' => new AccountingTransactionCollection($paginator),
            'meta' => [
                'total' => $paginator->total(),
            ],
        ];
    }

    public function transaction(Request $request)
    {
        $transaction = AccountingTransaction::query()
            ->with(['details.subaccount.account', 'branch', 'entity'])
            ->find($request->route('id'));

        if ($transaction === null) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        return [
            'data' => new AccountingTransactionCollectionResource($transaction),
        ];
    }

    public function create(Request $request)
    {
        $payload = $this->validatePayload($request, false);
        $entityId = $payload['entity_id'] ?? $this->resolveEntityIdFromBranch($payload['branch_id'] ?? null);

        if (AccountingPeriodLockService::isLocked(Carbon::parse($payload['date']), $payload['branch_id'] ?? null, $entityId)) {
            return response()->json([
                'message' => 'The selected accounting period is locked.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($payload, $entityId) {
            $status = $payload['status'] ?? 'draft';
            $transaction = AccountingTransaction::create([
                'entity_id' => $entityId,
                'branch_id' => $payload['branch_id'] ?? null,
                'label' => $payload['label'],
                'date' => Carbon::parse($payload['date']),
                'description' => $payload['description'] ?? null,
                'source_type' => $payload['source_type'] ?? null,
                'source_id' => $payload['source_id'] ?? null,
                'status' => $status,
                'posted_at' => $status === 'posted' ? now() : null,
                'reversed_at' => $status === 'reversed' ? now() : null,
            ]);

            $this->syncTransactionDetails($transaction, $payload['details']);

            return $transaction->load('details.subaccount.account');
        });

        return response()->json([
            'message' => 'Transaction saved successfully',
            'data' => new AccountingTransactionCollectionResource($transaction),
        ]);
    }

    public function update(Request $request)
    {
        $payload = $this->validatePayload($request, true);

        $transaction = AccountingTransaction::query()->find($payload['id']);

        if ($transaction === null) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        if ($transaction->automated || $transaction->accounting_posting_id !== null) {
            return response()->json([
                'message' => 'Automated or posted transactions cannot be edited',
            ], 422);
        }

        $entityId = $payload['entity_id'] ?? $this->resolveEntityIdFromBranch($payload['branch_id'] ?? null);

        if (AccountingPeriodLockService::isLocked(Carbon::parse($payload['date']), $payload['branch_id'] ?? null, $entityId)) {
            return response()->json([
                'message' => 'The selected accounting period is locked.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($transaction, $payload, $entityId) {
            $status = $payload['status'] ?? $transaction->status ?? 'draft';
            $transaction->update([
                'entity_id' => $entityId,
                'branch_id' => $payload['branch_id'] ?? null,
                'label' => $payload['label'],
                'date' => Carbon::parse($payload['date']),
                'description' => $payload['description'] ?? null,
                'source_type' => $payload['source_type'] ?? $transaction->source_type,
                'source_id' => $payload['source_id'] ?? $transaction->source_id,
                'status' => $status,
                'posted_at' => $status === 'posted' ? ($transaction->posted_at ?? now()) : null,
                'reversed_at' => $status === 'reversed' ? now() : null,
            ]);

            $this->revertTransactionDetails($transaction);
            $this->syncTransactionDetails($transaction, $payload['details']);

            return $transaction->load('details.subaccount.account');
        });

        return response()->json([
            'message' => 'Transaction saved successfully',
            'data' => new AccountingTransactionCollectionResource($transaction),
        ]);
    }

    public function upsert(Request $request)
    {
        if ($request->filled('id')) {
            return $this->update($request);
        }

        return $this->create($request);
    }

    private function buildTransactionQuery(Request $request)
    {
        $month = $request->query('month');
        $year = $request->query('year');
        $branchId = $request->query('branch_id');
        $entityId = $request->query('entity_id');
        $status = trim((string) $request->query('status', ''));
        $sourceType = trim((string) $request->query('source_type', ''));
        $automated = $request->query('automated');
        $search = trim((string) $request->query('search', ''));
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        return AccountingTransaction::query()
            ->with(['details.subaccount.account', 'branch', 'entity'])
            ->whereNull('accounting_posting_id')
            ->when($entityId, fn ($query) => $query->where('entity_id', (int) $entityId))
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($sourceType !== '', fn ($query) => $query->where('source_type', $sourceType))
            ->when($automated !== null && $automated !== '', fn ($query) => $query->where('automated', filter_var($automated, FILTER_VALIDATE_BOOLEAN)))
            ->when($year, fn ($query) => $query->whereYear('date', (int) $year))
            ->when($month, fn ($query) => $query->whereMonth('date', (int) $month))
            ->when($startDate, fn ($query) => $query->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('date', '<=', $endDate))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('label', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            });
    }

    private function validatePayload(Request $request, bool $isUpdate): array
    {
        $rules = [
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:2'],
            'label' => ['required', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,posted,reversed,failed'],
            'details.*.subaccountId' => ['required', 'integer', 'distinct'],
            'details.*.amount' => ['required', 'numeric', 'gt:0'],
            'details.*.type' => ['required', 'in:debit,credit'],
        ];

        if ($isUpdate) {
            $rules['id'] = ['required', 'integer'];
            $rules['details.*.id'] = ['nullable', 'integer'];
        }

        $validator = Validator::make($request->all(), $rules);
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

    private function syncTransactionDetails(AccountingTransaction $transaction, array $details): void
    {
        foreach ($details as $detail) {
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
    }

    private function revertTransactionDetails(AccountingTransaction $transaction): void
    {
        $oldDetails = AccountingTransactionDetail::query()
            ->where('accounting_transaction_id', $transaction->id)
            ->get();

        foreach ($oldDetails as $oldDetail) {
            $subaccount = AccountingSubaccount::query()->find($oldDetail->accounting_subaccount_id);

            if ($subaccount !== null) {
                if ($oldDetail->type === 'debit') {
                    $subaccount->total_debit -= (float) $oldDetail->amount;
                } else {
                    $subaccount->total_credit -= (float) $oldDetail->amount;
                }

                $subaccount->save();
            }

            $oldDetail->delete();
        }
    }
}
