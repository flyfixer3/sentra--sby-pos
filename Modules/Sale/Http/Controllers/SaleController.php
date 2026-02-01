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

// ✅ NEW: SaleOrder anchor
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

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

            $branchId = BranchContext::id();

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

            // optional quotation
            if ($request->quotation_id) {
                $quotation = Quotation::findOrFail($request->quotation_id);
                $quotation->update(['status' => 'Sent']);
            }

            $total_cost = 0;

            // ✅ Tentukan default warehouse untuk SaleOrder (kalau semua item 1 gudang)
            $warehouseIds = collect(Cart::instance('sale')->content())
                ->map(fn($it) => (int) ($it->options->warehouse_id ?? 0))
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values();

            $defaultWarehouseId = $warehouseIds->count() === 1 ? (int) $warehouseIds[0] : null;

            // ==========================================
            // ✅ CREATE INVOICE (SALE) - NO STOCK MOVEMENT
            // ==========================================
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

            // ==========================================
            // ✅ AUTO CREATE SALE ORDER (ANCHOR)
            // sale_orders.sale_id = sales.id
            // ==========================================
            $saleOrder = SaleOrder::create([
                'branch_id'    => $branchId,
                'customer_id'  => (int) $customer->id,
                'quotation_id' => $request->quotation_id ? (int) $request->quotation_id : null,
                'sale_id'      => (int) $sale->id,
                'warehouse_id' => $defaultWarehouseId,
                'reference'    => 'SO-TEMP-' . uniqid(), // sementara untuk lolos unique
                'date'         => $request->date,
                'note'         => $request->note,
                'created_by'   => auth()->id(),
                'updated_by'   => auth()->id(),
            ]);

            // setelah punya ID, set reference rapih
            $saleOrder->update([
                'reference' => make_reference_id('SO', (int) $saleOrder->id),
            ]);

            // ✅ ambil warehouse KS sekali (untuk konsinyasi)
            $warehouseKS = Warehouse::query()->where('warehouse_code', 'KS')->first();

            // ==========================================
            // ✅ Create SaleDetails + SaleOrderItem
            // ==========================================
            $aggregatedSOItems = []; // product_id => ['qty'=>x,'price'=>y]

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

                // ✅ agregasi untuk sale_order_items
                $pid = (int) $cart_item->id;
                $qty = (int) $cart_item->qty;
                $price = (int) $cart_item->price;

                if (!isset($aggregatedSOItems[$pid])) {
                    $aggregatedSOItems[$pid] = ['qty' => 0, 'price' => $price];
                }
                $aggregatedSOItems[$pid]['qty'] += $qty;

                // ==========================================
                // ✅ Konsinyasi KS: bikin purchase ke supplier KS
                // (tetap ada, tapi tidak ada mutation out)
                // ==========================================
                $isKS = $warehouseKS && ((int) $warehouseKS->id === (int) $cart_item->options->warehouse_id);

                if ($isKS) {
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
                }
            }

            // create sale_order_items
            foreach ($aggregatedSOItems as $pid => $row) {
                SaleOrderItem::create([
                    'sale_order_id' => (int) $saleOrder->id,
                    'product_id'    => (int) $pid,
                    'quantity'      => (int) $row['qty'],
                    'price'         => isset($row['price']) ? (int) $row['price'] : null,
                ]);
            }

            Cart::instance('sale')->destroy();

            // ==========================================
            // ✅ Accounting Transaction: selalu dibuat saat invoice dibuat
            // ==========================================
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

            // ==========================================
            // ✅ Payment record (kalau paid_amount > 0)
            // ==========================================
            if ((int) $sale->paid_amount > 0) {

                $paymentData = [
                    'date' => $request->date,
                    'reference' => 'INV/' . $sale->reference,
                    'amount' => (int) $sale->paid_amount,
                    'sale_id' => (int) $sale->id,
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

    public function show(Sale $sale)
    {
        abort_if(Gate::denies('show_sales'), 403);

        $branchId = BranchContext::id();

        $sale->load(['creator', 'updater', 'saleDetails']);

        $customer = Customer::query()
            ->where('id', $sale->customer_id)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->firstOrFail();

        // ✅ ambil sale deliveries yang terhubung ke invoice ini
        $saleDeliveries = \Modules\SaleDelivery\Entities\SaleDelivery::query()
            ->where('branch_id', $branchId)
            ->where('sale_id', (int) $sale->id)
            ->orderByDesc('id')
            ->get();

        return view('sale::show', compact('sale', 'customer', 'saleDeliveries'));
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
                    'stock'       => 0,
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

            // ✅ invoice update: tidak menyentuh stock sama sekali
            foreach ($sale->saleDetails as $sale_detail) {
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
                'fee_amount' => $request->fee_amount * 1,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'total_quantity' => $request->total_quantity,
                'due_amount' => $due_amount * 1,
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
                    'product_cost'=> (int) ($cart_item->options->product_cost ?? 0),
                    'warehouse_id' => (int) ($cart_item->options->warehouse_id ?? 0),
                    'quantity' => (int) $cart_item->qty,
                    'price' => (int) $cart_item->price,
                    'unit_price' => (int) ($cart_item->options->unit_price ?? 0),
                    'sub_total' => (int) ($cart_item->options->sub_total ?? 0),
                    'product_discount_amount' => (int) ($cart_item->options->product_discount ?? 0),
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => (int) ($cart_item->options->product_tax ?? 0),
                ]);
            }

            Cart::instance('sale')->destroy();
        });

        toast('Sale Updated!', 'info');
        return redirect()->route('sales.index');
    }

    public function destroy(Sale $sale) {
        abort_if(Gate::denies('delete_sales'), 403);
        toast('Sale Deleted!', 'warning');
        return redirect()->route('sales.index');
    }
}
