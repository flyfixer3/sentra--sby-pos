<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalesDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\People\Entities\Customer;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Warehouse;
use Modules\Sale\Entities\Sale;
use Modules\Quotation\Entities\Quotation;
use Modules\Sale\Entities\SaleDetails;
use App\Helpers\Helper;
use App\Support\BranchContext;
use Modules\Product\Entities\Product;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Http\Requests\StoreSaleRequest;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryItem;
use Modules\Sale\Http\Requests\UpdateSaleRequest;

// ✅ NEW: SaleOrder anchor (tetap biarin, karena sudah ada di project kamu)
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleController extends Controller
{
    public function index(SalesDataTable $dataTable) {
        abort_if(Gate::denies('access_sales'), 403);
        return $dataTable->render('sale::index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_sales'), 403);

        $branchId = BranchContext::id();

        $customers  = \Modules\People\Entities\Customer::query()
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->orderBy('customer_name')
            ->get();

        $warehouses = \Modules\Product\Entities\Warehouse::query()
            ->when(session('active_branch') && session('active_branch') !== 'all', function ($q) {
                $q->where('branch_id', (int) session('active_branch'));
            })
            ->get();

        // ✅ detect create-from-delivery
        $saleDeliveryId = (int) request()->get('sale_delivery_id', 0);
        $prefillCustomerId = 0;

        // ✅ NEW: locked financial + DP info from SaleOrder (if delivery has sale_order_id)
        $lockedFinancial = null; 
        $lockedSaleOrder = null;

        // ✅ reset cart
        Cart::instance('sale')->destroy();
        $cart = Cart::instance('sale');

        // helper: stock by warehouse
        $getStockLastByWarehouse = function (int $productId, int $warehouseId): int {
            $mutation = \Modules\Mutation\Entities\Mutation::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->latest()
                ->first();

            return $mutation ? (int) $mutation->stock_last : 0;
        };

        // helper: stock all warehouses in active branch
        $getStockLastAllWarehousesInActiveBranch = function (int $productId) use ($getStockLastByWarehouse): int {
            $branchId = session('active_branch');

            if (empty($branchId) || $branchId === 'all') {
                return 0;
            }

            $warehouseIds = \Modules\Product\Entities\Warehouse::where('branch_id', (int) $branchId)
                ->pluck('id')
                ->toArray();

            if (empty($warehouseIds)) return 0;

            $sum = 0;
            foreach ($warehouseIds as $wid) {
                $sum += $getStockLastByWarehouse($productId, (int) $wid);
            }
            return (int) $sum;
        };

        $invoiceItemsSubtotal = 0; // ✅ NEW: subtotal invoice (qty * price)

        // ✅ if from delivery → prefill cart items + qty
        if ($saleDeliveryId > 0) {
            $delivery = \Modules\SaleDelivery\Entities\SaleDelivery::with(['items'])->find($saleDeliveryId);

            if ($delivery) {
                $prefillCustomerId = (int) ($delivery->customer_id ?? 0);

                // ✅ resolve SaleOrder lock
                $saleOrderId = (int) ($delivery->sale_order_id ?? 0);
                if ($saleOrderId > 0) {
                    $lockedSaleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('id', $saleOrderId)
                        ->first();

                    if ($lockedSaleOrder) {
                        $lockedFinancial = [
                            'sale_order_id'        => (int) $lockedSaleOrder->id,
                            'sale_order_reference' => (string) ($lockedSaleOrder->reference ?? ('SO-' . $lockedSaleOrder->id)),

                            // financial lock
                            'tax_percentage'       => (int) ($lockedSaleOrder->tax_percentage ?? 0),
                            'discount_percentage'  => (int) ($lockedSaleOrder->discount_percentage ?? 0),
                            'shipping_amount'      => (int) ($lockedSaleOrder->shipping_amount ?? 0),
                            'fee_amount'           => (int) ($lockedSaleOrder->fee_amount ?? 0),

                            // ✅ NEW: DP info
                            'deposit_percentage'        => (float) ($lockedSaleOrder->deposit_percentage ?? 0),
                            'deposit_amount'            => (int) ($lockedSaleOrder->deposit_amount ?? 0),
                            'deposit_received_amount'   => (int) ($lockedSaleOrder->deposit_received_amount ?? 0),
                            'deposit_payment_method'    => (string) ($lockedSaleOrder->deposit_payment_method ?? ''),
                            'deposit_code'              => (string) ($lockedSaleOrder->deposit_code ?? ''),

                            // total on SO (optional)
                            'sale_order_total_amount'   => (int) ($lockedSaleOrder->total_amount ?? 0),
                        ];
                    }
                }

                // warehouse context dari delivery (boleh null)
                $deliveryWarehouseId = (int) ($delivery->warehouse_id ?? 0);
                $deliveryWarehouseName = null;

                if ($deliveryWarehouseId > 0) {
                    $wh = \Modules\Product\Entities\Warehouse::find($deliveryWarehouseId);
                    $deliveryWarehouseName = $wh?->warehouse_name;
                }

                foreach (($delivery->items ?? []) as $it) {
                    $productId = (int) ($it->product_id ?? 0);
                    if ($productId <= 0) continue;

                    $qty = (int) ($it->quantity ?? 0);
                    if ($qty <= 0) continue;

                    $p = \Modules\Product\Entities\Product::find($productId);

                    $unitPrice = (float) ($it->unit_price ?? ($p->product_price ?? 0));
                    $priceShown = (float) ($it->price ?? $unitPrice);

                    $productTax = (float) ($it->product_tax_amount ?? 0);
                    $discAmt    = (float) ($it->product_discount_amount ?? 0);
                    $discType   = (string) ($it->product_discount_type ?? 'fixed');

                    if ($deliveryWarehouseId > 0) {
                        $stock = $getStockLastByWarehouse($productId, $deliveryWarehouseId);
                        $stockScope = 'warehouse';
                    } else {
                        $stock = $getStockLastAllWarehousesInActiveBranch($productId);
                        $stockScope = 'branch';
                    }

                    $subTotal = (float) ($priceShown * $qty);
                    $invoiceItemsSubtotal += (int) round($subTotal); // ✅ NEW

                    $cart->add([
                        'id'      => $productId,
                        'name'    => (string) ($it->product_name ?? ($p->product_name ?? '-')),
                        'qty'     => $qty,
                        'price'   => $priceShown,
                        'weight'  => 1,
                        'options' => [
                            'sub_total'             => $subTotal,
                            'code'                  => (string) ($it->product_code ?? ($p->product_code ?? 'UNKNOWN')),
                            'unit'                  => (string) ($it->product_unit ?? ($p->product_unit ?? '')),
                            'stock'                 => (int) $stock,
                            'stock_scope'           => $stockScope,

                            'warehouse_id'          => $deliveryWarehouseId,
                            'warehouse_name'        => $deliveryWarehouseName,

                            'product_tax'           => $productTax,
                            'unit_price'            => $unitPrice,
                            'product_discount'      => $discAmt,
                            'product_discount_type' => $discType,
                            'product_cost'          => (float) ($it->product_cost ?? ($p->product_cost ?? 0)),
                        ],
                    ]);
                }
            }
        }

        // ✅ NEW: compute DP allocated for this invoice (pro-rata) for UI hint
        if (!empty($lockedFinancial) && !empty($lockedFinancial['sale_order_id'])) {
            $soId = (int) $lockedFinancial['sale_order_id'];

            $soSubtotal = (int) DB::table('sale_order_items')
                ->where('sale_order_id', $soId)
                ->selectRaw('SUM(COALESCE(quantity,0) * COALESCE(price,0)) as s')
                ->value('s');

            $ratio = 1.0;
            if ($soSubtotal > 0 && $invoiceItemsSubtotal > 0) {
                $ratio = $invoiceItemsSubtotal / $soSubtotal;
                if ($ratio < 0) $ratio = 0;
                if ($ratio > 1) $ratio = 1;
            }

            $dpTotal = (int) ($lockedFinancial['deposit_received_amount'] ?? 0);
            $dpAllocated = (int) round($dpTotal * $ratio);
            if ($dpAllocated < 0) $dpAllocated = 0;
            if ($dpAllocated > $dpTotal) $dpAllocated = $dpTotal;

            // ✅ compute "suggested remaining" based on invoice computed grand (estimate)
            $taxPct  = (int) ($lockedFinancial['tax_percentage'] ?? 0);
            $discPct = (int) ($lockedFinancial['discount_percentage'] ?? 0);

            $taxPct  = max(0, min(100, $taxPct));
            $discPct = max(0, min(100, $discPct));

            $taxAmt  = (int) round($invoiceItemsSubtotal * ($taxPct / 100));
            $discAmt = (int) round($invoiceItemsSubtotal * ($discPct / 100));

            // note: fee/shipping lock at SO level, but for UI we show "as per SO"
            $fee  = (int) ($lockedFinancial['fee_amount'] ?? 0);
            $ship = (int) ($lockedFinancial['shipping_amount'] ?? 0);

            $invoiceEstimatedGrand = (int) round($invoiceItemsSubtotal + $taxAmt - $discAmt + max(0, $fee) + max(0, $ship));
            $suggestedPayNow = max(0, $invoiceEstimatedGrand - $dpAllocated);

            $lockedFinancial['invoice_items_subtotal'] = (int) $invoiceItemsSubtotal;
            $lockedFinancial['sale_order_items_subtotal'] = (int) $soSubtotal;
            $lockedFinancial['dp_allocated_for_this_invoice'] = (int) $dpAllocated;
            $lockedFinancial['invoice_estimated_grand_total'] = (int) $invoiceEstimatedGrand;
            $lockedFinancial['suggested_pay_now'] = (int) $suggestedPayNow;
        }

        return view('sale::create', [
            'customers'         => $customers,
            'warehouses'        => $warehouses,
            'prefillCustomerId' => $prefillCustomerId,
            'lockedFinancial'   => $lockedFinancial,
        ]);
    }

    public function store(StoreSaleRequest $request)
    {
        abort_if(Gate::denies('create_sales'), 403);

        try {
            DB::transaction(function () use ($request) {
                $branchId = BranchContext::id();

                $customer = Customer::query()
                    ->where('id', $request->customer_id)
                    ->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->firstOrFail();

                $cartItems = collect(Cart::instance('sale')->content());
                if ($cartItems->isEmpty()) {
                    throw new \RuntimeException('Cart is empty. Please add items first.');
                }

                // ======================================================
                // ✅ detect invoice-from-delivery + lock delivery
                // ======================================================
                $saleDeliveryId = (int) $request->get('sale_delivery_id', 0);
                $fromDelivery = $saleDeliveryId > 0;
                $lockedDelivery = null;

                // ✅ SaleOrder lock
                $lockedSaleOrder = null;
                $lockedTaxPct = null;
                $lockedDiscPct = null;
                $lockedShipping = null;
                $lockedFee = null;

                // ✅ NEW: for pro-rata
                $saleOrderSubtotal = 0;
                $invoiceItemsSubtotal = 0;

                if ($fromDelivery) {
                    $lockedDelivery = SaleDelivery::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->with(['items'])
                        ->where('id', $saleDeliveryId)
                        ->where('branch_id', $branchId)
                        ->firstOrFail();

                    $st = strtolower(trim((string) ($lockedDelivery->getRawOriginal('status') ?? $lockedDelivery->status ?? 'pending')));
                    if ($st !== 'confirmed') {
                        throw new \RuntimeException('Sale Delivery must be CONFIRMED to create invoice.');
                    }

                    if (!empty($lockedDelivery->sale_id)) {
                        throw new \RuntimeException('Invoice already exists for this Sale Delivery.');
                    }

                    if ((int)($lockedDelivery->customer_id ?? 0) !== (int)$customer->id) {
                        throw new \RuntimeException('Customer mismatch with Sale Delivery.');
                    }

                    // ✅ if delivery has sale_order_id -> lock from SO
                    $saleOrderId = (int) ($lockedDelivery->sale_order_id ?? 0);
                    if ($saleOrderId > 0) {
                        $lockedSaleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                            ->where('branch_id', $branchId)
                            ->where('id', $saleOrderId)
                            ->first();

                        if ($lockedSaleOrder) {
                            $lockedTaxPct = (int) ($lockedSaleOrder->tax_percentage ?? 0);
                            $lockedDiscPct = (int) ($lockedSaleOrder->discount_percentage ?? 0);
                            $lockedShipping = (int) ($lockedSaleOrder->shipping_amount ?? 0);
                            $lockedFee = (int) ($lockedSaleOrder->fee_amount ?? 0);

                            // ✅ NEW: sale order items subtotal for pro-rata
                            $saleOrderSubtotal = (int) DB::table('sale_order_items')
                                ->where('sale_order_id', (int) $lockedSaleOrder->id)
                                ->selectRaw('SUM(COALESCE(quantity,0) * COALESCE(price,0)) as s')
                                ->value('s');
                        }
                    }
                }

                // ======================================================
                // ✅ compute items subtotal & qty server-side
                // ======================================================
                $itemsSubtotal = 0;
                $totalQty = 0;
                foreach ($cartItems as $cart_item) {
                    $qty = (int) ($cart_item->qty ?? 0);
                    $price = (int) ($cart_item->price ?? 0);
                    if ($qty <= 0) continue;

                    $totalQty += $qty;
                    $itemsSubtotal += ($qty * max(0, $price));
                }
                $invoiceItemsSubtotal = (int) $itemsSubtotal;

                // ======================================================
                // ✅ effective financial (locked when from delivery + sale order)
                // ======================================================
                $effectiveTaxPct = (int) ($lockedTaxPct ?? (int) $request->tax_percentage);
                $effectiveDiscPct = (int) ($lockedDiscPct ?? (int) $request->discount_percentage);

                // base fee/shipping from lock or request
                $baseShipping = (int) ($lockedShipping ?? (int) $request->shipping_amount);
                $baseFee = (int) ($lockedFee ?? (int) $request->fee_amount);

                // clamp pct
                $effectiveTaxPct = max(0, min(100, $effectiveTaxPct));
                $effectiveDiscPct = max(0, min(100, $effectiveDiscPct));

                $baseShipping = max(0, $baseShipping);
                $baseFee = max(0, $baseFee);

                // ✅ NEW: pro-rata fee & shipping if sale order exists and subtotal known
                $effectiveShipping = $baseShipping;
                $effectiveFee = $baseFee;

                if ($fromDelivery && $lockedSaleOrder && $saleOrderSubtotal > 0 && $invoiceItemsSubtotal > 0) {
                    $ratio = $invoiceItemsSubtotal / $saleOrderSubtotal;
                    if ($ratio < 0) $ratio = 0;
                    if ($ratio > 1) $ratio = 1;

                    $effectiveShipping = (int) round($baseShipping * $ratio);
                    $effectiveFee = (int) round($baseFee * $ratio);

                    $effectiveShipping = max(0, $effectiveShipping);
                    $effectiveFee = max(0, $effectiveFee);
                }

                $taxAmount = (int) round($itemsSubtotal * ($effectiveTaxPct / 100));
                $discountAmount = (int) round($itemsSubtotal * ($effectiveDiscPct / 100));
                $computedGrandTotal = (int) round($itemsSubtotal + $taxAmount - $discountAmount + $effectiveFee + $effectiveShipping);

                // ======================================================
                // ✅ paid/due/payment_status
                // ======================================================
                $paidAmount = (int) ($request->paid_amount ?? 0);
                $paidAmount = max(0, $paidAmount);

                $due_amount = $computedGrandTotal - $paidAmount;

                if ($due_amount == $computedGrandTotal) {
                    $payment_status = 'Unpaid';
                } elseif ($due_amount > 0) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                    $due_amount = 0;
                }

                if ($request->quotation_id) {
                    $quotation = Quotation::findOrFail($request->quotation_id);
                    $quotation->update(['status' => 'Sent']);
                }

                $total_cost = 0;

                // ==========================================
                // ✅ CREATE INVOICE (SALE)
                // ==========================================
                $saleData = [
                    'date' => $request->date,
                    'license_number' => $request->car_number_plate,
                    'sale_from' => $request->sale_from,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->customer_name,

                    // ✅ locked/effective
                    'tax_percentage' => $effectiveTaxPct,
                    'discount_percentage' => $effectiveDiscPct,
                    'shipping_amount' => $effectiveShipping,
                    'fee_amount' => $effectiveFee,

                    'paid_amount' => $paidAmount,
                    'total_amount' => $computedGrandTotal,
                    'total_quantity' => $totalQty,

                    'due_amount' => (int) $due_amount,
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,
                    'note' => $request->note,

                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                ];

                if (Schema::hasColumn('sales', 'branch_id')) {
                    $saleData['branch_id'] = $branchId;
                }

                if (Schema::hasColumn('sales', 'warehouse_id')) {
                    $saleData['warehouse_id'] = null;
                }

                $sale = Sale::create($saleData);

                // ==========================================
                // ✅ Create SaleDetails (warehouse null)
                // ==========================================
                foreach ($cartItems as $cart_item) {
                    $qty = (int) ($cart_item->qty ?? 0);
                    $price = (int) ($cart_item->price ?? 0);
                    if ($qty <= 0) continue;

                    $total_cost += (int) ($cart_item->options->product_cost ?? 0);

                    $saleDetailData = [
                        'sale_id' => $sale->id,
                        'product_id' => (int) $cart_item->id,
                        'product_name' => (string) $cart_item->name,
                        'product_code' => (string) ($cart_item->options->code ?? ''),
                        'product_cost' => (int) ($cart_item->options->product_cost ?? 0),

                        'warehouse_id' => null,

                        'quantity' => $qty,
                        'price' => max(0, $price),
                        'unit_price' => (int) ($cart_item->options->unit_price ?? 0),

                        'sub_total' => (int) ($qty * max(0, $price)),

                        'product_discount_amount' => (int) ($cart_item->options->product_discount ?? 0),
                        'product_discount_type' => (string) ($cart_item->options->product_discount_type ?? 'fixed'),
                        'product_tax_amount' => (int) ($cart_item->options->product_tax ?? 0),
                    ];

                    if (Schema::hasColumn('sale_details', 'branch_id')) {
                        $saleDetailData['branch_id'] = $branchId;
                    }

                    SaleDetails::create($saleDetailData);
                }

                // ======================================================
                // ✅ link invoice to delivery OR auto-create delivery
                // ======================================================
                if ($fromDelivery && $lockedDelivery) {
                    $lockedDelivery->update([
                        'sale_id' => (int) $sale->id,
                    ]);
                } else {
                    $this->autoCreateSaleDeliveryFromSale(
                        $sale,
                        $branchId,
                        $request->quotation_id ? (int) $request->quotation_id : null,
                        null
                    );
                }

                Cart::instance('sale')->destroy();

                // ==========================================
                // ✅ Accounting Transaction
                // ==========================================
                if ($total_cost <= 0) {
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
                    $depositCode = (string) ($request->deposit_code ?? '');
                    $depositCode = trim($depositCode);

                    if ($depositCode === '' || $depositCode === '-') {
                        throw new \RuntimeException("Deposit To wajib dipilih jika Amount Received > 0.");
                    }

                    $paymentData = [
                        'date' => $sale->date,
                        'reference' => 'INV/' . $sale->reference,
                        'amount' => (int) $sale->paid_amount,
                        'sale_id' => (int) $sale->id,
                        'payment_method' => $request->payment_method,
                        'deposit_code' => $depositCode,
                    ];

                    if (Schema::hasColumn('sale_payments', 'branch_id')) {
                        $paymentData['branch_id'] = $branchId;
                    }

                    $created_payment = SalePayment::create($paymentData);

                    Helper::addNewTransaction([
                        'date' => $sale->date,
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

        } catch (\Throwable $e) {
            toast($e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }

    private function autoCreateSaleDeliveryFromSale(
        Sale $sale,
        int $branchId,
        ?int $quotationId = null,
        ?int $saleOrderId = null
    ): void {
        // ambil detail invoice
        $details = SaleDetails::query()
            ->where('sale_id', (int) $sale->id)
            ->get();

        if ($details->isEmpty()) {
            throw new \RuntimeException('Cannot auto create Sale Delivery because invoice has no items.');
        }

        // ✅ FLOW BARU:
        // - Tidak ada lagi validasi "detail wajib punya warehouse"
        // - Tidak group by warehouse
        // - 1 sale => 1 sale delivery (warehouse_id null)
        $exists = SaleDelivery::query()
            ->where('branch_id', $branchId)
            ->where('sale_id', (int) $sale->id)
            ->exists();

        if ($exists) {
            return;
        }

        $deliveryData = [
            'branch_id' => $branchId,
            'quotation_id' => $quotationId,
            'sale_id' => (int) $sale->id,
            'sale_order_id' => null, // invoice-first
            'customer_id' => (int) $sale->customer_id,
            'date' => (string) $sale->getRawOriginal('date'),
            'status' => 'pending',
            'note' => 'Auto generated from Invoice #' . ($sale->reference ?? $sale->id)
                . ' | Payment: ' . ((string)($sale->payment_status ?? '')),
            'created_by' => auth()->id(),
        ];

        // kalau kolom warehouse_id ada, set null
        if (Schema::hasColumn('sale_deliveries', 'warehouse_id')) {
            $deliveryData['warehouse_id'] = null;
        }

        $delivery = SaleDelivery::create($deliveryData);

        // agregasi per product_id
        $grouped = $details->groupBy('product_id');
        foreach ($grouped as $productId => $productRows) {
            $qty = (int) $productRows->sum('quantity');
            if ($qty <= 0) continue;

            $price = (int) ($productRows->first()->price ?? 0);

            SaleDeliveryItem::create([
                'sale_delivery_id' => (int) $delivery->id,
                'product_id' => (int) $productId,
                'quantity' => $qty,
                'price' => $price > 0 ? $price : null,
            ]);
        }

        if (empty($delivery->reference)) {
            $delivery->update([
                'reference' => make_reference_id('SDO', (int) $delivery->id),
            ]);
        }
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

        $saleDeliveries = SaleDelivery::query()
            ->where('branch_id', $branchId)
            ->where('sale_id', (int) $sale->id)
            ->orderByDesc('id')
            ->get();

        $saleOrderDepositInfo = null;

        try {
            $saleOrderId = (int) ($saleDeliveries->pluck('sale_order_id')->filter()->first() ?? 0);

            if ($saleOrderId > 0) {
                $saleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                    ->where('branch_id', $branchId)
                    ->where('id', $saleOrderId)
                    ->first();

                if ($saleOrder) {
                    // ✅ pakai DP Received (actual)
                    $dpTotal = (int) ($saleOrder->deposit_received_amount ?? 0);

                    if ($dpTotal > 0) {
                        // invoiceSubtotal = sum(qty * price) dari sale_details
                        $invoiceSubtotal = 0;
                        foreach ($sale->saleDetails as $d) {
                            $qty = (int) ($d->quantity ?? 0);
                            $price = (int) ($d->price ?? 0);
                            if ($qty > 0 && $price >= 0) {
                                $invoiceSubtotal += ($qty * $price);
                            }
                        }

                        // soSubtotal = sum(qty * price) dari sale_order_items
                        $soSubtotal = (int) \Illuminate\Support\Facades\DB::table('sale_order_items')
                            ->where('sale_order_id', (int) $saleOrder->id)
                            ->selectRaw('SUM(COALESCE(quantity,0) * COALESCE(price,0)) as s')
                            ->value('s');

                        if ($soSubtotal > 0 && $invoiceSubtotal > 0) {
                            $allocated = (int) round($dpTotal * ($invoiceSubtotal / $soSubtotal));

                            if ($allocated < 0) $allocated = 0;
                            if ($allocated > $dpTotal) $allocated = $dpTotal;

                            $pct = (int) round(($invoiceSubtotal / $soSubtotal) * 100);

                            $saleOrderDepositInfo = [
                                'sale_order_reference' => (string) ($saleOrder->reference ?? ('SO-'.$saleOrder->id)),
                                'deposit_total' => $dpTotal,
                                'allocated' => $allocated,
                                'ratio_percent' => $pct,
                            ];
                        } else {
                            $saleOrderDepositInfo = [
                                'sale_order_reference' => (string) ($saleOrder->reference ?? ('SO-'.$saleOrder->id)),
                                'deposit_total' => $dpTotal,
                                'allocated' => $dpTotal,
                                'ratio_percent' => null,
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $saleOrderDepositInfo = null;
        }

        return view('sale::show', compact('sale', 'customer', 'saleDeliveries', 'saleOrderDepositInfo'));
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

                    // ✅ warehouse tidak dipakai lagi di invoice
                    'warehouse_id'=> null,

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

            $saleUpdateData = [
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
            ];

            if (Schema::hasColumn('sales', 'warehouse_id')) {
                $saleUpdateData['warehouse_id'] = null;
            }

            $sale->update($saleUpdateData);

            foreach (Cart::instance('sale')->content() as $cart_item) {
                SaleDetails::create([
                    'sale_id' => $sale->id,
                    'product_id' => (int) $cart_item->id,
                    'product_name' => (string) $cart_item->name,
                    'product_code' => (string) ($cart_item->options->code ?? ''),
                    'product_cost'=> (int) ($cart_item->options->product_cost ?? 0),

                    // ✅ warehouse tidak dipakai lagi
                    'warehouse_id' => null,

                    'quantity' => (int) $cart_item->qty,
                    'price' => (int) $cart_item->price,
                    'unit_price' => (int) ($cart_item->options->unit_price ?? 0),
                    'sub_total' => (int) ($cart_item->options->sub_total ?? 0),
                    'product_discount_amount' => (int) ($cart_item->options->product_discount ?? 0),
                    'product_discount_type' => (string) ($cart_item->options->product_discount_type ?? 'fixed'),
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
