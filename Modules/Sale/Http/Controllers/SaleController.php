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
use Modules\Branch\Entities\Branch;
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
use Barryvdh\DomPDF\Facade\Pdf;
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

    private function allocateSaleOrderInvoiceFinancials(SaleOrder $saleOrder, int $branchId, int $invoiceItemsSubtotal): array
    {
        $invoiceItemsSubtotal = max(0, (int) $invoiceItemsSubtotal);

        $soSubtotal = max(0, (int) ($saleOrder->subtotal_amount ?? 0));
        $soGrand = max(0, (int) ($saleOrder->total_amount ?? 0));

        $soTaxAmount = max(0, (int) ($saleOrder->tax_amount ?? 0));
        $soShip = max(0, (int) ($saleOrder->shipping_amount ?? 0));
        $soFee = max(0, (int) ($saleOrder->fee_amount ?? 0));
        $soHeaderDiscount = max(0, (int) ($soSubtotal + $soTaxAmount + $soShip + $soFee - $soGrand));
        $dpTotalReceived = max(0, (int) ($saleOrder->deposit_received_amount ?? 0));

        $prevSubtotal = $this->getPrevInvoiceItemsSubtotalForSO((int) $saleOrder->id, $branchId);

        $taxAlloc = $this->calcCumulativeAlloc($soTaxAmount, $soSubtotal, $prevSubtotal, $invoiceItemsSubtotal);
        $shipAlloc = $this->calcCumulativeAlloc($soShip, $soSubtotal, $prevSubtotal, $invoiceItemsSubtotal);
        $feeAlloc = $this->calcCumulativeAlloc($soFee, $soSubtotal, $prevSubtotal, $invoiceItemsSubtotal);
        $discountAlloc = $this->calcCumulativeAlloc($soHeaderDiscount, $soSubtotal, $prevSubtotal, $invoiceItemsSubtotal);

        $invoiceGrand = max(0, (int) ($invoiceItemsSubtotal + $taxAlloc + $shipAlloc + $feeAlloc - $discountAlloc));

        $dpAlloc = 0;
        if ($dpTotalReceived > 0 && $soGrand > 0 && $invoiceGrand > 0) {
            $prevGrand = $this->getPrevInvoiceGrandTotalForSO((int) $saleOrder->id, $branchId);
            $dpAlloc = $this->calcCumulativeAlloc($dpTotalReceived, $soGrand, $prevGrand, $invoiceGrand);
        }

        return [
            'items_subtotal' => (int) $invoiceItemsSubtotal,
            'tax_amount' => (int) $taxAlloc,
            'shipping_amount' => (int) $shipAlloc,
            'fee_amount' => (int) $feeAlloc,
            'discount_amount' => (int) $discountAlloc,
            'grand_total' => (int) $invoiceGrand,
            'dp_allocated_amount' => (int) $dpAlloc,
            'dp_total_received' => (int) $dpTotalReceived,
            'sale_order_subtotal' => (int) $soSubtotal,
            'sale_order_grand_total' => (int) $soGrand,
        ];
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

    private function resolveHeaderDiscount($request, int $itemsSubtotal, int $taxAmount, int $fee, int $shipping): array
    {
        $discountType = (string) ($request->discount_type ?? 'percentage');
        $discountType = $discountType === 'fixed' ? 'fixed' : 'percentage';

        $rawValue = $request->header_discount_value ?? $request->discount_percentage ?? 0;
        $discountValue = is_numeric($rawValue) ? (float) $rawValue : 0.0;

        if ($discountValue < 0) {
            throw new \RuntimeException('Discount cannot be negative.');
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            throw new \RuntimeException('Discount percentage cannot exceed 100%.');
        }

        $baseBeforeDiscount = max(0, $itemsSubtotal + $taxAmount + $fee + $shipping);

        if ($discountType === 'fixed') {
            $discountAmount = (int) round($discountValue);

            if ($discountAmount > $baseBeforeDiscount) {
                throw new \RuntimeException('Discount amount cannot be greater than total before discount.');
            }

            $discountPercentage = $itemsSubtotal > 0
                ? round(($discountAmount / $itemsSubtotal) * 100, 2)
                : 0.0;
        } else {
            $discountPercentage = round($discountValue, 2);
            $discountAmount = (int) floor($itemsSubtotal * ($discountPercentage / 100));
        }

        $grandTotal = $itemsSubtotal + $taxAmount + $fee + $shipping - $discountAmount;
        if ($grandTotal < 0) {
            throw new \RuntimeException('Grand Total cannot be negative.');
        }

        return [
            'percentage' => (float) $discountPercentage,
            'amount' => (int) $discountAmount,
            'grand_total' => (int) $grandTotal,
        ];
    }

    private function deliveredQtyFromDeliveryItem(SaleDeliveryItem $item): int
    {
        $confirmedQty = (int) (
            (int) ($item->qty_good ?? 0)
            + (int) ($item->qty_defect ?? 0)
            + (int) ($item->qty_damaged ?? 0)
        );

        if ($confirmedQty > 0) {
            return $confirmedQty;
        }

        return max(0, (int) ($item->quantity ?? 0));
    }

    private function getInvoiceableQtyMapByDelivery(SaleDelivery $delivery): array
    {
        $deliveredByProduct = [];

        foreach (($delivery->items ?? []) as $item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId <= 0) {
                continue;
            }

            if (!isset($deliveredByProduct[$productId])) {
                $deliveredByProduct[$productId] = 0;
            }

            $deliveredByProduct[$productId] += $this->deliveredQtyFromDeliveryItem($item);
        }

        $invoicedByProduct = [];
        $saleId = (int) ($delivery->sale_id ?? 0);
        if ($saleId > 0) {
            $invoicedByProduct = DB::table('sale_details')
                ->where('sale_id', $saleId)
                ->whereNull('deleted_at')
                ->select('product_id', DB::raw('SUM(COALESCE(quantity,0)) as qty'))
                ->groupBy('product_id')
                ->pluck('qty', 'product_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->toArray();
        }

        $result = [];

        foreach ($deliveredByProduct as $productId => $deliveredQty) {
            $productId = (int) $productId;
            $deliveredQty = max(0, (int) $deliveredQty);
            $alreadyInvoicedQty = max(0, (int) ($invoicedByProduct[$productId] ?? 0));
            $remainingQty = $deliveredQty - $alreadyInvoicedQty;

            if ($remainingQty < 0) {
                $remainingQty = 0;
            }

            $result[$productId] = [
                'delivered' => $deliveredQty,
                'already_invoiced' => $alreadyInvoicedQty,
                'remaining' => $remainingQty,
            ];
        }

        return $result;
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
        $hppService = new \Modules\Product\Services\HppService();

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

        // invoice items subtotal (qty * shown price)  => NOTE: shown price kita pakai NET (SO price)
        $invoiceItemsSubtotal = 0;
        $saleOrderGrandTotal = 0;

        $invoiceEstimatedGrand = 0;
        $dpAllocated = 0;
        $suggestedPayNow = 0;

        // INFO discount (buat tampilan di kolom item / info SO), TAPI TIDAK dipakai ngurangin summary lagi
        $deliveryDiscountInfoTotal = 0.0;

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

                            // ✅ PATCH: pastiin integer (biar UI selalu konsisten)
                            'deposit_received_amount'   => (int) ($lockedSaleOrder->deposit_received_amount ?? 0),

                            'deposit_payment_method'    => (string) ($lockedSaleOrder->deposit_payment_method ?? ''),
                            'deposit_code'              => (string) ($lockedSaleOrder->deposit_code ?? ''),

                            'sale_order_subtotal_amount' => (float) ($lockedSaleOrder->subtotal_amount ?? 0),
                            'sale_order_total_amount'    => (float) ($lockedSaleOrder->total_amount ?? 0),
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

                $getBranchStockSnapshot = function (int $productId) use ($branchId, $getStockLastAllWarehousesInActiveBranch) {
                    $good = (int) $getStockLastAllWarehousesInActiveBranch($productId);

                    $reserved = (int) \DB::table('stocks')
                        ->where('branch_id', (int) $branchId)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $productId)
                        ->value('qty_reserved');

                    $reserved = max(0, $reserved);

                    return [
                        'good' => (int) max(0, $good),
                        'reserved' => (int) $reserved,
                        'sellable' => (int) max(0, $good - $reserved),
                    ];
                };

                $invoiceableMap = $this->getInvoiceableQtyMapByDelivery($delivery);

                foreach (($delivery->items ?? []) as $it) {
                    $productId = (int) ($it->product_id ?? 0);
                    if ($productId <= 0) continue;

                    $deliveredQty = (int) ($invoiceableMap[$productId]['delivered'] ?? $this->deliveredQtyFromDeliveryItem($it));
                    $alreadyInvoicedQty = (int) ($invoiceableMap[$productId]['already_invoiced'] ?? 0);
                    $remainingInvoiceableQty = (int) ($invoiceableMap[$productId]['remaining'] ?? max(0, $deliveredQty - $alreadyInvoicedQty));

                    if ($remainingInvoiceableQty <= 0) continue;

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
                            $deliveryDiscountInfoTotal += ($discAmt * $remainingInvoiceableQty);
                        } else {
                            $deliveryDiscountInfoTotal += (($unitPrice * $remainingInvoiceableQty) * ($discAmt / 100));
                        }
                    }

                    $productTax = (float) ($it->product_tax_amount ?? 0);

                    $branchStockSnapshot = $getBranchStockSnapshot($productId);
                    $stock = (int) ($branchStockSnapshot['sellable'] ?? 0);
                    $stockScope = 'branch';

                    // subtotal pakai NET price (priceShown)
                    $subTotal = (float) ($priceShown * $remainingInvoiceableQty);
                    $invoiceItemsSubtotal += (int) round($subTotal);

                    $cart->add([
                        'id'      => $productId,
                        'name'    => (string) ($it->product_name ?? ($p->product_name ?? '-')),
                        'qty'     => $remainingInvoiceableQty,
                        'price'   => $priceShown,
                        'weight'  => 1,
                        'options' => [
                            'sub_total'             => $subTotal,
                            'code'                  => (string) ($it->product_code ?? ($p->product_code ?? 'UNKNOWN')),
                            'unit'                  => (string) ($it->product_unit ?? ($p->product_unit ?? '')),
                            'stock'                 => (int) $stock,
                            'reserved_stock'        => (int) ($branchStockSnapshot['reserved'] ?? 0),
                            'sellable_stock'        => (int) ($branchStockSnapshot['sellable'] ?? 0),
                            'stock_scope'           => $stockScope,

                            'warehouse_id'          => $deliveryWarehouseId,
                            'warehouse_name'        => $deliveryWarehouseName,

                            'invoice_source'        => 'sale_delivery',
                            'delivered_qty'         => (int) $deliveredQty,
                            'already_invoiced_qty'  => (int) $alreadyInvoicedQty,
                            'remaining_invoiceable_qty' => (int) $remainingInvoiceableQty,
                            'current_stock_qty'     => (int) ($branchStockSnapshot['sellable'] ?? 0),

                            'product_tax'           => $productTax,
                            'unit_price'            => $unitPrice,

                            // ✅ discount ditampilkan di kolom item
                            'product_discount'      => $discAmt,
                            'product_discount_type' => $discType,

                            'product_cost'          => (float) (
                                $it->product_cost
                                ?? (($branchId > 0 && $productId > 0)
                                    ? $hppService->getCurrentHpp((int) $branchId, (int) $productId)
                                    // Legacy compatibility fallback only when no
                                    // branch-specific HPP context is available.
                                    : ($p->product_cost ?? 0))
                            ),
                        ],
                    ]);
                }
            }
        }

        // ==========================================
        // ✅ LOCKED CALC FOR SUMMARY UI (NO DOUBLE DISCOUNT)
        // ==========================================
        if (!empty($lockedSaleOrder) && !empty($lockedFinancial) && (int)$lockedSaleOrder->id > 0) {
            $alloc = $this->allocateSaleOrderInvoiceFinancials($lockedSaleOrder, (int) $branchId, (int) $invoiceItemsSubtotal);

            $taxAlloc = (int) ($alloc['tax_amount'] ?? 0);
            $shipAlloc = (int) ($alloc['shipping_amount'] ?? 0);
            $feeAlloc = (int) ($alloc['fee_amount'] ?? 0);
            $discountAlloc = (int) ($alloc['discount_amount'] ?? 0);
            $invoiceEstimatedGrand = (int) ($alloc['grand_total'] ?? 0);
            $dpAllocated = (int) ($alloc['dp_allocated_amount'] ?? 0);
            $dpTotalReceived = (int) ($alloc['dp_total_received'] ?? 0);
            $lockedFinancial['discount_info_invoice_est'] = $discountAlloc;

            // ✅ suggested pay now selalu ada (kalau DP=0 ya = total invoice)
            $suggestedPayNow = max(0, $invoiceEstimatedGrand - $dpAllocated);

            $lockedFinancial['invoice_items_subtotal'] = (int) $invoiceItemsSubtotal;
            $lockedFinancial['sale_order_grand_total'] = (int) $saleOrderGrandTotal;

            $lockedFinancial['invoice_estimated_grand_total'] = (int) $invoiceEstimatedGrand;

            $lockedFinancial['tax_invoice_est']  = (int) $taxAlloc;
            $lockedFinancial['ship_invoice_est'] = (int) $shipAlloc;
            $lockedFinancial['fee_invoice_est']  = (int) $feeAlloc;

            $lockedFinancial['discount_info_invoice_est'] = 0;

            $lockedFinancial['discount_info_display_total'] = (int) round(max(0, (float)$deliveryDiscountInfoTotal));

            // ✅ PATCH: pastiin juga diset kembali (biar UI gak null)
            $lockedFinancial['deposit_received_amount'] = (int) $dpTotalReceived;

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

        $autoDeliveryRedirectUrl = null;

        try {
            DB::transaction(function () use ($request, &$autoDeliveryRedirectUrl) {
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

                $lockedDelivery = null;
                $lockedSaleOrder = null;

                $deliveryDiscountInfoTotal = 0.0;

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

                    // info only: compute discount from SO items
                    $soItems = \Modules\SaleOrder\Entities\SaleOrderItem::query()
                        ->where('sale_order_id', (int)$lockedSaleOrder->id)
                        ->get()
                        ->keyBy('product_id');

                    foreach (($lockedDelivery->items ?? []) as $it) {
                        $pid = (int) ($it->product_id ?? 0);
                        $qty = $this->deliveredQtyFromDeliveryItem($it);
                        if ($pid <= 0 || $qty <= 0) continue;

                        $soRow = $soItems->get($pid);

                        $unit  = (float) ($soRow?->unit_price ?? $it->unit_price ?? 0);
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

                    $remainingInvoiceableMap = $this->getInvoiceableQtyMapByDelivery($lockedDelivery);
                    $needByProduct = [];

                    foreach ($cartItems as $cartItem) {
                        $pid = (int) ($cartItem->id ?? 0);
                        $qty = max(0, (int) ($cartItem->qty ?? 0));
                        if ($pid <= 0 || $qty <= 0) {
                            continue;
                        }

                        if (!isset($needByProduct[$pid])) {
                            $needByProduct[$pid] = 0;
                        }

                        $needByProduct[$pid] += $qty;
                    }

                    foreach ($needByProduct as $pid => $qtyNeed) {
                        $remainingQty = (int) ($remainingInvoiceableMap[(int) $pid]['remaining'] ?? 0);
                        if ((int) $qtyNeed > $remainingQty) {
                            $product = Product::find((int) $pid);
                            $code = $product?->product_code ?? ('#' . (int) $pid);
                            $name = $product?->product_name ?? '';

                            throw new \RuntimeException(
                                "Invoice qty exceeds delivered quantity for {$code} {$name}. " .
                                "Remaining to invoice: {$remainingQty}, Requested: " . (int) $qtyNeed . "."
                            );
                        }
                    }
                }

                // ======================================================
                // ✅ STOCK VALIDATION (WALK-IN FLOW): prevent reserved > actual stock
                //
                // Problem kamu:
                // - Sale invoice dibuat -> auto create SaleDelivery pending
                // - reserved ditambah, tapi saat create sale berikutnya belum ngecek reserved existing
                // - jadinya bisa oversell dan qty_reserved bisa > stok real (mutation stock_last)
                //
                // Fix:
                // - Untuk NON-fromDelivery (walk-in), sebelum create invoice:
                //   cek: (GOOD STOCK di branch) - (CURRENT RESERVED pool) >= qty yang mau di-invoice
                // - Lock row stocks (pool: warehouse_id NULL) supaya aman dari race condition
                // ======================================================
                if (!$fromDelivery) {
                    // group request qty per product
                    $needByProduct = [];
                    foreach ($cartItems as $it) {
                        $pid = (int) ($it->id ?? 0);
                        $qty = (int) ($it->qty ?? 0);
                        if ($pid <= 0 || $qty <= 0) continue;
                        if (!isset($needByProduct[$pid])) $needByProduct[$pid] = 0;
                        $needByProduct[$pid] += $qty;
                    }

                    if (!empty($needByProduct)) {
                        // active branch warehouses (untuk baca GOOD stock dari mutations)
                        $warehouseIds = \Modules\Product\Entities\Warehouse::query()
                            ->where('branch_id', $branchId)
                            ->pluck('id')
                            ->map(fn($v) => (int) $v)
                            ->toArray();

                        $getStockLastByWarehouse = function (int $productId, int $warehouseId): int {
                            $m = \Modules\Mutation\Entities\Mutation::query()
                                ->where('product_id', $productId)
                                ->where('warehouse_id', $warehouseId)
                                ->latest()
                                ->first();

                            return $m ? (int) $m->stock_last : 0;
                        };

                        foreach ($needByProduct as $pid => $qtyNeed) {
                            $pid = (int) $pid;
                            $qtyNeed = (int) $qtyNeed;
                            if ($pid <= 0 || $qtyNeed <= 0) continue;

                            // 1) hitung GOOD stock real di branch (sum stock_last terakhir per warehouse)
                            $good = 0;
                            foreach ($warehouseIds as $wid) {
                                $good += $getStockLastByWarehouse($pid, (int) $wid);
                            }
                            $good = max(0, (int) $good);

                            // 2) ambil reserved pool saat ini (lock row biar aman)
                            $row = DB::table('stocks')
                                ->where('branch_id', (int) $branchId)
                                ->whereNull('warehouse_id') // POOL row
                                ->where('product_id', (int) $pid)
                                ->lockForUpdate()
                                ->first();

                            $reservedNow = $row ? max(0, (int) ($row->qty_reserved ?? 0)) : 0;

                            // available yang boleh di-reserve sekarang = GOOD - reserved existing
                            $availableToReserve = $good - $reservedNow;
                            if ($availableToReserve < 0) $availableToReserve = 0;

                            if ($qtyNeed > $availableToReserve) {
                                // bikin message yang jelas supaya admin ngerti
                                $p = \Modules\Product\Entities\Product::find($pid);
                                $code = $p?->product_code ?? ('#' . $pid);
                                $name = $p?->product_name ?? '';

                                throw new \RuntimeException(
                                    "Stock tidak cukup untuk {$code} {$name}. " .
                                    "Requested: {$qtyNeed}, Available (after reserved): {$availableToReserve}, " .
                                    "Good: {$good}, Reserved: {$reservedNow}. " .
                                    "Silakan kurangi qty / selesaikan dulu Sale Delivery pending yang masih reserve stock."
                                );
                            }
                        }
                    }
                }

                // ======================================================
                // Compute invoice items subtotal & qty (SERVER TRUTH)
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
                $effectiveTaxPct = max(0, min(100, $effectiveTaxPct));

                $effectiveShipping = (int) ($request->shipping_amount ?? 0);
                $effectiveFee      = (int) ($request->fee_amount ?? 0);

                $taxAmount      = (int) floor($itemsSubtotal * ($effectiveTaxPct / 100));
                $headerDiscount = $this->resolveHeaderDiscount(
                    $request,
                    (int) $itemsSubtotal,
                    (int) $taxAmount,
                    (int) $effectiveFee,
                    (int) $effectiveShipping
                );
                $effectiveDiscPct = (float) $headerDiscount['percentage'];
                $discountAmount = (int) $headerDiscount['amount'];
                $computedGrandTotal = (int) $headerDiscount['grand_total'];

                // ======================================================
                // LOCKED BY SALE ORDER (invoice from delivery)
                // ======================================================
                $dpAllocatedForThisInvoice = 0;

                if ($fromDelivery && $lockedSaleOrder) {
                    $alloc = $this->allocateSaleOrderInvoiceFinancials($lockedSaleOrder, (int) $branchId, (int) $itemsSubtotal);
                    $allocTax = (int) ($alloc['tax_amount'] ?? 0);
                    $allocShip = (int) ($alloc['shipping_amount'] ?? 0);
                    $allocFee = (int) ($alloc['fee_amount'] ?? 0);
                    $allocDiscount = (int) ($alloc['discount_amount'] ?? 0);

                    $effectiveDiscPct = round((float) ($lockedSaleOrder->discount_percentage ?? 0), 2);
                    $discountAmount = (int) $allocDiscount;

                    $effectiveTaxPct = round((float) ($lockedSaleOrder->tax_percentage ?? 0), 2);

                    $effectiveShipping = (int) $allocShip;
                    $effectiveFee      = (int) $allocFee;
                    $taxAmount         = (int) $allocTax;

                    $computedGrandTotal = (int) ($alloc['grand_total'] ?? 0);
                    $dpAllocatedForThisInvoice = (int) ($alloc['dp_allocated_amount'] ?? 0);
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

                    'dp_allocated_amount' => (int) $dpAllocatedForThisInvoice,

                    'due_amount' => (int) $due_amount,
                    'payment_status' => $payment_status,
                    'payment_method' => $request->payment_method,

                    'note' => $note,

                    'tax_amount' => (int) $taxAmount,
                    'discount_amount' => (int) $discountAmount,
                ];

                if (Schema::hasColumn('sales', 'branch_id')) {
                    $saleData['branch_id'] = $branchId;
                }
                if (Schema::hasColumn('sales', 'warehouse_id')) {
                    $saleData['warehouse_id'] = null;
                }

                $sale = Sale::create($saleData);

                // ======================================================
                // Create SaleDetails (✅ server-truth snapshot HPP + fix total_cost)
                // ======================================================
                $total_cost = 0;

                $hppService = new \Modules\Product\Services\HppService();
                $saleHppAt = $sale->created_at ?? now();

                foreach ($cartItems as $cart_item) {
                    $qty = (int) ($cart_item->qty ?? 0);
                    $price = (int) ($cart_item->price ?? 0);
                    if ($qty <= 0) continue;

                    $productId = (int) $cart_item->id;

                    // ✅ snapshot HPP di server: as-of waktu transaksi sale dibuat
                    $hppUnit = 0;
                    if ($branchId > 0 && $productId > 0) {
                        if (method_exists($hppService, 'getHppAsOf')) {
                            $hppUnit = (float) $hppService->getHppAsOf($branchId, $productId, $saleHppAt);
                        } else {
                            $hppUnit = (float) $hppService->getCurrentHpp($branchId, $productId);
                        }
                    }
                    $hppUnitInt = (int) round(max(0.0, $hppUnit), 0);

                    // ✅ FIX BUG: total_cost harus qty * hppUnit
                    $total_cost += ($hppUnitInt * $qty);

                    $saleDetailData = [
                        'sale_id' => (int) $sale->id,
                        'product_id' => $productId,
                        'product_name' => (string) $cart_item->name,
                        'product_code' => (string) ($cart_item->options->code ?? ''),

                        // ✅ ini snapshot HPP/unit
                        'product_cost' => $hppUnitInt,

                        'warehouse_id' => null,

                        'quantity' => $qty,
                        'price' => max(0, (int) $price),

                        // unit_price (original) tetap ambil dari cart (source: SO/delivery)
                        'unit_price' => (int) ($cart_item->options->unit_price ?? 0),

                        'sub_total' => (int) ($qty * max(0, (int) $price)),

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
                    $autoDelivery = $this->autoCreateSaleDeliveryFromSale(
                        $sale,
                        $branchId,
                        $request->quotation_id ? (int) $request->quotation_id : null,
                        null
                    );

                    if ($autoDelivery) {
                        $autoDeliveryRedirectUrl = route('sale-deliveries.confirm.form', (int) $autoDelivery->id);
                    }
                }

                Cart::instance('sale')->destroy();

                // ======================================================
                // Accounting Transaction (pakai total_cost yg sudah benar)
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
                // Payment record (kalau paid_amount > 0) - tetap
                // ======================================================
                if ((int) $sale->paid_amount > 0) {
                    $depositCode = trim((string) ($request->deposit_code ?? ''));

                    if ($depositCode === '' && $fromDelivery && $lockedSaleOrder) {
                        $soDepositCode = trim((string) ($lockedSaleOrder->deposit_code ?? ''));
                        if ($soDepositCode !== '' && $soDepositCode !== '-') {
                            $depositCode = $soDepositCode;
                        }
                    }

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

            $redirect = redirect()->route('sales.index');

            if (!empty($autoDeliveryRedirectUrl)) {
                $redirect->with('auto_delivery_notice', [
                    'title' => 'Sale Created',
                    'message' => 'A Sale Delivery has been automatically created. Please confirm the Sale Delivery to complete the delivery process.',
                    'primary_label' => 'Go to Sale Delivery',
                    'url' => $autoDeliveryRedirectUrl,
                ]);
            }

            return $redirect;

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
    ): ?SaleDelivery {
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
            return null;
        }

        $deliveryData = [
            'branch_id' => $branchId,
            'quotation_id' => $quotationId,
            'sale_id' => (int) $sale->id,
            'sale_order_id' => null, // walk-in invoice (not from SO)
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

        // ======================================================
        // Create SaleDeliveryItems (grouped by product)
        // ======================================================
        $grouped = $details->groupBy('product_id');

        // ✅ NEW: build qty map untuk reserved pool
        $reservedAddByProduct = [];

        foreach ($grouped as $productId => $productRows) {
            $pid = (int) $productId;
            $qty = (int) $productRows->sum('quantity');
            if ($pid <= 0 || $qty <= 0) continue;

            $price = (int) ($productRows->first()->price ?? 0);

            SaleDeliveryItem::create([
                'sale_delivery_id' => (int) $delivery->id,
                'product_id' => $pid,
                'quantity' => $qty,
                'price' => $price > 0 ? $price : null,
            ]);

            // ✅ accumulate reserved increase
            if (!isset($reservedAddByProduct[$pid])) $reservedAddByProduct[$pid] = 0;
            $reservedAddByProduct[$pid] += $qty;
        }

        // ======================================================
        // ✅ NEW: increment qty_reserved on POOL STOCK (warehouse_id NULL)
        // This is required for WALK-IN flow:
        // Invoice -> auto SaleDelivery (pending) -> later Confirm will DECREMENT reserved.
        // ======================================================
        if ($branchId > 0 && !empty($reservedAddByProduct)) {
            foreach ($reservedAddByProduct as $pid => $qtyAdd) {
                $pid = (int) $pid;
                $qtyAdd = (int) $qtyAdd;

                if ($pid <= 0 || $qtyAdd <= 0) continue;

                $row = DB::table('stocks')
                    ->where('branch_id', (int) $branchId)
                    ->whereNull('warehouse_id')
                    ->where('product_id', (int) $pid)
                    ->lockForUpdate()
                    ->first();

                if ($row) {
                    $currentReserved = (int) ($row->qty_reserved ?? 0);

                    DB::table('stocks')
                        ->where('id', (int) $row->id)
                        ->update([
                            'qty_reserved' => $currentReserved + $qtyAdd,
                            'updated_by'   => auth()->id(),
                            'updated_at'   => now(),
                        ]);
                } else {
                    DB::table('stocks')->insert([
                        'product_id'     => (int) $pid,
                        'branch_id'      => (int) $branchId,
                        'warehouse_id'   => null,

                        // pool row: available/incoming default 0, reserved = qty invoice
                        'qty_total'  => 0,
                        'qty_reserved'   => (int) $qtyAdd,
                        'qty_incoming'   => 0,
                        'min_stock'      => 0,

                        'note'           => null,
                        'created_by'     => auth()->id(),
                        'updated_by'     => auth()->id(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
            }
        }

        if (empty($delivery->reference)) {
            $delivery->update([
                'reference' => make_reference_id('SDO', (int) $delivery->id),
            ]);
        }

        return $delivery;
    }

    public function pdf(Sale $sale)
    {
        abort_if(Gate::denies('show_sales'), 403);

        $activeBranchId = BranchContext::id();
        $saleBranchId = (int) ($sale->branch_id ?? 0);

        if ($activeBranchId && $saleBranchId > 0) {
            abort_if($saleBranchId !== (int) $activeBranchId, 403);
        }

        $branchId = $saleBranchId > 0 ? $saleBranchId : $activeBranchId;

        $sale->load(['creator', 'updater', 'saleDetails', 'branch']);
        $branch = $sale->branch ?: ($branchId ? Branch::withoutGlobalScopes()->find((int) $branchId) : null);

        $customer = Customer::query()
            ->where('id', $sale->customer_id)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', (int) $branchId);
                }
            })
            ->firstOrFail();

        $saleDeliveries = SaleDelivery::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', (int) $branchId))
            ->where('sale_id', (int) $sale->id)
            ->orderByDesc('id')
            ->get();

        $salePayments = SalePayment::query()
            ->where('sale_id', (int) $sale->id)
            ->orderBy('id')
            ->get();

        $saleOrderDepositInfo = null;

        try {
            $saleOrderId = (int) ($saleDeliveries->pluck('sale_order_id')->filter()->first() ?? 0);

            if ($saleOrderId > 0) {
                $saleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                    ->when($branchId, fn ($q) => $q->where('branch_id', (int) $branchId))
                    ->where('id', $saleOrderId)
                    ->first();

                if ($saleOrder) {
                    $dpTotal = (int) ($saleOrder->deposit_received_amount ?? 0);
                    $allocated = (int) ($sale->dp_allocated_amount ?? 0);

                    if ($dpTotal > 0 || $allocated > 0) {
                        $saleOrderDepositInfo = [
                            'sale_order_reference' => (string) ($saleOrder->reference ?? ('SO-'.$saleOrder->id)),
                            'deposit_total' => max(0, $dpTotal),
                            'allocated' => max(0, $allocated),
                            'ratio_percent' => null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $saleOrderDepositInfo = null;
        }

        $pdf = Pdf::loadView('sale::print', [
            'sale' => $sale,
            'customer' => $customer,
            'saleDeliveries' => $saleDeliveries,
            'saleOrderDepositInfo' => $saleOrderDepositInfo,
            'salePayments' => $salePayments,
            'branch' => $branch,
        ])->setPaper('a4');

        return $pdf->stream('sale-' . $sale->reference . '.pdf');
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

        $salePayments = SalePayment::query()
            ->where('sale_id', (int) $sale->id)
            ->orderBy('id')
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
                    $allocated = (int) ($sale->dp_allocated_amount ?? 0);

                    if ($dpTotal > 0 || $allocated > 0) {
                        $saleOrderDepositInfo = [
                            'sale_order_reference' => (string) ($saleOrder->reference ?? ('SO-'.$saleOrder->id)),
                            'deposit_total' => max(0, $dpTotal),
                            'allocated' => max(0, $allocated),
                            'ratio_percent' => null, // sudah tidak pakai pro-rata
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $saleOrderDepositInfo = null;
        }

        return view('sale::show', compact('sale', 'customer', 'saleDeliveries', 'saleOrderDepositInfo', 'salePayments'));
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

            $itemsSubtotal = 0;
            $totalQty = 0;

            foreach (Cart::instance('sale')->content() as $cart_item) {
                $qty = (int) ($cart_item->qty ?? 0);
                $price = (int) ($cart_item->price ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $totalQty += $qty;
                $itemsSubtotal += ($qty * max(0, $price));
            }

            $itemsSubtotal = max(0, (int) $itemsSubtotal);
            $taxPct = max(0, min(100, round((float) ($request->tax_percentage ?? 0), 2)));
            $shipping = max(0, (int) ($request->shipping_amount ?? 0));
            $fee = max(0, (int) ($request->fee_amount ?? 0));
            $taxAmount = (int) floor($itemsSubtotal * ($taxPct / 100));

            $headerDiscount = $this->resolveHeaderDiscount(
                $request,
                (int) $itemsSubtotal,
                (int) $taxAmount,
                (int) $fee,
                (int) $shipping
            );

            $discountPercentage = (float) $headerDiscount['percentage'];
            $discountAmount = (int) $headerDiscount['amount'];
            $computedGrandTotal = (int) $headerDiscount['grand_total'];

            $due_amount = $computedGrandTotal - (int) $request->paid_amount;

            if ($due_amount == $computedGrandTotal) {
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
                'tax_percentage' => (float) $taxPct,
                'discount_percentage' => (float) $discountPercentage,
                'shipping_amount' => (int) $shipping,
                'fee_amount' => (int) $fee,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => (int) $computedGrandTotal,
                'total_quantity' => (int) $totalQty,
                'due_amount' => $due_amount * 1,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => (int) $taxAmount,
                'discount_amount' => (int) $discountAmount,
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

    public function destroy(Sale $sale)
    {
        abort_if(Gate::denies('delete_sales'), 403);

        $branchId = BranchContext::id();

        try {
            DB::transaction(function () use ($sale, $branchId) {

                // =========================
                // ✅ Lock invoice header
                // =========================
                $s = Sale::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail((int) $sale->id);

                // ✅ Branch guard
                if (Schema::hasColumn('sales', 'branch_id')) {
                    if ((int) ($s->branch_id ?? 0) !== (int) $branchId) {
                        abort(403, 'Active branch mismatch for this Sale.');
                    }
                }

                // =========================
                // ✅ RULE 0: payment_status MUST be Unpaid
                // =========================
                $status = strtolower(trim((string) ($s->payment_status ?? '')));
                if ($status !== 'unpaid') {
                    throw new \RuntimeException("Cannot delete: Invoice payment_status must be Unpaid. Current: {$s->payment_status}");
                }

                // =========================
                // ✅ RULE 1: No payment allowed
                // =========================
                $paidAmount = (int) ($s->paid_amount ?? 0);
                if ($paidAmount > 0) {
                    throw new \RuntimeException('Cannot delete: this Invoice already has payment amount (paid_amount > 0).');
                }

                $hasPayments = SalePayment::withoutGlobalScopes()
                    ->where('sale_id', (int) $s->id)
                    ->exists();

                if ($hasPayments) {
                    throw new \RuntimeException('Cannot delete: this Invoice already has payment records.');
                }

                // =========================
                // ✅ Lock related deliveries
                // =========================
                $deliveries = SaleDelivery::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->where('sale_id', (int) $s->id)
                    ->get();

                // =========================
                // ✅ RULE 2: ALL deliveries must be PENDING
                // =========================
                if ($deliveries->isNotEmpty()) {
                    foreach ($deliveries as $del) {

                        if ((int) ($del->branch_id ?? 0) !== (int) $branchId) {
                            throw new \RuntimeException('Cannot delete: related Sale Delivery belongs to different branch.');
                        }

                        $dst = strtolower(trim((string) ($del->getRawOriginal('status') ?? $del->status ?? 'pending')));
                        if ($dst !== 'pending') {
                            throw new \RuntimeException(
                                "Cannot delete: related Sale Delivery already processed (status: {$del->status})."
                            );
                        }
                    }

                    // =========================
                    // ✅ Rollback reserved POOL ONLY for AUTO walk-in deliveries
                    // (agar tidak salah rollback untuk delivery manual source=sale)
                    // =========================
                    $reservedRollbackByProduct = [];

                    foreach ($deliveries as $del) {
                        $saleOrderId = (int) ($del->sale_order_id ?? 0);

                        // ✅ kalau dari SO → tidak ada reserved pool (skip)
                        if ($saleOrderId > 0) {
                            continue;
                        }

                        // ✅ DETECT auto-generated delivery dari invoice (walk-in)
                        // NOTE: ini penting supaya delivery manual source=sale tidak ikut rollback reserved.
                        $note = (string) ($del->note ?? '');
                        $isAutoWalkin = str_starts_with($note, 'Auto generated from Invoice #');

                        if (!$isAutoWalkin) {
                            continue;
                        }

                        $items = SaleDeliveryItem::withoutGlobalScopes()
                            ->where('sale_delivery_id', (int) $del->id)
                            ->lockForUpdate()
                            ->get(['product_id', 'quantity']);

                        foreach ($items as $it) {
                            $pid = (int) ($it->product_id ?? 0);
                            $qty = (int) ($it->quantity ?? 0);
                            if ($pid <= 0 || $qty <= 0) continue;

                            if (!isset($reservedRollbackByProduct[$pid])) $reservedRollbackByProduct[$pid] = 0;
                            $reservedRollbackByProduct[$pid] += $qty;
                        }
                    }

                    if (!empty($reservedRollbackByProduct)) {
                        foreach ($reservedRollbackByProduct as $pid => $qty) {
                            $pid = (int) $pid;
                            $qty = (int) $qty;
                            if ($pid <= 0 || $qty <= 0) continue;

                            $poolRow = DB::table('stocks')
                                ->where('branch_id', (int) $branchId)
                                ->whereNull('warehouse_id')
                                ->where('product_id', (int) $pid)
                                ->lockForUpdate()
                                ->first();

                            if ($poolRow) {
                                DB::table('stocks')
                                    ->where('id', (int) $poolRow->id)
                                    ->update([
                                        'qty_reserved' => DB::raw("GREATEST(COALESCE(qty_reserved,0) - {$qty}, 0)"),
                                        'updated_by'   => auth()->id(),
                                        'updated_at'   => now(),
                                    ]);
                            }
                        }
                    }

                    // =========================
                    // ✅ Delete delivery items + deliveries (soft delete)
                    // =========================
                    $deliveryIds = $deliveries->pluck('id')->map(fn($v) => (int)$v)->toArray();

                    if (!empty($deliveryIds)) {
                        SaleDeliveryItem::withoutGlobalScopes()
                            ->whereIn('sale_delivery_id', $deliveryIds)
                            ->delete();

                        SaleDelivery::withoutGlobalScopes()
                            ->whereIn('id', $deliveryIds)
                            ->delete();
                    }
                }

                // =========================
                // ✅ Delete sale details + sale (soft delete)
                // =========================
                SaleDetails::withoutGlobalScopes()
                    ->where('sale_id', (int) $s->id)
                    ->delete();

                $s->delete();
            });

            toast('Sale Deleted (soft)!', 'warning');
            return redirect()->route('sales.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('sales.index');
        }
    }

    /**
     * Increment qty_reserved pada pool stock (warehouse_id NULL).
     * Dipakai saat RESTORE walk-in sale + pending delivery, karena saat destroy kamu sudah rollback reserved.
     */
    private function incrementReservedPoolStock(int $branchId, array $reservedAddByProduct, string $reference): void
    {
        if ($branchId <= 0) return;

        foreach ($reservedAddByProduct as $productId => $qtyAdd) {
            $productId = (int) $productId;
            $qtyAdd    = (int) $qtyAdd;

            if ($productId <= 0 || $qtyAdd <= 0) continue;

            $row = DB::table('stocks')
                ->where('branch_id', (int) $branchId)
                ->whereNull('warehouse_id')
                ->where('product_id', (int) $productId)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::table('stocks')->insert([
                    'product_id'     => (int) $productId,
                    'branch_id'      => (int) $branchId,
                    'warehouse_id'   => null,
                    'qty_total'  => 0,
                    'qty_reserved'   => 0,
                    'qty_incoming'   => 0,
                    'min_stock'      => 0,
                    'note'           => 'Auto created by restore reserved. Ref: ' . $reference,
                    'created_by'     => auth()->id(),
                    'updated_by'     => auth()->id(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $row = (object) ['qty_reserved' => 0];
            }

            $current = (int) ($row->qty_reserved ?? 0);

            DB::table('stocks')
                ->where('branch_id', (int) $branchId)
                ->whereNull('warehouse_id')
                ->where('product_id', (int) $productId)
                ->update([
                    'qty_reserved' => $current + $qtyAdd,
                    'updated_by'   => auth()->id(),
                    'updated_at'   => now(),
                ]);
        }
    }

    public function restore(int $id)
    {
        abort_if(Gate::denies('delete_sales'), 403);

        $branchId = BranchContext::id();

        try {
            DB::transaction(function () use ($id, $branchId) {

                $sale = Sale::withTrashed()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($id);

                // branch guard
                if (Schema::hasColumn('sales', 'branch_id')) {
                    if ((int) ($sale->branch_id ?? 0) !== (int) $branchId) {
                        abort(403, 'Active branch mismatch for this Sale.');
                    }
                }

                // ✅ only add reserved back if this restore actually changes state from trashed -> active
                $saleWasTrashed = $sale->trashed();

                // restore sale header
                if ($saleWasTrashed) {
                    $sale->restore();
                }

                // restore sale details
                SaleDetails::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('sale_id', (int) $sale->id)
                    ->restore();

                /**
                 * ✅ Restore related SaleDelivery ONLY for WALK-IN lifecycle:
                 * delivery.sale_id = sale.id AND delivery.sale_order_id IS NULL
                 */
                $walkinDeliveries = SaleDelivery::withTrashed()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->where('sale_id', (int) $sale->id)
                    ->whereNull('sale_order_id')
                    ->get();

                // ✅ reserved add map (only for deliveries that were actually restored from trashed)
                $reservedAddByProduct = [];

                foreach ($walkinDeliveries as $del) {
                    // extra safety: branch guard delivery
                    if ((int) ($del->branch_id ?? 0) !== (int) $branchId) {
                        throw new \RuntimeException('Cannot restore: related Sale Delivery belongs to different branch.');
                    }

                    $deliveryWasTrashed = $del->trashed();

                    if ($deliveryWasTrashed) {
                        $del->restore();
                    }

                    // ✅ IMPORTANT:
                    // - reserved pool hanya relevan untuk delivery PENDING (belum confirmed stock-out)
                    // - dan hanya jika delivery benar-benar baru di-restore (biar gak double-add)
                    $st = strtolower(trim((string) ($del->getRawOriginal('status') ?? $del->status ?? 'pending')));
                    if ($saleWasTrashed && $deliveryWasTrashed && $st === 'pending') {

                        // Ensure items restored (idempotent)
                        SaleDeliveryItem::withTrashed()
                            ->withoutGlobalScopes()
                            ->where('sale_delivery_id', (int) $del->id)
                            ->restore();

                        $items = SaleDeliveryItem::withoutGlobalScopes()
                            ->where('sale_delivery_id', (int) $del->id)
                            ->get(['product_id', 'quantity']);

                        foreach ($items as $it) {
                            $pid = (int) ($it->product_id ?? 0);
                            $qty = (int) ($it->quantity ?? 0);
                            if ($pid <= 0 || $qty <= 0) continue;

                            if (!isset($reservedAddByProduct[$pid])) $reservedAddByProduct[$pid] = 0;
                            $reservedAddByProduct[$pid] += $qty;
                        }
                    } else {
                        // kalau bukan pending / bukan newly restored, tetap restore items kalau kamu butuh tampilan data
                        // (optional, tapi aman)
                        if ($deliveryWasTrashed) {
                            SaleDeliveryItem::withTrashed()
                                ->withoutGlobalScopes()
                                ->where('sale_delivery_id', (int) $del->id)
                                ->restore();
                        }
                    }
                }

                // ✅ add back reserved pool (warehouse_id NULL)
                if (!empty($reservedAddByProduct)) {
                    $this->incrementReservedPoolStock((int) $branchId, $reservedAddByProduct, (string) ($sale->reference ?? ('SALE#'.$sale->id)));
                }
            });

            toast('Sale Restored!', 'success');
            return redirect()->route('sales.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('sales.index');
        }
    }

    public function forceDestroy(int $id)
    {
        abort_if(Gate::denies('delete_sales'), 403);

        $branchId = BranchContext::id();

        try {
            DB::transaction(function () use ($id, $branchId) {

                $sale = Sale::withTrashed()
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($id);

                // branch guard
                if (Schema::hasColumn('sales', 'branch_id')) {
                    if ((int) ($sale->branch_id ?? 0) !== (int) $branchId) {
                        abort(403, 'Active branch mismatch for this Sale.');
                    }
                }

                if (!$sale->trashed()) {
                    throw new \RuntimeException('Force delete only allowed after soft delete.');
                }

                // ✅ extra safety: pastikan tidak ada payment record nyangkut
                $hasPayments = SalePayment::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('sale_id', (int) $sale->id)
                    ->exists();

                if ($hasPayments) {
                    throw new \RuntimeException('Cannot force delete: Sale still has payment records.');
                }

                // =========================
                // ✅ Force delete related deliveries + items
                // =========================
                $deliveryIds = SaleDelivery::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('sale_id', (int) $sale->id)
                    ->pluck('id')
                    ->map(fn($v) => (int) $v)
                    ->toArray();

                if (!empty($deliveryIds)) {
                    SaleDeliveryItem::withTrashed()
                        ->withoutGlobalScopes()
                        ->whereIn('sale_delivery_id', $deliveryIds)
                        ->forceDelete();

                    SaleDelivery::withTrashed()
                        ->withoutGlobalScopes()
                        ->whereIn('id', $deliveryIds)
                        ->forceDelete();
                }

                // =========================
                // ✅ Force delete sale details
                // =========================
                SaleDetails::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('sale_id', (int) $sale->id)
                    ->forceDelete();

                // =========================
                // ✅ Force delete sale header
                // =========================
                $sale->forceDelete();
            });

            toast('Sale Deleted Permanently!', 'warning');
            return redirect()->route('sales.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('sales.index');
        }
    }
}
