<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalesDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Mutation\Entities\Mutation;
use Modules\Sale\Entities\Sale;
use Modules\Quotation\Entities\Quotation;
use Carbon\Carbon;
use Modules\Sale\Entities\SaleDetails;
use App\Helpers\Helper;
use App\Support\BranchContext;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Http\Requests\StoreSaleRequest;
use Modules\Sale\Http\Requests\UpdateSaleRequest;

class SaleController extends Controller
{

    public function index(SalesDataTable $dataTable) {
        abort_if(Gate::denies('access_sales'), 403);

        return $dataTable->render('sale::index');
    }


    public function create() {
        abort_if(Gate::denies('create_sales'), 403);

        $branchId = BranchContext::id();

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
            })
            ->orderBy('customer_name')
            ->get();

        Cart::instance('sale')->destroy();

        return view('sale::create', compact('warehouses', 'customers'));
    }

    public function store(StoreSaleRequest $request)
    {
        abort_if(Gate::denies('create_sales'), 403);

        DB::transaction(function () use ($request) {

            $branchId = BranchContext::id(); // wajib ada (route write udah pakai branch.selected)

            // ✅ validasi customer harus global atau branch aktif
            $customer = Customer::query()
                ->where('id', $request->customer_id)
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
                })
                ->firstOrFail();

            $due_amount = $request->total_amount - $request->paid_amount;

            if ($due_amount == $request->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            if ($request->quotation_id) {
                $quotation = Quotation::findOrFail($request->quotation_id);
                $quotation->update(['status' => 'Sent']);
            }

            $total_cost = 0;

            $saleData = [
                'date'                => $request->date,
                'license_number'      => $request->car_number_plate,
                'sale_from'           => $request->sale_from,
                'customer_id'         => $customer->id,
                'customer_name'       => $customer->customer_name,
                'tax_percentage'      => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount'     => (int) $request->shipping_amount,
                'paid_amount'         => (int) $request->paid_amount,
                'total_amount'        => (int) $request->total_amount,
                'total_quantity'      => (int) $request->total_quantity,
                'fee_amount'          => (int) $request->fee_amount,
                'due_amount'          => (int) $due_amount,
                'status'              => $request->status,
                'payment_status'      => $payment_status,
                'payment_method'      => $request->payment_method,
                'note'                => $request->note,
                'tax_amount'          => (int) Cart::instance('sale')->tax(),
                'discount_amount'     => (int) Cart::instance('sale')->discount(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'branch_id')) {
                $saleData['branch_id'] = $branchId;
            }

            $sale = Sale::create($saleData);

            // ✅ ambil warehouse KS SEKALI (jangan di dalam loop)
            $warehouseKS = Warehouse::query()->where('warehouse_code', 'KS')->first();

            foreach (Cart::instance('sale')->content() as $cart_item) {

                $total_cost += (int) ($cart_item->options->product_cost ?? 0);

                $saleDetailData = [
                    'sale_id'                 => $sale->id,
                    'product_id'              => $cart_item->id,
                    'product_name'            => $cart_item->name,
                    'product_code'            => $cart_item->options->code,
                    'product_cost'            => (int) $cart_item->options->product_cost,
                    'warehouse_id'            => (int) $cart_item->options->warehouse_id,
                    'quantity'                => (int) $cart_item->qty,
                    'price'                   => (int) $cart_item->price,
                    'unit_price'              => (int) $cart_item->options->unit_price,
                    'sub_total'               => (int) $cart_item->options->sub_total,
                    'product_discount_amount' => (int) $cart_item->options->product_discount,
                    'product_discount_type'   => $cart_item->options->product_discount_type,
                    'product_tax_amount'      => (int) $cart_item->options->product_tax,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('sale_details', 'branch_id')) {
                    $saleDetailData['branch_id'] = $branchId;
                }

                SaleDetails::create($saleDetailData);

                // ==================================================
                // ✅ FIX BUG: KS vs non-KS harus pakai flag, bukan if-else warehouseKS exists
                // ==================================================
                $isKS = $warehouseKS && ((int) $warehouseKS->id === (int) $cart_item->options->warehouse_id);

                if ($isKS) {
                    // konsinyasi -> bikin purchase ke supplier KS
                    $supplierKs = Supplier::query()->where('supplier_name', 'MJD-KS')->first();

                    if ($supplierKs) {
                        $purchase = Purchase::create([
                            'date'                => $request->date,
                            'due_date'            => 0,
                            'supplier_id'         => $supplierKs->id,
                            'supplier_name'       => $supplierKs->supplier_name,
                            'tax_percentage'      => 0,
                            'discount_percentage' => 0,
                            'shipping_amount'     => 0,
                            'paid_amount'         => 0,
                            'total_amount'        => (int) ($cart_item->options->product_cost * $cart_item->qty),
                            'due_amount'          => (int) ($cart_item->options->product_cost * $cart_item->qty),
                            'status'              => "Pending",
                            'total_quantity'      => (int) $request->total_quantity,
                            'payment_status'      => "Unpaid",
                            'payment_method'      => "Bank Transfer",
                            'note'                => "Penjualan Konsyinasi, referensi nota sale: " . $sale->reference,
                            'tax_amount'          => (int) Cart::instance('sale')->tax(),
                            'discount_amount'     => 0,
                        ]);

                        PurchaseDetail::create([
                            'purchase_id'              => $purchase->id,
                            'product_id'               => $cart_item->id,
                            'product_name'             => $cart_item->name,
                            'product_code'             => $cart_item->options->code,
                            'quantity'                 => (int) $cart_item->qty,
                            'price'                    => (int) $cart_item->options->product_cost,
                            'unit_price'               => (int) $cart_item->options->unit_price,
                            'sub_total'                => (int) ($cart_item->options->product_cost * $cart_item->qty),
                            'warehouse_id'             => (int) $cart_item->options->warehouse_id,
                            'product_discount_amount'  => (int) ($cart_item->options->unit_price - $cart_item->options->product_cost),
                            'product_discount_type'    => "fixed",
                            'product_tax_amount'       => 0,
                        ]);
                    }

                    // NOTE: untuk KS kamu memang tidak bikin mutation out di code lama (asumsi stok KS gak dihitung)
                    continue;
                }

                // ==================================================
                // non-KS: kalau shipped/completed -> mutation out + accounting
                // ==================================================
                if ($request->status === 'Shipped' || $request->status === 'Completed') {

                    $mutation = Mutation::where('product_id', $cart_item->id)
                        ->where('warehouse_id', $cart_item->options->warehouse_id)
                        ->latest()
                        ->first();

                    $_stock_early = $mutation ? (int) $mutation->stock_last : 0;
                    $_stock_in    = 0;
                    $_stock_out   = (int) $cart_item->qty;
                    $_stock_last  = $_stock_early - $_stock_out;

                    $mutationData = [
                        'reference'     => $sale->reference,
                        'date'          => $request->date,
                        'mutation_type' => "Out",
                        'note'          => "Mutation for Sale: " . $sale->reference,
                        'warehouse_id'  => (int) $cart_item->options->warehouse_id,
                        'product_id'    => (int) $cart_item->id,
                        'stock_early'   => $_stock_early,
                        'stock_in'      => $_stock_in,
                        'stock_out'     => $_stock_out,
                        'stock_last'    => $_stock_last,
                    ];

                    if (\Illuminate\Support\Facades\Schema::hasColumn('mutations', 'branch_id')) {
                        $mutationData['branch_id'] = $branchId;
                    }

                    Mutation::create($mutationData);

                    // ==============================
                    // Accounting Transaction (tetap sama)
                    // ==============================
                    if ($total_cost <= 0) {
                        Helper::addNewTransaction([
                            'date' => $request->date,
                            'label' => "Sale Invoice for #" . $sale->reference,
                            'description' => "Order ID: " . $sale->reference,
                            'purchase_id' => null,
                            'purchase_payment_id' => null,
                            'purchase_return_id' => null,
                            'purchase_return_payment_id' => null,
                            'sale_id' => $sale->id,
                            'sale_payment_id' => null,
                            'sale_return_id' => null,
                            'sale_return_payment_id' => null,
                        ], [
                            ['subaccount_number' => '1-10100', 'amount' => $sale->total_amount, 'type' => 'debit'],
                            ['subaccount_number' => '4-40000', 'amount' => $sale->total_amount, 'type' => 'credit'],
                        ]);
                    } else {
                        Helper::addNewTransaction([
                            'date' => $sale->date,
                            'label' => "Sale Invoice for #" . $sale->reference,
                            'description' => "Order ID: " . $sale->reference,
                            'purchase_id' => null,
                            'purchase_payment_id' => null,
                            'purchase_return_id' => null,
                            'purchase_return_payment_id' => null,
                            'sale_id' => $sale->id,
                            'sale_payment_id' => null,
                            'sale_return_id' => null,
                            'sale_return_payment_id' => null,
                        ], [
                            ['subaccount_number' => '1-10100', 'amount' => $sale->total_amount, 'type' => 'debit'],
                            ['subaccount_number' => '5-50000', 'amount' => $total_cost, 'type' => 'debit'],
                            ['subaccount_number' => '4-40000', 'amount' => $sale->total_amount, 'type' => 'credit'],
                            ['subaccount_number' => '1-10200', 'amount' => $total_cost, 'type' => 'credit'],
                        ]);
                    }
                }
            }

            Cart::instance('sale')->destroy();

            // payment
            if ($sale->paid_amount > 0) {

                $paymentData = [
                    'date' => $request->date,
                    'reference' => 'INV/' . $sale->reference,
                    'amount' => $sale->paid_amount,
                    'sale_id' => $sale->id,
                    'payment_method' => $request->payment_method,
                    'deposit_code' => $request->deposit_code
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('sale_payments', 'branch_id')) {
                    $paymentData['branch_id'] = $branchId;
                }

                $created_payment = SalePayment::create($paymentData);

                Helper::addNewTransaction([
                    'date' => $request->date,
                    'label' => "Payment for Sales Order #" . $sale->reference,
                    'description' => "Sale ID: " . $sale->reference,
                    'purchase_id' => null,
                    'purchase_payment_id' => null,
                    'purchase_return_id' => null,
                    'purchase_return_payment_id' => null,
                    'sale_id' => null,
                    'sale_payment_id' => $created_payment->id,
                    'sale_return_id' => null,
                    'sale_return_payment_id' => null,
                ], [
                    ['subaccount_number' => '1-10100', 'amount' => $created_payment->amount, 'type' => 'debit'],
                    ['subaccount_number' => $created_payment->deposit_code, 'amount' => $created_payment->amount, 'type' => 'credit'],
                ]);
            }
        });

        toast('Sale Created!', 'success');
        return redirect()->route('sales.index');
    }

    public function show(Sale $sale) {
        abort_if(Gate::denies('show_sales'), 403);

        $sale->load(['creator', 'updater']);
        $customer = Customer::findOrFail($sale->customer_id);

        return view('sale::show', compact('sale', 'customer'));
    }


    public function edit(Sale $sale) {
        abort_if(Gate::denies('edit_sales'), 403);

        $sale_details = $sale->saleDetails;

        $branchId = BranchContext::id();

        Cart::instance('sale')->destroy();

        $cart = Cart::instance('sale');

        foreach ($sale_details as $sale_detail) {
            $cart->add([
                'id'      => $sale_detail->product_id,
                'name'    => $sale_detail->product_name,
                'qty'     => $sale_detail->quantity,
                'price'   => $sale_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $sale_detail->product_discount_amount,
                    'product_discount_type' => $sale_detail->product_discount_type,
                    'sub_total'   => $sale_detail->sub_total,
                    'code'        => $sale_detail->product_code,
                    'stock'       => !Mutation::with('warehouse')
                                        ->where('warehouse_id', $sale_detail->warehouse_id)
                                        ->where('product_id', $sale_detail->product_id)
                                        ->latest()
                                        ->first() ? 0 : Mutation::with('warehouse')
                                        ->where('warehouse_id', $sale_detail->warehouse_id)
                                        ->where('product_id', $sale_detail->product_id)
                                        ->latest()
                                        ->first()->stock_last,
                    'warehouse_id'=> $sale_detail->warehouse_id,
                    'product_cost'=> $sale_detail->product_cost,
                    'product_tax' => $sale_detail->product_tax_amount,
                    'unit_price'  => $sale_detail->unit_price
                ]
            ]);
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
            })
            ->orderBy('customer_name')
            ->get();

        return view('sale::edit', compact('sale', 'customers', 'warehouses'));
    }

    public function update(UpdateSaleRequest $request, Sale $sale) {
        DB::transaction(function () use ($request, $sale) {

            $due_amount = $request->total_amount - $request->paid_amount;

            if ($due_amount == $request->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }
            $list_cost = array();
            foreach ($sale->saleDetails as $sale_detail) {
                if ($sale->status == 'Shipped' || $sale->status == 'Completed') {
                    $product = Product::findOrFail($sale_detail->product_id);
                    $product->update([
                        'product_quantity' => $product->product_quantity + $sale_detail->quantity
                    ]);
                }
                $list_cost[$sale_detail->product_id] = $sale_detail->product_cost;
                $sale_detail->delete();
            }

            $sale->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'customer_id' => $request->customer_id,
                'customer_name' => Customer::findOrFail($request->customer_id)->customer_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'fee_amount' => $request-> fee_amount * 1,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'total_quantity' => $request->total_quantity,
                'due_amount' => $due_amount * 1,
                'status' => $request->status,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('sale')->tax() * 1,
                'discount_amount' => Cart::instance('sale')->discount() * 1,
            ]);

            foreach (Cart::instance('sale')->content() as $cart_item) {
                SaleDetails::create([
                    'sale_id' => $sale->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'product_cost'=> $list_cost[$cart_item->id],
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'unit_price' => $cart_item->options->unit_price * 1,
                    'sub_total' => $cart_item->options->sub_total * 1,
                    'product_discount_amount' => $cart_item->options->product_discount * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax * 1,
                ]);

                if ($request->status == 'Shipped' || $request->status == 'Completed') {
                    $product = Product::findOrFail($cart_item->id);
                    $product->update([
                        'product_quantity' => $product->product_quantity - $cart_item->qty
                    ]);
                }
            }

            Cart::instance('sale')->destroy();
        });

        toast('Sale Updated!', 'info');

        return redirect()->route('sales.index');
    }


    public function destroy(Sale $sale) {
        abort_if(Gate::denies('delete_sales'), 403);
        // DB::transaction(function () use ($sale) {

        // });
        // $sale->delete();
        foreach ($sale->saleDetails as $sale_detail) {
            // dd($sale_detail);

            if ($sale->status == 'Shipped' || $sale->status == 'Completed') {
                $mutation = Mutation::with('warehouse')->where('product_id', $sale_detail->product_id)
                ->where('warehouse_id', $sale_detail->warehouse_id)
                ->latest()->first();
                // dd($mutation);
                if($mutation['warehouse']->warehouse_code == 'KS'){
                    $purchase = Purchase::with('purchaseDetails')
                    ->where('note', 'like', '%' . $sale->reference . '%')
                    ->whereHas('purchaseDetails', function($q) use ($sale_detail){
                        $q->where('product_id', $sale_detail->product_id);
                    });
                    $purchase->update(['status' => 'Void']);
                }


                $_stock_early = $mutation ? $mutation->stock_last : 0;
                $_stock_in = $sale_detail->quantity;
                $_stock_out = 0;
                $_stock_last = $_stock_early + $_stock_in;

                Mutation::create([
                    'reference' => $sale->reference,
                    'date' => $sale->date,
                    'mutation_type' => "In",
                    'note' => "Hapus Penjualan, Referensi : ". $sale->reference,
                    'warehouse_id' => $sale_detail->warehouse_id,
                    'product_id' => $sale_detail->product_id,
                    'stock_early' => $_stock_early,
                    'stock_in' => $_stock_in,
                    'stock_out'=> $_stock_out,
                    'stock_last'=> $_stock_last,
                ]);
            }
        }

        $sale->update(['status' => 'Void']);
        toast('Sale Deleted!', 'warning');

        return redirect()->route('sales.index');
    }
}
