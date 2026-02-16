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

// ✅ SaleOrder anchor
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleController extends Controller
{
    public function index(SalesDataTable $dataTable) {
        abort_if(Gate::denies('access_sales'), 403);
        return $dataTable->render('sale::index');
    }

    private function calcCumulativeAlloc(int $total, int $base, int $prevBase, int $currAddBase): int
    {
        $total = max(0, $total);
        $base = max(0, $base);
        $prevBase = max(0, $prevBase);
        $currAddBase = max(0, $currAddBase);

        if ($total <= 0 || $base <= 0 || $currAddBase <= 0) {
            return 0;
        }

        $prevCum = intdiv($total * $prevBase, $base);
        $targetCum = intdiv($total * ($prevBase + $currAddBase), $base);

        $alloc = $targetCum - $prevCum;
        if ($alloc < 0) $alloc = 0;

        $remaining = $total - $prevCum;
        if ($alloc > $remaining) $alloc = max(0, $remaining);

        return (int) $alloc;
    }

    private function getPrevInvoiceItemsSubtotalForSO(int $saleOrderId, int $branchId): int
    {
        $prev = (int) DB::table('sale_details as sd')
            ->join('sales as s', 's.id', '=', 'sd.sale_id')
            ->join('sale_deliveries as del', 'del.sale_id', '=', 's.id')
            ->where('del.sale_order_id', $saleOrderId)
            ->where('del.branch_id', $branchId)
            ->whereNotNull('del.sale_id')
            ->selectRaw('SUM(COALESCE(sd.quantity,0) * COALESCE(sd.price,0)) as s')
            ->value('s');

        return max(0, $prev);
    }

    private function getPrevInvoiceGrandTotalForSO(int $saleOrderId, int $branchId): int
    {
        $prev = (int) DB::table('sales as s')
            ->join('sale_deliveries as del', 'del.sale_id', '=', 's.id')
            ->where('del.sale_order_id', $saleOrderId)
            ->where('del.branch_id', $branchId)
            ->whereNotNull('del.sale_id')
            ->sum('s.total_amount');

        return max(0, $prev);
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

        $saleDeliveryId = (int) request()->get('sale_delivery_id', 0);
        $prefillCustomerId = 0;

        $lockedFinancial = null;
        $lockedSaleOrder = null;

        Cart::instance('sale')->destroy();
        $cart = Cart::instance('sale');

        $getStockLastByWarehouse = function (int $productId, int $warehouseId): int {
            $mutation = \Modules\Mutation\Entities\Mutation::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->latest()
                ->first();

            return $mutation ? (int) $mutation->stock_last : 0;
        };

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

        // =========================
        // Helpers: equal split (shipping/fee) by invoice count
        // =========================
        $splitEven = function (int $total, int $count, int $index1Based): int {
            $total = max(0, $total);
            $count = max(1, $count);
            $index1Based = max(1, $index1Based);

            $base = intdiv($total, $count);
            $rem  = $total - ($base * $count);

            $extra = ($index1Based <= $rem) ? 1 : 0;
            return (int) ($base + $extra);
        };

        $getDeliverySplitContext = function (int $saleOrderId, int $branchId, int $currentDeliveryId): array {
            $rows = \Modules\SaleDelivery\Entities\SaleDelivery::withoutGlobalScopes()
                ->where('sale_order_id', $saleOrderId)
                ->where('branch_id', $branchId)
                ->whereRaw('LOWER(COALESCE(status, "")) = ?', ['confirmed'])
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            $count = count($rows);
            if ($count <= 0) return ['count' => 1, 'index' => 1];

            $index = array_search($currentDeliveryId, $rows, true);
            $index1Based = ($index === false) ? 1 : ((int)$index + 1);

            return ['count' => $count, 'index' => $index1Based];
        };

        // invoice items subtotal (qty * shown price)  => NOTE: shown price kita pakai NET (SO price)
        $invoiceItemsSubtotal = 0;
        $saleOrderGrandTotal = 0;

        $invoiceEstimatedGrand = 0;
        $dpAllocated = 0;
        $suggestedPayNow = 0;

        // INFO discount (buat tampilan di kolom item / info SO), TAPI TIDAK dipakai ngurangin summary lagi
        $deliveryDiscountInfoTotal = 0.0;

        $splitCount = 1;
        $splitIndex = 1;

        if ($saleDeliveryId > 0) {
            $delivery = \Modules\SaleDelivery\Entities\SaleDelivery::with(['items'])->find($saleDeliveryId);

            if ($delivery) {
                $prefillCustomerId = (int) ($delivery->customer_id ?? 0);

                $saleOrderId = (int) ($delivery->sale_order_id ?? 0);
                if ($saleOrderId > 0) {
                    $lockedSaleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('id', $saleOrderId)
                        ->first();

                    if ($lockedSaleOrder) {
                        $saleOrderGrandTotal = (int) ($lockedSaleOrder->total_amount ?? 0);

                        $ctx = $getDeliverySplitContext((int)$lockedSaleOrder->id, (int)$branchId, (int)$delivery->id);
                        $splitCount = (int) $ctx['count'];
                        $splitIndex = (int) $ctx['index'];

                        $discountPct = (float) ($lockedSaleOrder->discount_percentage ?? 0);
                        $discountDiffAmt = (float) ($lockedSaleOrder->discount_amount ?? 0);

                        $lockedFinancial = [
                            'sale_order_id'        => (int) $lockedSaleOrder->id,
                            'sale_order_reference' => (string) ($lockedSaleOrder->reference ?? ('SO-' . $lockedSaleOrder->id)),

                            'tax_percentage'       => (float) ($lockedSaleOrder->tax_percentage ?? 0),
                            'tax_amount'           => (float) ($lockedSaleOrder->tax_amount ?? 0),

                            'discount_percentage'  => (float) ($lockedSaleOrder->discount_percentage ?? 0),

                            'shipping_amount'      => (float) ($lockedSaleOrder->shipping_amount ?? 0),
                            'fee_amount'           => (float) ($lockedSaleOrder->fee_amount ?? 0),

                            'discount_info_percentage' => (float) $discountPct,
                            'discount_info_amount'     => (float) $discountDiffAmt,

                            'deposit_percentage'        => (float) ($lockedSaleOrder->deposit_percentage ?? 0),
                            'deposit_amount'            => (float) ($lockedSaleOrder->deposit_amount ?? 0),
                            'deposit_received_amount'   => (float) ($lockedSaleOrder->deposit_received_amount ?? 0),
                            'deposit_payment_method'    => (string) ($lockedSaleOrder->deposit_payment_method ?? ''),
                            'deposit_code'              => (string) ($lockedSaleOrder->deposit_code ?? ''),

                            'sale_order_subtotal_amount' => (float) ($lockedSaleOrder->subtotal_amount ?? 0),
                            'sale_order_total_amount'    => (float) ($lockedSaleOrder->total_amount ?? 0),

                            'split_count' => $splitCount,
                            'split_index' => $splitIndex,
                        ];
                    }
                }

                // ✅ build map SO item by product (source harga & discount)
                $soItemByProduct = [];
                if (!empty($lockedSaleOrder) && (int)$lockedSaleOrder->id > 0) {
                    $soItems = \Modules\SaleOrder\Entities\SaleOrderItem::query()
                        ->where('sale_order_id', (int)$lockedSaleOrder->id)
                        ->get();

                    foreach ($soItems as $row) {
                        $pid = (int) ($row->product_id ?? 0);
                        if ($pid > 0) $soItemByProduct[$pid] = $row;
                    }
                }

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

                    $soRow = $soItemByProduct[$productId] ?? null;

                    // ✅ PENTING:
                    // Sell Unit Price = NET (ambil dari SO price kalau ada)
                    $unitPrice  = (float) (
                        $soRow?->unit_price
                        ?? $it->unit_price
                        ?? ($p->product_price ?? 0)
                    );

                    $priceShown = (float) (
                        $soRow?->price
                        ?? $it->price
                        ?? $unitPrice
                    );

                    // discount info (buat kolom item), TAPI JANGAN DIPAKAI NGURANGIN SUMMARY LAGI
                    $discAmt  = (float) (
                        $soRow?->product_discount_amount
                        ?? $it->product_discount_amount
                        ?? 0
                    );
                    $discType = strtolower((string) (
                        $soRow?->product_discount_type
                        ?? $it->product_discount_type
                        ?? 'fixed'
                    ));

                    // fallback implicit diff (kalau net < unit)
                    if ($discAmt <= 0 && $unitPrice > 0 && $unitPrice > $priceShown) {
                        $discAmt  = (float) ($unitPrice - $priceShown);
                        $discType = 'fixed';
                    }

                    // INFO total discount (buat info saja)
                    if ($discAmt > 0) {
                        if ($discType === 'fixed') {
                            $deliveryDiscountInfoTotal += ($discAmt * $qty);
                        } else {
                            $deliveryDiscountInfoTotal += (($unitPrice * $qty) * ($discAmt / 100));
                        }
                    }

                    $productTax = (float) ($it->product_tax_amount ?? 0);

                    if ($deliveryWarehouseId > 0) {
                        $stock = $getStockLastByWarehouse($productId, $deliveryWarehouseId);
                        $stockScope = 'warehouse';
                    } else {
                        $stock = $getStockLastAllWarehousesInActiveBranch($productId);
                        $stockScope = 'branch';
                    }

                    // subtotal pakai NET price (priceShown)
                    $subTotal = (float) ($priceShown * $qty);
                    $invoiceItemsSubtotal += (int) round($subTotal);

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

                            // ✅ discount ditampilkan di kolom item
                            'product_discount'      => $discAmt,
                            'product_discount_type' => $discType,

                            'product_cost'          => (float) ($it->product_cost ?? ($p->product_cost ?? 0)),
                        ],
                    ]);
                }
            }
        }

        // ==========================================
        // ✅ LOCKED CALC FOR SUMMARY UI (NO DOUBLE DISCOUNT)
        // ==========================================
        if (!empty($lockedSaleOrder) && !empty($lockedFinancial) && (int)$lockedSaleOrder->id > 0) {

            $soSubtotal = (int) ($lockedSaleOrder->subtotal_amount ?? 0);
            $soSubtotal = max(0, $soSubtotal);

            $soTaxAmount = (int) ($lockedSaleOrder->tax_amount ?? 0);

            $soShip = (int) ($lockedSaleOrder->shipping_amount ?? 0);
            $soFee  = (int) ($lockedSaleOrder->fee_amount ?? 0);

            // TAX prorata
            $prevSubtotal = $this->getPrevInvoiceItemsSubtotalForSO((int)$lockedSaleOrder->id, (int)$branchId);
            $taxAlloc  = $this->calcCumulativeAlloc($soTaxAmount, $soSubtotal, $prevSubtotal, $invoiceItemsSubtotal);

            // shipping/fee split
            $shipAlloc = $splitEven($soShip, $splitCount, $splitIndex);
            $feeAlloc  = $splitEven($soFee,  $splitCount, $splitIndex);

            // ✅ PENTING: DISCOUNT JANGAN DIKURANGI DI SUMMARY
            // Karena item price sudah NET (priceShown sudah diskon).
            $discAlloc = 0;

            $invoiceEstimatedGrand = (int) ($invoiceItemsSubtotal + $taxAlloc + $shipAlloc + $feeAlloc - $discAlloc);
            if ($invoiceEstimatedGrand < 0) $invoiceEstimatedGrand = 0;

            // DP allocated = grand_after_discount (di sini grand sudah net)
            $depositPct = (float) ($lockedSaleOrder->deposit_percentage ?? 0);
            if ($depositPct < 0) $depositPct = 0;
            if ($depositPct > 100) $depositPct = 100;

            $dpTotalReceived = (int) ($lockedSaleOrder->deposit_received_amount ?? 0);
            $dpAllocated = (int) round($invoiceEstimatedGrand * ($depositPct / 100));

            if ($dpTotalReceived > 0 && $dpAllocated > $dpTotalReceived) {
                $dpAllocated = $dpTotalReceived;
            }
            if ($dpAllocated < 0) $dpAllocated = 0;

            $suggestedPayNow = max(0, $invoiceEstimatedGrand - $dpAllocated);

            $lockedFinancial['invoice_items_subtotal'] = (int) $invoiceItemsSubtotal;
            $lockedFinancial['sale_order_grand_total'] = (int) $saleOrderGrandTotal;

            $lockedFinancial['invoice_estimated_grand_total'] = (int) $invoiceEstimatedGrand;

            $lockedFinancial['tax_invoice_est']  = (int) $taxAlloc;
            $lockedFinancial['ship_invoice_est'] = (int) $shipAlloc;
            $lockedFinancial['fee_invoice_est']  = (int) $feeAlloc;

            // ✅ BIAR ROW DISCOUNT SUMMARY NGGAK MUNCUL
            $lockedFinancial['discount_info_invoice_est'] = 0;

            // kalau kamu masih mau tampilkan info % dan angka discount (bukan untuk dikurangin)
            $lockedFinancial['discount_info_display_total'] = (int) round(max(0, (float)$deliveryDiscountInfoTotal));

            $lockedFinancial['dp_allocated_for_this_invoice'] = (int) $dpAllocated;
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
                // Detect invoice-from-delivery + lock delivery
                // ======================================================
                $saleDeliveryId = (int) $request->get('sale_delivery_id', 0);
                $fromDelivery = $saleDeliveryId > 0;

                /** @var \Modules\SaleDelivery\Entities\SaleDelivery|null $lockedDelivery */
                $lockedDelivery = null;

                /** @var \Modules\SaleOrder\Entities\SaleOrder|null $lockedSaleOrder */
                $lockedSaleOrder = null;

                // for split shipping/fee equally across invoices (deliveries)
                $splitCount = 1;
                $splitIndex = 1;

                // info only
                $deliveryDiscountInfoTotal = 0.0;

                $splitEven = function (int $total, int $count, int $index1Based): int {
                    $total = max(0, $total);
                    $count = max(1, $count);
                    $index1Based = max(1, $index1Based);

                    $base = intdiv($total, $count);
                    $rem  = $total - ($base * $count);

                    $extra = ($index1Based <= $rem) ? 1 : 0;
                    return (int) ($base + $extra);
                };

                $getDeliverySplitContext = function (int $saleOrderId, int $branchId, int $currentDeliveryId): array {
                    $rows = \Modules\SaleDelivery\Entities\SaleDelivery::withoutGlobalScopes()
                        ->where('sale_order_id', $saleOrderId)
                        ->where('branch_id', $branchId)
                        ->whereRaw('LOWER(COALESCE(status, "")) = ?', ['confirmed'])
                        ->orderBy('id')
                        ->pluck('id')
                        ->toArray();

                    $count = count($rows);
                    if ($count <= 0) return ['count' => 1, 'index' => 1];

                    $idx = array_search($currentDeliveryId, $rows, true);
                    $index1Based = ($idx === false) ? 1 : ((int)$idx + 1);

                    return ['count' => $count, 'index' => $index1Based];
                };

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

                    $saleOrderId = (int) ($lockedDelivery->sale_order_id ?? 0);
                    if ($saleOrderId <= 0) {
                        throw new \RuntimeException('Sale Delivery does not have Sale Order reference.');
                    }

                    $lockedSaleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('id', $saleOrderId)
                        ->firstOrFail();

                    // split context (equal split shipping/fee per invoice)
                    $ctx = $getDeliverySplitContext((int)$lockedSaleOrder->id, (int)$branchId, (int)$lockedDelivery->id);
                    $splitCount = (int) $ctx['count'];
                    $splitIndex = (int) $ctx['index'];

                    // info only: compute discount from SO items (for note)
                    $soItems = \Modules\SaleOrder\Entities\SaleOrderItem::query()
                        ->where('sale_order_id', (int)$lockedSaleOrder->id)
                        ->get()
                        ->keyBy('product_id');

                    foreach (($lockedDelivery->items ?? []) as $it) {
                        $pid = (int) ($it->product_id ?? 0);
                        $qty = (int) ($it->quantity ?? 0);
                        if ($pid <= 0 || $qty <= 0) continue;

                        $soRow = $soItems->get($pid);

                        $unit = (float) ($soRow?->unit_price ?? $it->unit_price ?? 0);
                        $price = (float) ($soRow?->price ?? $it->price ?? $unit);

                        $dAmt  = (float) ($soRow?->product_discount_amount ?? $it->product_discount_amount ?? 0);
                        $dType = strtolower((string) ($soRow?->product_discount_type ?? $it->product_discount_type ?? 'fixed'));

                        if ($dAmt <= 0 && $unit > 0 && $unit > $price) {
                            $dAmt = (float) ($unit - $price);
                            $dType = 'fixed';
                        }

                        if ($dAmt > 0) {
                            $deliveryDiscountInfoTotal += ($dType === 'fixed')
                                ? ($dAmt * $qty)
                                : (($unit * $qty) * ($dAmt / 100));
                        }
                    }
                }

                // ======================================================
                // Compute invoice items subtotal & qty (SERVER TRUTH)
                // IMPORTANT: price di cart sudah NET (sell price).
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

                $itemsSubtotal = max(0, (int) $itemsSubtotal);

                // ======================================================
                // Defaults (non-locked)
                // ======================================================
                $effectiveTaxPct  = round((float) ($request->tax_percentage ?? 0), 2);
                $effectiveDiscPct = round((float) ($request->discount_percentage ?? 0), 2);

                if ($effectiveTaxPct < 0) $effectiveTaxPct = 0;
                if ($effectiveTaxPct > 100) $effectiveTaxPct = 100;

                if ($effectiveDiscPct < 0) $effectiveDiscPct = 0;
                if ($effectiveDiscPct > 100) $effectiveDiscPct = 100;

                $effectiveShipping = (int) ($request->shipping_amount ?? 0);
                $effectiveFee      = (int) ($request->fee_amount ?? 0);

                $taxAmount      = (int) floor($itemsSubtotal * ($effectiveTaxPct / 100));
                $discountAmount = (int) floor($itemsSubtotal * ($effectiveDiscPct / 100));

                $computedGrandTotal = (int) ($itemsSubtotal + $taxAmount - $discountAmount + $effectiveFee + $effectiveShipping);
                if ($computedGrandTotal < 0) $computedGrandTotal = 0;

                // ======================================================
                // LOCKED BY SALE ORDER (invoice from delivery)
                // ======================================================
                $dpAllocatedForThisInvoice = 0;

                if ($fromDelivery && $lockedSaleOrder) {
                    $soSubtotal = max(0, (int) ($lockedSaleOrder->subtotal_amount ?? 0));
                    $soGrand    = max(0, (int) ($lockedSaleOrder->total_amount ?? 0));

                    $soTaxAmount = (int) ($lockedSaleOrder->tax_amount ?? 0);
                    $soShip      = (int) ($lockedSaleOrder->shipping_amount ?? 0);
                    $soFee       = (int) ($lockedSaleOrder->fee_amount ?? 0);

                    $prevSubtotal = $this->getPrevInvoiceItemsSubtotalForSO((int) $lockedSaleOrder->id, (int) $branchId);

                    $allocTax  = $this->calcCumulativeAlloc($soTaxAmount, $soSubtotal, $prevSubtotal, $itemsSubtotal);
                    $allocShip = $splitEven($soShip, $splitCount, $splitIndex);
                    $allocFee  = $splitEven($soFee,  $splitCount, $splitIndex);

                    // ✅ PENTING: jangan apply discount lagi.
                    // Karena itemsSubtotal sudah NET (sell price) dan discount hanya info.
                    $effectiveDiscPct = round((float) ($lockedSaleOrder->discount_percentage ?? 0), 2);
                    $discountAmount = 0;

                    $effectiveTaxPct = round((float) ($lockedSaleOrder->tax_percentage ?? 0), 2);

                    $effectiveShipping = (int) $allocShip;
                    $effectiveFee      = (int) $allocFee;
                    $taxAmount         = (int) $allocTax;

                    $computedGrandTotal = (int) ($itemsSubtotal + $taxAmount + $effectiveFee + $effectiveShipping);
                    if ($computedGrandTotal < 0) $computedGrandTotal = 0;

                    $dpTotal = max(0, (int) ($lockedSaleOrder->deposit_received_amount ?? 0));
                    $prevGrand = $this->getPrevInvoiceGrandTotalForSO((int) $lockedSaleOrder->id, (int) $branchId);

                    if ($dpTotal > 0 && $soGrand > 0 && $computedGrandTotal > 0) {
                        $dpAllocatedForThisInvoice = $this->calcCumulativeAlloc($dpTotal, $soGrand, $prevGrand, $computedGrandTotal);
                    }
                }

                // ======================================================
                // Payment status
                // ======================================================
                $paidAmount = max(0, (int) ($request->paid_amount ?? 0));

                $effectivePaidForStatus = $paidAmount + (int) $dpAllocatedForThisInvoice;
                $due_amount = (int) ($computedGrandTotal - $effectivePaidForStatus);

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

                // ======================================================
                // CREATE INVOICE (SALE)
                // ======================================================
                $note = trim((string) ($request->note ?? ''));

                if ($fromDelivery && $lockedSaleOrder) {
                    $soRef = (string) ($lockedSaleOrder->reference ?? ('SO-' . $lockedSaleOrder->id));

                    $append = "SO Locked: {$soRef}"
                        . " | Discount Info (Diff): " . number_format((float)$deliveryDiscountInfoTotal, 2, '.', '')
                        . " | DP Allocated: " . number_format((float)$dpAllocatedForThisInvoice, 2, '.', '');

                    $note = $note !== '' ? ($note . "\n" . $append) : $append;
                }

                $saleData = [
                    'date' => $request->date,
                    'license_number' => $request->car_number_plate,
                    'sale_from' => $request->sale_from,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->customer_name,

                    'tax_percentage'      => (float) $effectiveTaxPct,
                    'discount_percentage' => (float) $effectiveDiscPct,

                    'shipping_amount' => (int) $effectiveShipping,
                    'fee_amount' => (int) $effectiveFee,

                    'paid_amount' => (int) $paidAmount,
                    'total_amount' => (int) $computedGrandTotal,
                    'total_quantity' => (int) $totalQty,

                    'due_amount' => (int) $due_amount,
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,

                    'note' => $note,

                    'tax_amount' => (int) $taxAmount,
                    'discount_amount' => (int) $discountAmount, // locked => 0
                ];

                if (Schema::hasColumn('sales', 'branch_id')) {
                    $saleData['branch_id'] = $branchId;
                }
                if (Schema::hasColumn('sales', 'warehouse_id')) {
                    $saleData['warehouse_id'] = null;
                }

                $sale = Sale::create($saleData);

                // ======================================================
                // Create SaleDetails
                // ======================================================
                $total_cost = 0;

                foreach ($cartItems as $cart_item) {
                    $qty = (int) ($cart_item->qty ?? 0);
                    $price = (int) ($cart_item->price ?? 0);
                    if ($qty <= 0) continue;

                    $total_cost += (int) ($cart_item->options->product_cost ?? 0);

                    $saleDetailData = [
                        'sale_id' => (int) $sale->id,
                        'product_id' => (int) $cart_item->id,
                        'product_name' => (string) $cart_item->name,
                        'product_code' => (string) ($cart_item->options->code ?? ''),
                        'product_cost' => (int) ($cart_item->options->product_cost ?? 0),

                        'warehouse_id' => null,

                        'quantity' => (int) $qty,
                        'price' => max(0, (int) $price),
                        'unit_price' => (int) ($cart_item->options->unit_price ?? 0),

                        'sub_total' => (int) ($qty * max(0, (int) $price)),

                        // ini tetap disimpan buat info, bukan untuk ngurangin grand total lagi
                        'product_discount_amount' => (float) ($cart_item->options->product_discount ?? 0),
                        'product_discount_type' => (string) ($cart_item->options->product_discount_type ?? 'fixed'),
                        'product_tax_amount' => (float) ($cart_item->options->product_tax ?? 0),
                    ];

                    if (Schema::hasColumn('sale_details', 'branch_id')) {
                        $saleDetailData['branch_id'] = $branchId;
                    }

                    SaleDetails::create($saleDetailData);
                }

                // ======================================================
                // Link invoice to delivery OR auto-create delivery
                // ======================================================
                if ($fromDelivery && $lockedDelivery) {
                    $lockedDelivery->update([
                        'sale_id' => (int) $sale->id,
                    ]);

                    if ($lockedSaleOrder) {
                        $so = \Modules\SaleOrder\Entities\SaleOrder::query()
                            ->lockForUpdate()
                            ->where('branch_id', $branchId)
                            ->where('id', (int) $lockedSaleOrder->id)
                            ->first();

                        if ($so) {
                            $current = strtolower((string) ($so->status ?? 'pending'));

                            $updates = [
                                'sale_id' => (int) $sale->id,
                                'updated_by' => auth()->id(),
                            ];

                            if ($current === 'delivered') {
                                $updates['status'] = 'completed';
                            }

                            $so->update($updates);
                        }
                    }
                } else {
                    $this->autoCreateSaleDeliveryFromSale(
                        $sale,
                        $branchId,
                        $request->quotation_id ? (int) $request->quotation_id : null,
                        null
                    );
                }

                Cart::instance('sale')->destroy();

                // ======================================================
                // Accounting Transaction (unchanged)
                // ======================================================
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

                // ======================================================
                // Payment record (kalau paid_amount > 0)
                // ======================================================
                if ((int) $sale->paid_amount > 0) {
                    $depositCode = trim((string) ($request->deposit_code ?? ''));

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
        $details = SaleDetails::query()
            ->where('sale_id', (int) $sale->id)
            ->get();

        if ($details->isEmpty()) {
            throw new \RuntimeException('Cannot auto create Sale Delivery because invoice has no items.');
        }

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
            'sale_order_id' => null,
            'customer_id' => (int) $sale->customer_id,
            'date' => (string) $sale->getRawOriginal('date'),
            'status' => 'pending',
            'note' => 'Auto generated from Invoice #' . ($sale->reference ?? $sale->id)
                . ' | Payment: ' . ((string)($sale->payment_status ?? '')),
            'created_by' => auth()->id(),
        ];

        if (Schema::hasColumn('sale_deliveries', 'warehouse_id')) {
            $deliveryData['warehouse_id'] = null;
        }

        $delivery = SaleDelivery::create($deliveryData);

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
                    $dpTotal = (int) ($saleOrder->deposit_received_amount ?? 0);

                    if ($dpTotal > 0) {
                        $invoiceSubtotal = 0;
                        foreach ($sale->saleDetails as $d) {
                            $qty = (int) ($d->quantity ?? 0);
                            $price = (int) ($d->price ?? 0);
                            if ($qty > 0 && $price >= 0) {
                                $invoiceSubtotal += ($qty * $price);
                            }
                        }

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
