<?php

namespace Modules\SaleDelivery\Http\Controllers\Concerns;

use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Modules\SaleOrder\Entities\SaleOrder;

trait SaleDeliveryShared
{
    private function deliveredQtyExpr(string $alias = 'sdi'): string
    {
        return "CASE
            WHEN (COALESCE({$alias}.qty_good,0) + COALESCE({$alias}.qty_defect,0) + COALESCE({$alias}.qty_damaged,0)) > 0
                THEN (COALESCE({$alias}.qty_good,0) + COALESCE({$alias}.qty_defect,0) + COALESCE({$alias}.qty_damaged,0))
            ELSE COALESCE({$alias}.quantity,0)
        END";
    }

    protected function failBack(string $message, int $status = 422)
    {
        toast($message, 'error');
        return redirect()->back()->withInput();
    }

    protected function ensureSpecificBranchSelected(): void
    {
        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            throw new \RuntimeException("Please choose a specific branch first (not 'All Branch').");
        }
    }

    private function allocationTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('sale_delivery_item_allocations');
    }

    private function hasAllocationsForSaleOrder(int $saleOrderId): bool
    {
        if (!$this->allocationTableExists()) {
            return false;
        }

        return DB::table('sale_delivery_item_allocations')
            ->where('sale_order_id', (int) $saleOrderId)
            ->exists();
    }

    private function getDeliveredQtyBySaleOrderItem(int $saleOrderId): array
    {
        if (!$this->allocationTableExists()) {
            return [];
        }

        return DB::table('sale_delivery_item_allocations as sda')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sda.sale_delivery_id')
            ->where('sd.sale_order_id', (int) $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereIn(DB::raw('LOWER(COALESCE(sd.status,""))'), ['confirmed', 'partial'])
            ->whereNotNull('sda.sale_order_item_id')
            ->select('sda.sale_order_item_id', DB::raw('SUM(COALESCE(sda.quantity,0)) as qty'))
            ->groupBy('sda.sale_order_item_id')
            ->pluck('qty', 'sale_order_item_id')
            ->map(fn ($qty) => max(0, (int) $qty))
            ->toArray();
    }

    private function getPlannedQtyBySaleOrderItem(int $saleOrderId): array
    {
        if (!$this->allocationTableExists()) {
            return [];
        }

        return DB::table('sale_delivery_item_allocations as sda')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sda.sale_delivery_id')
            ->where('sd.sale_order_id', (int) $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereIn(DB::raw('LOWER(COALESCE(sd.status,""))'), ['pending', 'partial'])
            ->whereNotNull('sda.sale_order_item_id')
            ->select('sda.sale_order_item_id', DB::raw('SUM(COALESCE(sda.quantity,0)) as qty'))
            ->groupBy('sda.sale_order_item_id')
            ->pluck('qty', 'sale_order_item_id')
            ->map(fn ($qty) => max(0, (int) $qty))
            ->toArray();
    }

    private function distributeRemainingByItem(int $saleOrderId, array $remainingByProduct): array
    {
        $items = DB::table('sale_order_items')
            ->where('sale_order_id', (int) $saleOrderId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'product_id', 'quantity']);

        $remainingByItem = [];
        $remainingByProduct = array_map(fn ($v) => max(0, (int) $v), $remainingByProduct);

        foreach ($items as $item) {
            $pid = (int) ($item->product_id ?? 0);
            $itemQty = max(0, (int) ($item->quantity ?? 0));

            if ($pid <= 0 || $itemQty <= 0) {
                $remainingByItem[(int) $item->id] = 0;
                continue;
            }

            $available = (int) ($remainingByProduct[$pid] ?? 0);
            $alloc = min($itemQty, $available);

            $remainingByItem[(int) $item->id] = (int) $alloc;
            $remainingByProduct[$pid] = max(0, $available - $alloc);
        }

        return $remainingByItem;
    }

    protected function getRemainingQtyBySaleOrderItem(int $saleOrderId): array
    {
        if ($this->hasAllocationsForSaleOrder($saleOrderId)) {
            $deliveredByItem = $this->getDeliveredQtyBySaleOrderItem($saleOrderId);

            $items = DB::table('sale_order_items')
                ->where('sale_order_id', (int) $saleOrderId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'quantity']);

            $remainingByItem = [];

            foreach ($items as $item) {
                $itemId = (int) ($item->id ?? 0);
                $ordered = max(0, (int) ($item->quantity ?? 0));
                $delivered = max(0, (int) ($deliveredByItem[$itemId] ?? 0));
                $remainingByItem[$itemId] = max(0, $ordered - $delivered);
            }

            return $remainingByItem;
        }

        $remainingByProduct = $this->getRemainingQtyBySaleOrder($saleOrderId);
        return $this->distributeRemainingByItem($saleOrderId, $remainingByProduct);
    }

    protected function getPlannedRemainingQtyBySaleOrderItem(int $saleOrderId): array
    {
        if ($this->hasAllocationsForSaleOrder($saleOrderId)) {
            $deliveredByItem = $this->getDeliveredQtyBySaleOrderItem($saleOrderId);
            $plannedByItem = $this->getPlannedQtyBySaleOrderItem($saleOrderId);

            $items = DB::table('sale_order_items')
                ->where('sale_order_id', (int) $saleOrderId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'quantity']);

            $remainingByItem = [];

            foreach ($items as $item) {
                $itemId = (int) ($item->id ?? 0);
                $ordered = max(0, (int) ($item->quantity ?? 0));
                $delivered = max(0, (int) ($deliveredByItem[$itemId] ?? 0));
                $planned = max(0, (int) ($plannedByItem[$itemId] ?? 0));

                $remainingByItem[$itemId] = max(0, $ordered - $delivered - $planned);
            }

            return $remainingByItem;
        }

        $remainingByProduct = $this->getPlannedRemainingQtyBySaleOrder($saleOrderId);
        return $this->distributeRemainingByItem($saleOrderId, $remainingByProduct);
    }

    protected function updateSaleOrderFulfillmentStatus(int $saleOrderId): void
    {
        $so = SaleOrder::query()
            ->lockForUpdate()
            ->with(['items'])
            ->findOrFail($saleOrderId);

        $remainingByItem = $this->getRemainingQtyBySaleOrderItem((int) $so->id);
        $remainingByProduct = empty($remainingByItem) ? $this->getRemainingQtyBySaleOrder((int) $so->id) : [];

        $totalRemaining = 0;
        $totalOrdered = 0;

        foreach ($so->items as $it) {
            $pid = (int) $it->product_id;
            $ordered = (int) ($it->quantity ?? 0);
            $rem = !empty($remainingByItem)
                ? (int) ($remainingByItem[(int) $it->id] ?? 0)
                : (int) ($remainingByProduct[$pid] ?? 0);

            $totalOrdered += $ordered;
            $totalRemaining += $rem;
        }

        if ($totalOrdered <= 0) {
            if ((string) $so->status !== 'pending') {
                $so->update(['status' => 'pending', 'updated_by' => auth()->id()]);
            }
            return;
        }

        // =========================
        // 1) Fulfillment status (qty delivered)
        // =========================
        if ($totalRemaining <= 0) $newStatus = 'delivered';
        elseif ($totalRemaining < $totalOrdered) $newStatus = 'partial_delivered';
        else $newStatus = 'pending';

        // =========================
        // 2) Invoice completion rule:
        // completed hanya jika:
        // - fulfillment sudah delivered
        // - dan SEMUA Sale Delivery (status confirmed) sudah punya sale_id (invoice)
        // =========================
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
            } else {
                // tetap delivered kalau belum semua invoiced
                $newStatus = 'delivered';
            }
        }

        if ((string) $so->status !== $newStatus) {
            $so->update(['status' => $newStatus, 'updated_by' => auth()->id()]);
        }
    }

    protected function getRemainingQtyBySale(int $saleId): array
    {
        $saleDetails = DB::table('sale_details')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_id', $saleId)
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_id', $saleId)
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw('SUM(
                    CASE
                        WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0
                            THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))
                        ELSE COALESCE(sdi.quantity,0)
                    END
                ) as qty')
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($saleDetails as $row) {
            $pid = (int) $row->product_id;
            $invoiceQty = (int) $row->qty;
            $shippedQty = isset($shipped[$pid]) ? (int) $shipped[$pid]->qty : 0;

            $rem = $invoiceQty - $shippedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }

    protected function getRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        if ($this->hasAllocationsForSaleOrder($saleOrderId)) {
            $deliveredByItem = $this->getDeliveredQtyBySaleOrderItem($saleOrderId);

            $items = DB::table('sale_order_items')
                ->where('sale_order_id', (int) $saleOrderId)
                ->whereNull('deleted_at')
                ->get(['id', 'product_id', 'quantity']);

            $remaining = [];

            foreach ($items as $item) {
                $pid = (int) ($item->product_id ?? 0);
                $orderedQty = max(0, (int) ($item->quantity ?? 0));
                $deliveredQty = max(0, (int) ($deliveredByItem[(int) $item->id] ?? 0));
                $rem = max(0, $orderedQty - $deliveredQty);

                if (!isset($remaining[$pid])) $remaining[$pid] = 0;
                $remaining[$pid] += $rem;
            }

            return $remaining;
        }

        $deliveredExpr = $this->deliveredQtyExpr('sdi');

        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->whereNull('deleted_at')
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw("SUM({$deliveredExpr}) as qty")
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $shippedQty = isset($shipped[$pid]) ? (int) $shipped[$pid]->qty : 0;

            $rem = $orderedQty - $shippedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }

    protected function getPlannedRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        if ($this->hasAllocationsForSaleOrder($saleOrderId)) {
            $deliveredByItem = $this->getDeliveredQtyBySaleOrderItem($saleOrderId);
            $plannedByItem = $this->getPlannedQtyBySaleOrderItem($saleOrderId);

            $items = DB::table('sale_order_items')
                ->where('sale_order_id', (int) $saleOrderId)
                ->whereNull('deleted_at')
                ->get(['id', 'product_id', 'quantity']);

            $remaining = [];

            foreach ($items as $item) {
                $pid = (int) ($item->product_id ?? 0);
                $orderedQty = max(0, (int) ($item->quantity ?? 0));
                $deliveredQty = max(0, (int) ($deliveredByItem[(int) $item->id] ?? 0));
                $plannedQty = max(0, (int) ($plannedByItem[(int) $item->id] ?? 0));

                $rem = max(0, $orderedQty - $deliveredQty - $plannedQty);

                if (!isset($remaining[$pid])) $remaining[$pid] = 0;
                $remaining[$pid] += $rem;
            }

            return $remaining;
        }

        $deliveredExpr = $this->deliveredQtyExpr('sdi');

        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->whereNull('deleted_at')
            ->groupBy('product_id')
            ->get();

        $delivered = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select('sdi.product_id', DB::raw("SUM({$deliveredExpr}) as qty"))
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $plannedOutstanding = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->whereIn(DB::raw('LOWER(sd.status)'), ['pending', 'partial'])
            ->select(
                'sdi.product_id',
                DB::raw("SUM(GREATEST(COALESCE(sdi.quantity,0) - CASE
                    WHEN LOWER(COALESCE(sd.status,'')) = 'partial' THEN {$deliveredExpr}
                    ELSE 0
                END, 0)) as qty")
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $deliveredQty = isset($delivered[$pid]) ? (int) $delivered[$pid]->qty : 0;
            $plannedQty = isset($plannedOutstanding[$pid]) ? (int) $plannedOutstanding[$pid]->qty : 0;

            if ($deliveredQty < 0) $deliveredQty = 0;
            if ($plannedQty < 0) $plannedQty = 0;

            if ($deliveredQty > $orderedQty) $deliveredQty = $orderedQty;

            $maxPlannable = $orderedQty - $deliveredQty;
            if ($plannedQty > $maxPlannable) $plannedQty = $maxPlannable;

            $rem = $orderedQty - $deliveredQty - $plannedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }
}
