<?php

namespace Modules\Expense\Http\Controllers;

use Modules\Expense\DataTables\ExpensesDataTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Expense\Entities\Expense;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Exp;
use App\Helpers\Helper;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Modules\Expense\Entities\ExpenseCategory;

class ExpenseController extends Controller
{

    public function index(ExpensesDataTable $dataTable) {
        abort_if(Gate::denies('access_expenses'), 403);

        return $dataTable->render('expense::expenses.index');
    }


    public function create() {
        abort_if(Gate::denies('create_expenses'), 403);

        return view('expense::expenses.create');
    }


    public function store(Request $request) 
    {
        abort_if(Gate::denies('create_expenses'), 403);

        $branchId = BranchContext::id();
        abort_unless($branchId, 403); // wajib pilih cabang spesifik (bukan ALL) untuk input transaksi

        $request->validate([
            'date' => 'required|date',
            'category_id' => 'required|numeric|exists:expense_categories,id',
            'type' => 'required|in:debit,credit',
            'payment_method' => 'required|string|max:255',
            'from_account' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1|max:2147483647',
            'details' => 'nullable|string|max:1000',
        ]);

        $type = strtolower($request->type);

        // Kalau DEBIT (uang masuk), from_account wajib (sumber dana)
        if ($type === 'debit') {
            if (!$request->from_account) {
                return back()->withErrors('For DEBIT (IN), Source Account is required.')->withInput();
            }

            if ($request->from_account === $request->payment_method) {
                return back()->withErrors('Source Account and Cash/Bank Account cannot be the same.')->withInput();
            }
        }

        $category = ExpenseCategory::findOrFail((int) $request->category_id);

        // Kalau CREDIT (uang keluar), category harus punya mapping akun beban
        if ($type === 'credit') {
            if (empty($category->subaccount_number)) {
                return back()->withErrors('This category has no Expense Account mapping (subaccount_number). Please set it first in Expense Categories.')->withInput();
            }
        }

        DB::transaction(function () use ($request, $branchId, $type, $category) {

            $expense = Expense::create([
                'branch_id' => (int) $branchId,
                'category_id' => (int) $request->category_id,
                'date' => $request->date,
                // reference auto by model boot()
                'type' => $type,
                'payment_method' => $request->payment_method,
                'from_account' => $type === 'debit' ? $request->from_account : null,
                'amount' => (int) $request->amount,
                'details' => $request->details,
            ]);

            // =========================
            // Accounting Journal
            // =========================
            if ($type === 'credit') {
                // OUT: Debit Beban (kategori), Credit Kas/Bank (payment_method)
                Helper::addNewTransaction([
                    'date' => $request->date,
                    'label' => "Expense (Cash Out)",
                    'description' => "Expense Ref: {$expense->reference} | {$category->category_name}",
                    'purchase_id' => null,
                    'purchase_payment_id' => null,
                    'purchase_return_id' => null,
                    'purchase_return_payment_id' => null,
                    'sale_id' => null,
                    'sale_payment_id' => null,
                    'sale_return_id' => null,
                    'sale_return_payment_id' => null,
                ], [
                    [
                        'subaccount_number' => $category->subaccount_number, // akun beban
                        'amount' => (int) $request->amount,
                        'type' => 'debit'
                    ],
                    [
                        'subaccount_number' => $request->payment_method, // kas/bank
                        'amount' => (int) $request->amount,
                        'type' => 'credit'
                    ]
                ]);
            } else {
                // IN: Debit Kas/Bank penerima (payment_method), Credit akun sumber (from_account)
                Helper::addNewTransaction([
                    'date' => $request->date,
                    'label' => "Petty Cash (Cash In)",
                    'description' => "Expense Ref: {$expense->reference} | Source: {$request->from_account}",
                    'purchase_id' => null,
                    'purchase_payment_id' => null,
                    'purchase_return_id' => null,
                    'purchase_return_payment_id' => null,
                    'sale_id' => null,
                    'sale_payment_id' => null,
                    'sale_return_id' => null,
                    'sale_return_payment_id' => null,
                ], [
                    [
                        'subaccount_number' => $request->payment_method, // kas/bank penerima
                        'amount' => (int) $request->amount,
                        'type' => 'debit'
                    ],
                    [
                        'subaccount_number' => $request->from_account, // sumber
                        'amount' => (int) $request->amount,
                        'type' => 'credit'
                    ]
                ]);
            }
        });

        toast('Expense Saved!', 'success');

        return redirect()->route('expenses.index');
    }

    public function edit(Expense $expense) {
        abort_if(Gate::denies('edit_expenses'), 403);

        return view('expense::expenses.edit', compact('expense'));
    }

    public function update(Request $request, Expense $expense) 
    {
        abort_if(Gate::denies('edit_expenses'), 403);

        $branchId = BranchContext::id();
        abort_unless($branchId, 403);

        $request->validate([
            'date' => 'required|date',
            'category_id' => 'required|numeric|exists:expense_categories,id',
            'type' => 'required|in:debit,credit',
            'payment_method' => 'required|string|max:255',
            'from_account' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1|max:2147483647',
            'details' => 'nullable|string|max:1000',
        ]);

        $type = strtolower($request->type);

        if ($type === 'debit') {
            if (!$request->from_account) {
                return back()->withErrors('For DEBIT (IN), Source Account is required.')->withInput();
            }
            if ($request->from_account === $request->payment_method) {
                return back()->withErrors('Source Account and Cash/Bank Account cannot be the same.')->withInput();
            }
        }

        $category = ExpenseCategory::findOrFail((int) $request->category_id);

        if ($type === 'credit') {
            if (empty($category->subaccount_number)) {
                return back()->withErrors('This category has no Expense Account mapping (subaccount_number). Please set it first in Expense Categories.')->withInput();
            }
        }

        DB::transaction(function () use ($request, $expense, $branchId, $type, $category) {

            // NOTE:
            // saat ini kita TIDAK reverse accounting_transaction lama karena table accounting_transactions
            // belum punya foreign key ke expenses. Jadi update hanya update ledger expense.
            // (Kalau mau next step: kita tambah kolom expense_id di accounting_transactions biar bisa reverse + re-post.)
            $expense->update([
                'branch_id' => (int) $branchId,
                'category_id' => (int) $request->category_id,
                'date' => $request->date,
                'type' => $type,
                'payment_method' => $request->payment_method,
                'from_account' => $type === 'debit' ? $request->from_account : null,
                'amount' => (int) $request->amount,
                'details' => $request->details,
            ]);

            // OPTIONAL (kalau kamu mau “strict accounting”):
            // kita bisa buat transaction baru koreksi (reversal + repost),
            // tapi itu akan menggandakan histori. Untuk sekarang aku tahan dulu biar aman.
        });

        toast('Expense Updated!', 'info');

        return redirect()->route('expenses.index');
    }

    public function destroy(Expense $expense) {
        abort_if(Gate::denies('delete_expenses'), 403);

        $expense->delete();

        toast('Expense Deleted!', 'warning');

        return redirect()->route('expenses.index');
    }
}
