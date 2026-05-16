<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalesDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\People\Entities\Customer;
use Modules\People\Entities\CustomerVehicle;
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

    private function resolveHeaderDiscount($request, int $itemsSubtotal, float $taxPercentage, int $fee, int $shipping): array
    {
        $discountType = (string) ($request->discount_type ?? 'percentage');
        $discountType = $discountType === 'fixed' ? 'fixed' : 'percentage';
        $taxPercentage = max(0, min(100, round((float) $taxPercentage, 2)));

        $rawValue = $request->header_discount_value ?? $request->discount_percentage ?? 0;
        $discountValue = is_numeric($rawValue) ? (float) $rawValue : 0.0;

        if ($discountValue < 0) {
            throw new \RuntimeException('Discount cannot be negative.');
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            throw new \RuntimeException('Discount percentage cannot exceed 100%.');
        }

        if ($discountType === 'fixed') {
            $discountAmount = min((int) round($discountValue), max(0, $itemsSubtotal));

            $discountPercentage = $itemsSubtotal > 0
                ? round(($discountAmount / $itemsSubtotal) * 100, 2)
                : 0.0;
        } else {
            $discountPercentage = round($discountValue, 2);
            $discountAmount = (int) floor($itemsSubtotal * ($discountPercentage / 100));
        }

        $taxBase = max(0, $itemsSubtotal - $discountAmount);
        $taxAmount = (int) floor($taxBase * ($taxPercentage / 100));
        $grandTotal = $taxBase + $taxAmount + $fee + $shipping;
        if ($grandTotal < 0) {
            throw new \RuntimeException('Grand Total cannot be negative.');
        }

        return [
            'percentage' => (float) $discountPercentage,
            'amount' => (int) $discountAmount,
            'tax_amount' => (int) $taxAmount,
            'tax_base' => (int) $taxBase,
            'grand_total' => (int) $grandTotal,
        ];
    }

    private function getInventoryAvailableStockSnapshot(int $branchId, int $productId): array
    {
        if ($branchId <= 0 || $productId <= 0) {
            return [
                'total' => 0,
                'good' => 0,
                'reserved' => 0,
                'damaged' => 0,
                'sellable' => 0,
            ];
        }

        $stockRow = DB::table('stocks')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(qty_total), 0) as total_qty, COALESCE(SUM(qty_reserved), 0) as reserved_qty')
            ->first();

        $total = max(0, (int) ($stockRow->total_qty ?? 0));
        $reserved = max(0, (int) ($stockRow->reserved_qty ?? 0));

        $defect = max(0, (int) DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->sum('quantity'));

        $damaged = max(0, (int) DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->sum('quantity'));

        return [
            'total' => (int) $total,
            'good' => (int) max(0, $total - $defect - $damaged),
            'reserved' => (int) $reserved,
            'damaged' => (int) $damaged,
            'sellable' => (int) max(0, max(0, $total - $damaged) - $reserved),
        ];
    }

    private function normalizeSaleDetailInstallationType($value): string
    {
        return (string) $value === 'with_installation' ? 'with_installation' : 'item_only';
    }

    private function resolveSaleDetailInstallationMetadata($cartItem, int $customerId, int $branchId): array
    {
        $installationType = $this->normalizeSaleDetailInstallationType($cartItem->options->installation_type ?? 'item_only');

        if ($installationType !== 'with_installation') {
            return ['item_only', null];
        }

        $vehicleId = (int) ($cartItem->options->customer_vehicle_id ?? 0);
        if ($customerId <= 0 || $vehicleId <= 0) {
            return ['with_installation', null];
        }

        $vehicleExists = CustomerVehicle::query()
            ->where('id', $vehicleId)
            ->where('customer_id', $customerId)
            ->when($branchId > 0, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->exists();

        if (!$vehicleExists) {
            throw ValidationException::withMessages([
                'customer_vehicle_id' => 'Selected vehicle does not belong to the selected customer.',
            ]);
        }

        return ['with_installation', $vehicleId];
    }

    private function effectiveSaleBranchId(Sale $sale): ?int
    {
        $activeBranchId = BranchContext::id();
        $saleBranchId = (int) ($sale->branch_id ?? 0);

        if ($activeBranchId && $saleBranchId > 0) {
            abort_if($saleBranchId !== (int) $activeBranchId, 403, 'Active branch mismatch for this Sale.');

            return (int) $activeBranchId;
        }

        return $saleBranchId > 0 ? $saleBranchId : null;
    }

    private function assertSaleEditable(Sale $sale, int $branchId, bool $lockRelated = false): Sale
    {
        $saleQuery = Sale::withoutGlobalScopes();
        if ($lockRelated) {
            $saleQuery->lockForUpdate();
        }

        $lockedSale = $saleQuery->findOrFail((int) $sale->id);

        if (Schema::hasColumn('sales', 'branch_id') && $branchId > 0) {
            if ((int) ($lockedSale->branch_id ?? 0) !== (int) $branchId) {
                abort(403, 'Active branch mismatch for this Sale.');
            }
        }

        if (strtolower(trim((string) ($lockedSale->payment_status ?? ''))) !== 'unpaid') {
            throw new \RuntimeException('This sale cannot be edited because its payment status is not Unpaid.');
        }

        if ((int) ($lockedSale->paid_amount ?? 0) > 0) {
            throw new \RuntimeException('This sale cannot be edited because it already has payment amount.');
        }

        $paymentsQuery = SalePayment::withoutGlobalScopes()->where('sale_id', (int) $lockedSale->id);
        if ($lockRelated) {
            $paymentsQuery->lockForUpdate();
        }

        if ($paymentsQuery->exists()) {
            throw new \RuntimeException('This sale cannot be edited because it already has payment records.');
        }

        $deliveriesQuery = SaleDelivery::withoutGlobalScopes()->where('sale_id', (int) $lockedSale->id);
        if ($lockRelated) {
            $deliveriesQuery->lockForUpdate();
        }

        if ($deliveriesQuery->exists()) {
            throw new \RuntimeException('This sale cannot be edited because it already has related Sale Delivery records.');
        }

        return $lockedSale;
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

    private function refreshSaleOrderFulfillmentStatus(int $saleOrderId): void
    {
        if ($saleOrderId <= 0) return;

        $so = \Modules\SaleOrder\Entities\SaleOrder::query()
            ->lockForUpdate()
            ->with(['items'])
            ->find($saleOrderId);

        if (!$so) return;

        $deliveredExpr = "CASE
            WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0
                THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))
            ELSE COALESCE(sdi.quantity,0)
        END";

        $deliveredByItem = Schema::hasColumn('sale_delivery_items', 'sale_order_item_id')
            ? DB::table('sale_delivery_items as sdi')
                ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
                ->where('sd.sale_order_id', (int) $saleOrderId)
                ->whereNull('sd.deleted_at')
                ->whereNull('sdi.deleted_at')
                ->whereIn(DB::raw('LOWER(COALESCE(sd.status,""))'), ['confirmed', 'partial'])
                ->whereNotNull('sdi.sale_order_item_id')
                ->select('sdi.sale_order_item_id', DB::raw("SUM({$deliveredExpr}) as qty"))
                ->groupBy('sdi.sale_order_item_id')
                ->pluck('qty', 'sale_order_item_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->toArray()
            : [];

        $remainingByProduct = [];
        if (empty($deliveredByItem)) {
            $orderedByProduct = [];
            foreach ($so->items as $item) {
                $pid = (int) ($item->product_id ?? 0);
                if ($pid <= 0) continue;
                if (!isset($orderedByProduct[$pid])) $orderedByProduct[$pid] = 0;
                $orderedByProduct[$pid] += max(0, (int) ($item->quantity ?? 0));
            }

            $deliveredByProduct = DB::table('sale_delivery_items as sdi')
                ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
                ->where('sd.sale_order_id', (int) $saleOrderId)
                ->whereNull('sd.deleted_at')
                ->whereNull('sdi.deleted_at')
                ->whereIn(DB::raw('LOWER(COALESCE(sd.status,""))'), ['confirmed', 'partial'])
                ->select('sdi.product_id', DB::raw("SUM({$deliveredExpr}) as qty"))
                ->groupBy('sdi.product_id')
                ->pluck('qty', 'product_id')
                ->map(fn ($qty) => max(0, (int) $qty))
                ->toArray();

            foreach ($orderedByProduct as $pid => $orderedQty) {
                $remainingByProduct[(int) $pid] = max(0, (int) $orderedQty - max(0, (int) ($deliveredByProduct[(int) $pid] ?? 0)));
            }
        }

        $totalRemaining = 0;
        $totalOrdered = 0;

        foreach ($so->items as $it) {
            $ordered = max(0, (int) ($it->quantity ?? 0));
            $totalOrdered += $ordered;

            if (!empty($deliveredByItem)) {
                $delivered = max(0, (int) ($deliveredByItem[(int) $it->id] ?? 0));
                $totalRemaining += max(0, $ordered - $delivered);
            } else {
                $pid = (int) ($it->product_id ?? 0);
                $available = max(0, (int) ($remainingByProduct[$pid] ?? 0));
                $rem = min($ordered, $available);
                $remainingByProduct[$pid] = max(0, $available - $rem);
                $totalRemaining += $rem;
            }
        }

        if ($totalOrdered <= 0) {
            if ((string) $so->status !== 'pending') {
                $so->update(['status' => 'pending', 'updated_by' => auth()->id()]);
            }
            return;
        }

        if ($totalRemaining <= 0) $newStatus = 'delivered';
        elseif ($totalRemaining < $totalOrdered) $newStatus = 'partial_delivered';
        else $newStatus = 'pending';

        if ($newStatus === 'delivered') {
            $confirmedCount = (int) DB::table('sale_deliveries')
                ->where('sale_order_id', (int) $so->id)
                ->where('branch_id', (int) $so->branch_id)
                ->whereRaw('LOWER(COALESCE(status,"")) = ?', ['confirmed'])
                ->count();

            $invoicedConfirmedCount = (int) DB::table('sale_deliveries')
                ->where('sale_order_id', (int) $so->id)
                ->where('branch_id', (int) $so->branch_id)
                ->whereRaw('LOWER(COALESCE(status,"")) = ?', ['confirmed'])
                ->whereNotNull('sale_id')
                ->count();

            $allConfirmedInvoiced = ($confirmedCount > 0 && $confirmedCount === $invoicedConfirmedCount);
            if ($allConfirmedInvoiced) {
                $newStatus = 'completed';
            }
        }

        if ((string) $so->status !== $newStatus) {
            $so->update(['status' => $newStatus, 'updated_by' => auth()->id()]);
        }
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

        // invoice items subtotal (qty * shown price)  => NOTE: shown price kita pakai NET (SO price)
        $invoiceItemsSubtotal = 0;
        $saleOrderGrandTotal = 0;

        $invoiceEstimatedGrand = 0;
        $dpAllocated = 0;
        $suggestedPayNow = 0;

        // INFO discount (buat tampilan di kolom item / info SO), TAPI TIDAK dipakai ngurangin summary lagi
        $deliveryDiscountInfoTotal = 0.0;

        if ($saleDeliveryId > 0) {
            $delivery = \Modules\SaleDelivery\Entities\SaleDelivery::with(['items.product'])->find($saleDeliveryId);

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

                // Build SO item groups by product. Duplicate same-product rows may carry
                // different installation metadata, so do not collapse to one row.
                $soItemsByProduct = [];
                $soItemsById = [];
                if (!empty($lockedSaleOrder) && (int)$lockedSaleOrder->id > 0) {
                    $soItems = \Modules\SaleOrder\Entities\SaleOrderItem::query()
                        ->where('sale_order_id', (int)$lockedSaleOrder->id)
                        ->orderBy('id')
                        ->get();

                    foreach ($soItems as $row) {
                        $pid = (int) ($row->product_id ?? 0);
                        if ($pid <= 0) continue;
                        if (!isset($soItemsByProduct[$pid])) {
                            $soItemsByProduct[$pid] = [];
                        }
                        $soItemsByProduct[$pid][] = $row;
                        $soItemsById[(int) $row->id] = $row;
                    }
                }

                $deliveryWarehouseId = (int) ($delivery->warehouse_id ?? 0);
                $deliveryWarehouseName = null;

                if ($deliveryWarehouseId > 0) {
                    $wh = \Modules\Product\Entities\Warehouse::find($deliveryWarehouseId);
                    $deliveryWarehouseName = $wh?->warehouse_name;
                }

                $getBranchStockSnapshot = function (int $productId) use ($branchId) {
                    return $this->getInventoryAvailableStockSnapshot((int) $branchId, (int) $productId);
                };

                $invoiceableMap = $this->getInvoiceableQtyMapByDelivery($delivery);

                $productIds = collect($delivery->items ?? [])
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $productMap = empty($productIds)
                    ? collect()
                    : \Modules\Product\Entities\Product::withoutGlobalScopes()
                        ->whereIn('id', $productIds)
                        ->get(['id', 'product_name', 'product_code', 'product_unit', 'product_price', 'product_cost'])
                        ->keyBy('id');

                $remainingInvoiceableByProduct = [];
                foreach (($invoiceableMap ?? []) as $pid => $mapRow) {
                    $remainingInvoiceableByProduct[(int) $pid] = (int) ($mapRow['remaining'] ?? 0);
                }

                foreach (($delivery->items ?? []) as $it) {
                    $productId = (int) ($it->product_id ?? 0);
                    if ($productId <= 0) continue;

                    $deliveredQty = (int) ($invoiceableMap[$productId]['delivered'] ?? $this->deliveredQtyFromDeliveryItem($it));
                    $alreadyInvoicedQty = (int) ($invoiceableMap[$productId]['already_invoiced'] ?? 0);
                    $remainingInvoiceableQty = (int) ($remainingInvoiceableByProduct[$productId] ?? 0);

                    if ($remainingInvoiceableQty <= 0) continue;

                    $p = $productMap->get($productId);
                    $sourceSaleItem = null;
                    if (!empty($it->sale_item_id)) {
                        $sourceSaleItem = \Modules\Sale\Entities\SaleDetails::query()
                            ->where('id', (int) $it->sale_item_id)
                            ->first();
                    }

                    $sourceRow = null;
                    if (!empty($it->sale_order_item_id)) {
                        $sourceRow = $soItemsById[(int) $it->sale_order_item_id] ?? null;
                    } elseif ($sourceSaleItem) {
                        $sourceRow = $sourceSaleItem;
                    }

                    if (!$sourceRow) {
                        $sourceRow = (object) [
                            'id' => 0,
                            'product_id' => $productId,
                            'quantity' => $remainingInvoiceableQty,
                            'unit_price' => $it->unit_price ?? ($p->product_price ?? 0),
                            'price' => $it->price ?? ($it->unit_price ?? ($p->product_price ?? 0)),
                            'product_discount_amount' => $it->product_discount_amount ?? 0,
                            'product_discount_type' => $it->product_discount_type ?? 'fixed',
                            'product_tax_amount' => $it->product_tax_amount ?? 0,
                            'installation_type' => 'item_only',
                            'customer_vehicle_id' => null,
                        ];
                    }

                    $qtyToSkip = max(0, (int) $alreadyInvoicedQty);
                    $qtyToAllocate = max(0, (int) $remainingInvoiceableQty);

                    $sourceQty = max(0, (int) ($sourceRow->quantity ?? $remainingInvoiceableQty));
                    if ($sourceQty <= 0) continue;

                    if ($qtyToSkip >= $sourceQty) {
                        $qtyToSkip -= $sourceQty;
                        continue;
                    }

                    $availableFromRow = $sourceQty - $qtyToSkip;
                    $qtyToSkip = 0;
                    $rowInvoiceQty = min($qtyToAllocate, $availableFromRow);
                    if ($rowInvoiceQty <= 0) continue;

                        $unitPrice = (float) (
                            $sourceRow->unit_price
                            ?? $it->unit_price
                            ?? ($p->product_price ?? 0)
                        );

                        $priceShown = (float) (
                            $sourceRow->price
                            ?? $it->price
                            ?? $unitPrice
                        );

                        $discAmt = (float) (
                            $sourceRow->product_discount_amount
                            ?? $it->product_discount_amount
                            ?? 0
                        );
                        $discType = strtolower((string) (
                            $sourceRow->product_discount_type
                            ?? $it->product_discount_type
                            ?? 'fixed'
                        ));

                        if ($discAmt <= 0 && $unitPrice > 0 && $unitPrice > $priceShown) {
                            $discAmt = (float) ($unitPrice - $priceShown);
                            $discType = 'fixed';
                        }

                        if ($discAmt > 0) {
                            $deliveryDiscountInfoTotal += ($discType === 'fixed')
                                ? ($discAmt * $rowInvoiceQty)
                                : (($unitPrice * $rowInvoiceQty) * ($discAmt / 100));
                        }

                        $productTax = (float) ($sourceRow->product_tax_amount ?? $it->product_tax_amount ?? 0);
                        $branchStockSnapshot = $getBranchStockSnapshot($productId);
                        $subTotal = (float) ($priceShown * $rowInvoiceQty);
                        $invoiceItemsSubtotal += (int) round($subTotal);

                        $installationType = (string) ($sourceRow->installation_type ?? 'item_only') === 'with_installation'
                            ? 'with_installation'
                            : 'item_only';
                        $customerVehicleId = $installationType === 'with_installation'
                            ? ((int) ($sourceRow->customer_vehicle_id ?? 0) ?: null)
                            : null;
                        $soItemId = (int) ($it->sale_order_item_id ?? 0);
                        $saleItemId = (int) ($it->sale_item_id ?? 0);

                        $cart->add([
                            'id'      => $productId,
                            'name'    => (string) ($p?->product_name ?? '-'),
                            'qty'     => $rowInvoiceQty,
                            'price'   => $priceShown,
                            'weight'  => 1,
                            'options' => [
                                'sub_total'             => $subTotal,
                                'code'                  => (string) ($p?->product_code ?? 'UNKNOWN'),
                                'unit'                  => trim((string) ($p?->product_unit ?? '')) !== ''
                                    ? (string) $p->product_unit
                                    : 'Unit',
                                'stock'                 => (int) ($branchStockSnapshot['sellable'] ?? 0),
                                'reserved_stock'        => (int) ($branchStockSnapshot['reserved'] ?? 0),
                                'sellable_stock'        => (int) ($branchStockSnapshot['sellable'] ?? 0),
                                'stock_scope'           => 'branch',
                                'warehouse_id'          => $deliveryWarehouseId,
                                'warehouse_name'        => $deliveryWarehouseName,
                                'invoice_source'        => 'sale_delivery',
                                'delivered_qty'         => (int) $deliveredQty,
                                'already_invoiced_qty'  => (int) $alreadyInvoicedQty,
                                'remaining_invoiceable_qty' => (int) $remainingInvoiceableQty,
                                'current_stock_qty'     => (int) ($branchStockSnapshot['sellable'] ?? 0),
                                'product_tax'           => $productTax,
                                'unit_price'            => $unitPrice,
                                'product_discount'      => $discAmt,
                                'product_discount_type' => $discType,
                                'line_key'              => $soItemId > 0
                                    ? ('sale_order_item_' . $soItemId . '_delivery_item_' . (int) $it->id)
                                    : ($saleItemId > 0
                                        ? ('sale_item_' . $saleItemId . '_delivery_item_' . (int) $it->id)
                                        : ('sale_delivery_item_' . (int) $it->id)),
                                'installation_type'     => $installationType,
                                'customer_vehicle_id'   => $customerVehicleId,
                                'product_cost'          => (float) (
                                    $it->product_cost
                                    ?? (($branchId > 0 && $productId > 0)
                                        ? $hppService->getCurrentHpp((int) $branchId, (int) $productId)
                                        : ($p->product_cost ?? 0))
                                ),
                            ],
                        ]);

                        $qtyToAllocate -= $rowInvoiceQty;
                        $remainingInvoiceableByProduct[$productId] = max(0, (int) $remainingInvoiceableByProduct[$productId] - $rowInvoiceQty);
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
                        ->keyBy('id');

                    foreach (($lockedDelivery->items ?? []) as $it) {
                        $pid = (int) ($it->product_id ?? 0);
                        $qty = $this->deliveredQtyFromDeliveryItem($it);
                        if ($pid <= 0 || $qty <= 0) continue;

                        $soRow = !empty($it->sale_order_item_id)
                            ? $soItems->get((int) $it->sale_order_item_id)
                            : null;

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
                        foreach ($needByProduct as $pid => $qtyNeed) {
                            $pid = (int) $pid;
                            $qtyNeed = (int) $qtyNeed;
                            if ($pid <= 0 || $qtyNeed <= 0) continue;

                            DB::table('stocks')
                                ->where('branch_id', (int) $branchId)
                                ->where('product_id', (int) $pid)
                                ->lockForUpdate()
                                ->get();

                            $snapshot = $this->getInventoryAvailableStockSnapshot((int) $branchId, (int) $pid);
                            $availableToReserve = (int) ($snapshot['sellable'] ?? 0);

                            if ($qtyNeed > $availableToReserve) {
                                // bikin message yang jelas supaya admin ngerti
                                $p = \Modules\Product\Entities\Product::find($pid);
                                $code = $p?->product_code ?? ('#' . $pid);
                                $name = $p?->product_name ?? '';

                                throw new \RuntimeException(
                                    "Stock tidak cukup untuk {$code} {$name}. " .
                                    "Requested: {$qtyNeed}, Available (after reserved): {$availableToReserve}, " .
                                    "Good: " . (int) ($snapshot['good'] ?? 0) . ", Reserved: " . (int) ($snapshot['reserved'] ?? 0) . ". " .
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

                $headerDiscount = $this->resolveHeaderDiscount(
                    $request,
                    (int) $itemsSubtotal,
                    (float) $effectiveTaxPct,
                    (int) $effectiveFee,
                    (int) $effectiveShipping
                );
                $effectiveDiscPct = (float) $headerDiscount['percentage'];
                $discountAmount = (int) $headerDiscount['amount'];
                $taxAmount = (int) $headerDiscount['tax_amount'];
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
                    'status' => 'Pending',
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

                    [$installationType, $customerVehicleId] = $this->resolveSaleDetailInstallationMetadata(
                        $cart_item,
                        (int) $customer->id,
                        (int) $branchId
                    );

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
                        'installation_type' => $installationType,
                        'customer_vehicle_id' => $customerVehicleId,
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
                        $this->refreshSaleOrderFulfillmentStatus((int) $lockedSaleOrder->id);
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

                $sale->refresh();
                $sale->loadMissing(['saleDeliveries']);
                $sale->syncBusinessStatus();

                Cart::instance('sale')->destroy();

                // ======================================================
                // Accounting Transaction (pakai total_cost yg sudah benar)
                // ======================================================
                $saleReceivableSubaccount = Helper::resolveAccountingMapping('sale', 'receivable', $branchId, null, '1-10100');
                $saleRevenueSubaccount = Helper::resolveAccountingMapping('sale', 'revenue', $branchId, null, '4-40000');
                $saleCogsSubaccount = Helper::resolveAccountingMapping('sale', 'cogs', $branchId, null, '5-50000');
                $saleInventorySubaccount = Helper::resolveAccountingMapping('sale', 'inventory', $branchId, null, '1-10200');

                if ($total_cost <= 0) {
                    Helper::addNewTransaction([
                        'branch_id' => $branchId,
                        'date' => $sale->date,
                        'label' => "Sale Invoice for #" . $sale->reference,
                        'description' => "Order ID: " . $sale->reference,
                        'source_type' => 'sale',
                        'source_id' => $sale->id,
                        'purchase_id' => null,
                        'purchase_payment_id' => null,
                        'purchase_return_id' => null,
                        'purchase_return_payment_id' => null,
                        'sale_id' => $sale->id,
                        'sale_payment_id' => null,
                        'sale_return_id' => null,
                        'sale_return_payment_id' => null,
                    ], [
                        ['subaccount_number' => $saleReceivableSubaccount, 'amount' => $sale->total_amount, 'type' => 'debit'],
                        ['subaccount_number' => $saleRevenueSubaccount, 'amount' => $sale->total_amount, 'type' => 'credit'],
                    ]);
                } else {
                    Helper::addNewTransaction([
                        'branch_id' => $branchId,
                        'date' => $sale->date,
                        'label' => "Sale Invoice for #" . $sale->reference,
                        'description' => "Order ID: " . $sale->reference,
                        'source_type' => 'sale',
                        'source_id' => $sale->id,
                        'purchase_id' => null,
                        'purchase_payment_id' => null,
                        'purchase_return_id' => null,
                        'purchase_return_payment_id' => null,
                        'sale_id' => $sale->id,
                        'sale_payment_id' => null,
                        'sale_return_id' => null,
                        'sale_return_payment_id' => null,
                    ], [
                        ['subaccount_number' => $saleReceivableSubaccount, 'amount' => $sale->total_amount, 'type' => 'debit'],
                        ['subaccount_number' => $saleCogsSubaccount, 'amount' => $total_cost, 'type' => 'debit'],
                        ['subaccount_number' => $saleRevenueSubaccount, 'amount' => $sale->total_amount, 'type' => 'credit'],
                        ['subaccount_number' => $saleInventorySubaccount, 'amount' => $total_cost, 'type' => 'credit'],
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
                        'branch_id' => $branchId,
                        'date' => $sale->date,
                        'label' => "Payment for Sales Order #" . $sale->reference,
                        'description' => "Sale ID: " . $sale->reference,
                        'source_type' => 'sale_payment',
                        'source_id' => $created_payment->id,
                        'purchase_id' => null,
                        'purchase_payment_id' => null,
                        'purchase_return_id' => null,
                        'purchase_return_payment_id' => null,
                        'sale_id' => null,
                        'sale_payment_id' => $created_payment->id,
                        'sale_return_id' => null,
                        'sale_return_payment_id' => null,
                    ], [
                        ['subaccount_number' => $created_payment->deposit_code, 'amount' => $created_payment->amount, 'type' => 'debit'],
                        ['subaccount_number' => Helper::resolveAccountingMapping('sale_payment', 'receivable', $branchId, null, '1-10100'), 'amount' => $created_payment->amount, 'type' => 'credit'],
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
        // Create SaleDeliveryItems from each SaleDetails row.
        // ======================================================
        // ✅ NEW: build qty map untuk reserved pool
        $reservedAddByProduct = [];

        foreach ($details as $detail) {
            $pid = (int) ($detail->product_id ?? 0);
            $qty = (int) ($detail->quantity ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;

            $price = (int) ($detail->price ?? 0);

            SaleDeliveryItem::create([
                'sale_delivery_id' => (int) $delivery->id,
                'product_id' => $pid,
                'sale_item_id' => (int) $detail->id,
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

        $branchId = $this->effectiveSaleBranchId($sale);

        $sale->load(['creator', 'updater', 'saleDetails.customerVehicle', 'branch']);
        $branch = $sale->branch ?: ($branchId ? Branch::withoutGlobalScopes()->find((int) $branchId) : null);

        $customer = Customer::query()
            ->where('id', $sale->customer_id)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', (int) $branchId);
                });
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

        $branchId = $this->effectiveSaleBranchId($sale);

        $sale->load(['creator', 'updater', 'saleDetails.customerVehicle']);

        $customer = Customer::query()
            ->where('id', $sale->customer_id)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', (int) $branchId);
                });
            })
            ->firstOrFail();

        $saleDeliveries = SaleDelivery::query()
            ->where('sale_id', (int) $sale->id)
            ->when($branchId, fn ($q) => $q->where('branch_id', (int) $branchId))
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
                            'sale_order_id' => (int) $saleOrder->id,
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

        $branchId = BranchContext::id();

        try {
            $sale = $this->assertSaleEditable($sale, (int) $branchId);
        } catch (\Throwable $e) {
            toast($e->getMessage(), 'error');
            return redirect()->route('sales.show', $sale->id);
        }

        $sale_details = $sale->saleDetails;

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
                    'unit_price'  => $sale_detail->unit_price,
                    'line_key'    => 'sale_detail_' . (int) $sale_detail->id,
                    'installation_type' => $this->normalizeSaleDetailInstallationType($sale_detail->installation_type ?? 'item_only'),
                    'customer_vehicle_id' => $sale_detail->customer_vehicle_id,
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
        try {
            DB::transaction(function () use ($request, $sale) {
                $branchId = (int) BranchContext::id();
                $sale = $this->assertSaleEditable($sale, $branchId, true);

                $customer = Customer::query()
                ->where('id', $request->customer_id)
                ->when($branchId > 0, function ($query) use ($branchId) {
                    $query->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    });
                })
                ->firstOrFail();

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
            $headerDiscount = $this->resolveHeaderDiscount(
                $request,
                (int) $itemsSubtotal,
                (float) $taxPct,
                (int) $fee,
                (int) $shipping
            );

            $discountPercentage = (float) $headerDiscount['percentage'];
            $discountAmount = (int) $headerDiscount['amount'];
            $taxAmount = (int) $headerDiscount['tax_amount'];
            $computedGrandTotal = (int) $headerDiscount['grand_total'];

            $due_amount = $computedGrandTotal - (int) $request->paid_amount;

            if ($due_amount == $computedGrandTotal) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
                $due_amount = 0;
            }

            foreach ($sale->saleDetails as $sale_detail) {
                $sale_detail->delete();
            }

            $saleUpdateData = [
                'date' => $request->date,
                'reference' => $request->reference,
                'customer_id' => $customer->id,
                'customer_name' => $customer->customer_name,
                'tax_percentage' => (float) $taxPct,
                'discount_percentage' => (float) $discountPercentage,
                'shipping_amount' => (int) $shipping,
                'fee_amount' => (int) $fee,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => (int) $computedGrandTotal,
                'total_quantity' => (int) $totalQty,
                'due_amount' => $due_amount * 1,
                'status' => 'Pending',
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
            $sale->refresh();
            $sale->loadMissing(['saleDeliveries']);
            $sale->syncBusinessStatus();

            foreach (Cart::instance('sale')->content() as $cart_item) {
                [$installationType, $customerVehicleId] = $this->resolveSaleDetailInstallationMetadata(
                    $cart_item,
                    (int) $customer->id,
                    (int) $branchId
                );

                $saleDetailData = [
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
                    'installation_type' => $installationType,
                    'customer_vehicle_id' => $customerVehicleId,
                ];

                if (Schema::hasColumn('sale_details', 'branch_id')) {
                    $saleDetailData['branch_id'] = $branchId;
                }

                SaleDetails::create($saleDetailData);
            }

            Cart::instance('sale')->destroy();
            });
        } catch (\Throwable $e) {
            toast($e->getMessage(), 'error');
            return redirect()->route('sales.show', $sale->id);
        }

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
