<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingTransactionCollection;
use App\Http\Resources\AccountingTransactionCollectionResource;
use App\Models\AccountingSubaccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingTransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountingTransactionController extends Controller
{
    function allTransactions (Request $request) {
        $startDate = $request->query('startDate', null);
        $endDate = $request->query('endDate', null);
        $month = $request->query('month', null);
        $user = Auth::user();

        $transactions = AccountingTransaction::query()  
            ->whereMonth('date','=',$month)
            ->whereYear('date' ,'=', 2024)
            // ->where('date', '>=', $startDate)
            // ->where('date', '<=', $endDate)
            ->where('accounting_posting_id', '=', null)
            ->orderBy('date', 'ASC');

        //     $transactions = AccountingTransaction::withWhereHas('details', fn($query) =>
        //     $query->where('accounting_subaccount_id', '=', 1)
        //    )->whereMonth('date','=','02');
        return [
            'data' => new AccountingTransactionCollection($transactions->get()),
        ];
    }

    function transactions (Request $request) {
        // $user = Auth::user();
        // $limit = $request->query('limit', 20);
        // $search = trim($request->query('search', ''));

        // $transactions = AccountingTransaction::query()
        //     ->when(!empty($search), function ($query) use ($search) {
        //         return $query->where('label', 'LIKE', '%' . $search . '%')
        //             ->orWhere('description', 'LIKE', '%' . $search . '%');
        //     })
        //     ->where('accounting_posting_id', '=', null)
        //     ->orderBy('date', 'DESC');

        
        // $total = AccountingTransaction::query()
        //     ->where('accounting_posting_id', '=', null)
        //     ->count();

        // return [
        //     'page' => new AccountingTransactionCollection($transactions->paginate($limit)),
        //     'meta' => [
        //         'total' => $total
        //     ]
        // ];

        $user = Auth::user();
        $limit = $request->query('limit', 20);
        $month = $request->query('month', null);
        $year = $request->query('year', null);
        $search = trim($request->query('search', ''));

        $transactions = AccountingTransaction::query()
        ->when(!empty($year), function ($query) use ($year) {
            return $query->whereYear('date','=',$year);
        })
        ->when(!empty($month), function ($query) use ($month) {
            return $query->whereMonth('date','=',$month);
        })
        ->when(!empty($search), function ($query) use ($search) {
            return $query->where('label', 'LIKE', '%' . $search . '%')
                ->orWhere('description', 'LIKE', '%' . $search . '%');
        })
            ->where('accounting_posting_id', '=', null)
            ->orderBy('date', 'DESC');

        
        $total = AccountingTransaction::query()
            ->where('accounting_posting_id', '=', null)
            ->count();

        return [
            'page' => new AccountingTransactionCollection($transactions->paginate($total)),
            'meta' => [
                'total' => $total
            ]
        ];
    }

    function transaction (Request $request) {
        $user = Auth::user();

        $transaction = AccountingTransaction::query()
            ->where('id', '=', $request->route('id'))
            ->first();

        return [
            'data' => new AccountingTransactionCollectionResource($transaction),
        ];
    }

    function create (Request $request) {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'description' => 'string|nullable',
            'details' => 'required|array',
            'label' => 'required|string',
            'details.*.subaccountId' => 'required|integer',
            'details.*.amount' => 'required|numeric',
            'details.*.type' => 'required|in:debit,credit',
        ]);
        $validator->validate();
        // $test = Carbon::parse($request->input('date'), 'Asia/Jakarta');
        
        // return response()->json(['message' => $test], 400);
        $user = Auth::user();
        DB::beginTransaction();
        $transaction = AccountingTransaction::create([
            'label' => $request->input('label'),
            'date' => Carbon::parse($request->input('date')),
            'description' => $request->input('description'),
        ]);

        foreach ($request->input('details') as $detail) {
            $subaccount = AccountingSubaccount::query()->where('id', '=', $detail['subaccountId'])->first();
            if ($subaccount == null) {
                DB::rollBack();
                return response()->json(['message' => 'Subaccount not found'], 400);
            }
            if ($detail['type'] == 'debit') {
                $subaccount->total_debit += $detail['amount'];
            } else {
                $subaccount->total_credit += $detail['amount'];
            }
            $subaccount->save();
            AccountingTransactionDetail::create([
                'accounting_transaction_id' => $transaction->id,
                'accounting_subaccount_id' => $detail['subaccountId'],
                'amount' => $detail['amount'],
                'type' => $detail['type'],
            ]);
        }
        DB::commit();
    }

    function update (Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'date' => 'required|date',
            'description' => 'string|nullable',
            'details' => 'required|array',
            'label' => 'required|string',
            'details.*.id' => 'required|integer',
            'details.*.subaccountId' => 'required|integer',
            'details.*.amount' => 'required|numeric',
            'details.*.type' => 'required|in:debit,credit',
        ]);
        $validator->validate();

        $user = Auth::user();

        DB::beginTransaction();
        $transaction = AccountingTransaction::find($request->input('id'));
        if ($transaction == null || 
            (($transaction->ibo_id != null && $transaction->ibo_id != $user->ibo_id) ||
            ($transaction->training_center_id != null && $transaction->training_center_id != $user->training_center_id))) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        // return response()->json(['message' => Carbon::parse($request->input('date'))], 404);
        $transaction->label = $request->input('label');
        $transaction->date = Carbon::parse($request->input('date'));
        $transaction->description = $request->input('description');
        $transaction->save();

        $oldDetails = AccountingTransactionDetail::query()->where('accounting_transaction_id', '=', $request->input('id'))->get();
        foreach ($oldDetails as $oldDetail) {
            $subaccount = AccountingSubaccount::query()->where('id', '=', $oldDetail->accounting_subaccount_id)->first();
            if ($oldDetail->type == 'debit') {
                $subaccount->total_debit -= $oldDetail->amount;
            } else {
                $subaccount->total_credit -= $oldDetail->amount;
            }
            $subaccount->save();
            $oldDetail->delete();
        }

        foreach ($request->input('details') as $detail) {
            $subaccount = AccountingSubaccount::query()->where('id', '=', $detail['subaccountId'])->first();
            if ($subaccount == null) {
                DB::rollBack();
                return response()->json(['message' => 'Subaccount not found'], 400);
            }
            if ($detail['type'] == 'debit') {
                $subaccount->total_debit += $detail['amount'];
            } else {
                $subaccount->total_credit += $detail['amount'];
            }
            $subaccount->save();
            AccountingTransactionDetail::create([
                'accounting_transaction_id' => $transaction->id,
                'accounting_subaccount_id' => $detail['subaccountId'],
                'amount' => $detail['amount'],
                'type' => $detail['type'],
            ]);
        }
        DB::commit();
    }

    function upsert (Request $request) {
        if ($request->input('id') != null) {
            return $this->update($request);
        } else {
            return $this->create($request);
        }
    }
}
