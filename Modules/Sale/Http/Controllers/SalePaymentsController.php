<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalePaymentsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Sale\Entities\Sale;
use App\Helpers\Helper;
use Modules\Sale\Entities\SalePayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\People\Entities\Customer;

class SalePaymentsController extends Controller
{

    public function index($sale_id, SalePaymentsDataTable $dataTable) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return $dataTable->render('sale::payments.index', compact('sale'));
    }


    public function create($sale_id) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return view('sale::payments.create', compact('sale'));
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_sale_payments'), 403);
        
        $sale = Sale::findOrFail($request->sale_id);
        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'sale_id' => 'required',
            'payment_method' => 'required|string|max:255',
            'deposit_code' => 'required|string|max:255'
        ]);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:' . $sale->due_amount],
            'payment_method' => 'required',
            'deposit_code' => 'required',
        ], [
            'amount.required' => 'The amount field is required.',
            'amount.min' => 'The amount must be at least 1.',
            'amount.max' => 'The amount cannot be greater than the due amount of ' . format_currency($sale->due_amount) . '.',
        ]);
    
        // dd($request);
        DB::transaction(function () use ($request) {
            $sale_payment = SalePayment::create([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'sale_id' => $request->sale_id,
                'payment_method' => $request->payment_method,
                'deposit_code' => $request->deposit_code
            ]);

            $sale = Sale::findOrFail($request->sale_id);
            $due_amount = $sale->due_amount - $request->amount;

            if ($due_amount == $sale->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $sale->update([
                'paid_amount' => ($sale->paid_amount + $request->amount) * 1,
                'due_amount' => $due_amount * 1,
                'payment_status' => $payment_status
            ]);
            Helper::addNewTransaction([
                'date' =>  $request->date,
                'label' => "Payment For Sale #".$request->reference,
                'description' => "Sale ID: ".$request->reference,
                'purchase_id' => null,
                'purchase_payment_id' => null,
                'purchase_return_id' => null,
                'purchase_return_payment_id' => null,
                'sale_id' =>$request->sale_id,
                'sale_payment_id' => $sale_payment->id,
                'sale_return_id' => null,
                'sale_return_payment_id' => null,
            ], [
                [
                    'subaccount_number' => $sale_payment->deposit_code, // Persediaan
                    'amount' => $request->amount,
                    'type' => 'debit'
                ],
                [
                    'subaccount_number' => '1-10100', // Hutang Usaha
                    'amount' => $request->amount,
                    'type' => 'credit'
                ],
            ]);
        });


        toast('Sale Payment Created!', 'success');

        return redirect()->route('sales.index');
    }


    public function edit($sale_id, SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return view('sale::payments.edit', compact('salePayment', 'sale'));
    }


    public function update(Request $request, SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'sale_id' => 'required',
            'payment_method' => 'required|string|max:255',
            'deposit_code' => 'required|string|max:255'
        ]);

        DB::transaction(function () use ($request, $salePayment) {
            $sale = $salePayment->sale;

            $due_amount = ($sale->due_amount + $salePayment->amount) - $request->amount;

            if ($due_amount == $sale->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $sale->update([
                'paid_amount' => (($sale->paid_amount - $salePayment->amount) + $request->amount) * 1,
                'due_amount' => $due_amount * 1,
                'payment_status' => $payment_status
            ]);

            $salePayment->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'sale_id' => $request->sale_id,
                'payment_method' => $request->payment_method
            ]);
        });

        toast('Sale Payment Updated!', 'info');

        return redirect()->route('sales.index');
    }


    public function destroy(SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $salePayment->delete();

        toast('Sale Payment Deleted!', 'warning');

        return redirect()->route('sales.index');
    }

    public function receipt(SalePayment $salePayment)
    {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $salePayment->load('sale');
        $sale = $salePayment->sale;

        if (!$sale) {
            abort(404);
        }

        $customer = Customer::query()->find($sale->customer_id);

        // Hitung paid_before supaya receipt bisa menampilkan: sebelum bayar ini sudah berapa
        $paidBefore = (int) SalePayment::query()
            ->where('sale_id', (int) $sale->id)
            ->where('id', '<', (int) $salePayment->id)
            ->sum('amount');

        $grandTotal = (int) ($sale->total_amount ?? 0);
        $paidThis = (int) ($salePayment->amount ?? 0);
        $paidAfter = $paidBefore + $paidThis;
        $remaining = max(0, $grandTotal - $paidAfter);

        $pdf = Pdf::loadView('sale::payments.receipt', [
            'sale' => $sale,
            'customer' => $customer,
            'salePayment' => $salePayment,
            'paidBefore' => $paidBefore,
            'paidAfter' => $paidAfter,
            'remaining' => $remaining,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream('receipt-' . ($salePayment->reference ?? $salePayment->id) . '.pdf');
    }

    public function receiptDebug(SalePayment $salePayment)
    {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $salePayment->load('sale');
        $sale = $salePayment->sale;

        if (!$sale) {
            abort(404);
        }

        $customer = Customer::query()->find($sale->customer_id);

        $paidBefore = (int) SalePayment::query()
            ->where('sale_id', (int) $sale->id)
            ->where('id', '<', (int) $salePayment->id)
            ->sum('amount');

        $grandTotal = (int) ($sale->total_amount ?? 0);
        $paidThis = (int) ($salePayment->amount ?? 0);
        $paidAfter = $paidBefore + $paidThis;
        $remaining = max(0, $grandTotal - $paidAfter);

        return view('sale::payments.receipt', [
            'sale' => $sale,
            'customer' => $customer,
            'salePayment' => $salePayment,
            'paidBefore' => $paidBefore,
            'paidAfter' => $paidAfter,
            'remaining' => $remaining,
        ]);
    }
}
