<?php

namespace Modules\SaleDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\Product\Entities\Warehouse;
use Modules\Mutation\Entities\Mutation;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\SaleOrder\Entities\SaleOrder;

use Modules\SaleDelivery\Http\Controllers\Concerns\SaleDeliveryShared;

class SaleDeliveryConfirmController extends Controller
{
    use SaleDeliveryShared;

    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    /**
     * Decrement qty_reserved pada pool stock (warehouse_id NULL) berdasarkan qty yang dikirim.
     * STRICT: kalau reserved kurang => throw biar ketahuan mismatch data.
     */
    private function decrementReservedPoolStock(int $branchId, array $reservedReduceByProduct, string $reference): void
    {
        if ($branchId <= 0) return;

        foreach ($reservedReduceByProduct as $productId => $qtyReduce) {
            $productId = (int) $productId;
            $qtyReduce = (int) $qtyReduce;

            if ($productId <= 0 || $qtyReduce <= 0) continue;

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

                    'qty_available'  => 0,
                    'qty_reserved'   => 0,
                    'qty_incoming'   => 0,
                    'min_stock'      => 0,

                    'note'           => null,
                    'created_by'     => auth()->id(),
                    'updated_by'     => auth()->id(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $row = (object) ['qty_reserved' => 0];
            }

            $current = (int) ($row->qty_reserved ?? 0);

            if ($current < $qtyReduce) {
                throw new \RuntimeException(
                    "Reserved stock is not enough for product_id {$productId}. " .
                    "Need {$qtyReduce}, reserved {$current}. Ref: {$reference}."
                );
            }

            DB::table('stocks')
                ->where('branch_id', (int) $branchId)
                ->whereNull('warehouse_id')
                ->where('product_id', (int) $productId)
                ->update([
                    'qty_reserved' => $current - $qtyReduce,
                    'updated_by'   => auth()->id(),
                    'updated_at'   => now(),
                ]);
        }
    }

    /**
     * ✅ STRICT: pastikan TOTAL stock di rack cukup untuk diambil.
     * (total = qty_good + qty_defect + qty_damaged)
     * Wajib jalan di dalam DB::transaction (karena pakai lockForUpdate).
     */
    private function assertRackTotalStockEnough(
        int $branchId,
        int $warehouseId,
        int $productId,
        array $allocations,
        string $reference
    ): void {
        foreach ($allocations as $a) {
            $rackId = (int) ($a['from_rack_id'] ?? 0);
            $qty    = (int) ($a['qty'] ?? 0);

            if ($rackId <= 0 || $qty <= 0) continue;

            // lock row stock per rack
            $row = DB::table('stock_racks')
                ->where('branch_id', (int) $branchId)
                ->where('warehouse_id', (int) $warehouseId)
                ->where('product_id', (int) $productId)
                ->where('rack_id', (int) $rackId)
                ->lockForUpdate()
                ->first();

            $good   = (int) ($row->qty_good ?? 0);
            $defect = (int) ($row->qty_defect ?? 0);
            $damaged= (int) ($row->qty_damaged ?? 0);

            $total = $good + $defect + $damaged;

            if ($total < $qty) {
                throw new \RuntimeException(
                    "Not enough stock on selected rack. " .
                    "Product ID {$productId}, WH {$warehouseId}, Rack {$rackId}. " .
                    "Need {$qty}, available TOTAL {$total} (G{$good}/D{$defect}/DM{$damaged}). Ref: {$reference}"
                );
            }
        }
    }


    private function roleString(): string
    {
        $user = auth()->user();
        if (!$user) return 'unknown';

        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            if (!empty($roles) && count($roles) > 0) return (string) $roles[0];
        }

        return 'user';
    }

    public function confirmForm(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();

            if (strtolower((string) $saleDelivery->status) !== 'pending') {
                throw new \RuntimeException('Sale Delivery is not pending.');
            }

            $branchId = (int) BranchContext::id();
            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $saleDelivery->load(['items.product', 'customer']);

            $warehouses = Warehouse::query()
                ->where('branch_id', (int) $branchId)
                ->orderBy('warehouse_name')
                ->get();

            $productIds = [];
            foreach ($saleDelivery->items as $it) {
                $pid = (int) $it->product_id;
                if ($pid > 0) $productIds[] = $pid;
            }
            $productIds = array_values(array_unique($productIds));

            $defectData = [];
            $damagedData = [];

            if (!empty($productIds)) {

                $defRows = DB::table('product_defect_items')
                    ->where('branch_id', (int) $branchId)
                    ->whereIn('product_id', $productIds)
                    ->whereNull('moved_out_at')
                    ->orderBy('id')
                    ->get();

                foreach ($defRows as $r) {
                    $pid = (int) $r->product_id;
                    $wid = (int) $r->warehouse_id;

                    $defectData[$pid][$wid][] = [
                        'id' => (int) $r->id,
                        'defect_type' => (string) ($r->defect_type ?? ''),
                        'description' => (string) ($r->description ?? ''),
                        'rack_id' => (int) ($r->rack_id ?? 0),
                    ];
                }

                $damRows = DB::table('product_damaged_items')
                    ->where('branch_id', (int) $branchId)
                    ->whereIn('product_id', $productIds)
                    ->where('damage_type', 'damaged')
                    ->where('resolution_status', 'pending')
                    ->whereNull('moved_out_at')
                    ->orderBy('id')
                    ->get();

                foreach ($damRows as $r) {
                    $pid = (int) $r->product_id;
                    $wid = (int) $r->warehouse_id;

                    $damagedData[$pid][$wid][] = [
                        'id' => (int) $r->id,
                        'reason' => (string) ($r->reason ?? ''),
                        'rack_id' => (int) ($r->rack_id ?? 0),
                    ];
                }
            }

            $warehouseIds = $warehouses->pluck('id')->map(fn($x) => (int) $x)->all();

            // ✅ racks: JSON friendly
            $racksByWarehouse = DB::table('racks')
                ->whereIn('warehouse_id', $warehouseIds)
                ->orderBy('warehouse_id')
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'warehouse_id', 'code', 'name'])
                ->groupBy('warehouse_id')
                ->map(function ($rows) {
                    return $rows->map(function ($r) {
                        return [
                            'id' => (int) $r->id,
                            'code' => (string) ($r->code ?? ''),
                            'name' => (string) ($r->name ?? ''),
                        ];
                    })->values()->all();
                })
                ->all();

            /**
             * ✅ NEW: Stock per rack (TOTAL + breakdown) untuk UI GOOD rows.
             * Map: stockRackData[product_id][warehouse_id][rack_id] = [total, good, defect, damaged]
             */
            $stockRackData = [];
            if (!empty($productIds) && !empty($warehouseIds)) {
                $srRows = DB::table('stock_racks')
                    ->where('branch_id', (int) $branchId)
                    ->whereIn('product_id', $productIds)
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->get(['product_id', 'warehouse_id', 'rack_id', 'qty_good', 'qty_defect', 'qty_damaged']);

                foreach ($srRows as $r) {
                    $pid = (int) $r->product_id;
                    $wid = (int) $r->warehouse_id;
                    $rid = (int) $r->rack_id;

                    $good = (int) ($r->qty_good ?? 0);
                    $def  = (int) ($r->qty_defect ?? 0);
                    $dam  = (int) ($r->qty_damaged ?? 0);
                    $total = $good + $def + $dam;

                    if (!isset($stockRackData[$pid])) $stockRackData[$pid] = [];
                    if (!isset($stockRackData[$pid][$wid])) $stockRackData[$pid][$wid] = [];

                    $stockRackData[$pid][$wid][$rid] = [
                        'total' => $total,
                        'good' => $good,
                        'defect' => $def,
                        'damaged' => $dam,
                    ];
                }
            }

            return view('saledelivery::confirm', compact(
                'saleDelivery',
                'warehouses',
                'defectData',
                'damagedData',
                'racksByWarehouse',
                'stockRackData'
            ));

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function confirmStore(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();

            $branchId = (int) BranchContext::id();

            $request->validate([
                'confirm_note' => 'nullable|string|max:5000',

                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer',

                // hidden (auto from JS)
                'items.*.good' => 'required|integer|min:0',
                'items.*.defect' => 'required|integer|min:0',
                'items.*.damaged' => 'required|integer|min:0',

                'items.*.good_allocations' => 'nullable|array',
                'items.*.good_allocations.*.from_rack_id' => 'required|integer|min:1',
                'items.*.good_allocations.*.qty' => 'required|integer|min:1',

                'items.*.selected_defect_ids' => 'nullable|array',
                'items.*.selected_defect_ids.*' => 'integer',

                'items.*.selected_damaged_ids' => 'nullable|array',
                'items.*.selected_damaged_ids.*' => 'integer',
            ]);

            DB::transaction(function () use ($request, $saleDelivery, $branchId) {

                $saleDelivery = SaleDelivery::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->findOrFail($saleDelivery->id);

                $status = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
                if (!in_array($status, ['pending'], true)) {
                    throw new \RuntimeException('Sale Delivery is not pending.');
                }

                if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                    throw new \RuntimeException('Wrong branch context.');
                }

                if (empty($saleDelivery->reference)) {
                    $saleDelivery->update([
                        'reference' => make_reference_id('SDO', (int) $saleDelivery->id),
                    ]);
                }

                $reference = (string) $saleDelivery->reference;

                $exists = Mutation::withoutGlobalScopes()
                    ->where('reference', $reference)
                    ->where('note', 'like', 'Sales Delivery OUT%')
                    ->exists();

                if ($exists) {
                    throw new \RuntimeException('This sale delivery was already confirmed (stock movement exists).');
                }

                $mutationDate = (string) ($saleDelivery->getRawOriginal('date') ?? $saleDelivery->date ?? now()->toDateString());

                // ------------------------------------------------------------------
                // 1) Normalize input by item_id
                // ------------------------------------------------------------------
                $inputById = [];

                foreach ($request->items as $row) {
                    $itemId = (int) ($row['id'] ?? 0);
                    if ($itemId <= 0) continue;

                    $selectedDefectIds = [];
                    if (isset($row['selected_defect_ids']) && is_array($row['selected_defect_ids'])) {
                        $selectedDefectIds = array_values(array_unique(array_map('intval', $row['selected_defect_ids'])));
                    }

                    $selectedDamagedIds = [];
                    if (isset($row['selected_damaged_ids']) && is_array($row['selected_damaged_ids'])) {
                        $selectedDamagedIds = array_values(array_unique(array_map('intval', $row['selected_damaged_ids'])));
                    }

                    $goodAlloc = [];
                    if (isset($row['good_allocations']) && is_array($row['good_allocations'])) {
                        foreach ($row['good_allocations'] as $a) {
                            if (!is_array($a)) continue;
                            $goodAlloc[] = [
                                'from_rack_id' => (int) ($a['from_rack_id'] ?? 0),
                                'qty'          => (int) ($a['qty'] ?? 0),
                            ];
                        }
                    }

                    $inputById[$itemId] = [
                        'good'                 => (int) ($row['good'] ?? 0),
                        'defect'               => (int) ($row['defect'] ?? 0),
                        'damaged'              => (int) ($row['damaged'] ?? 0),
                        'good_allocations'     => $goodAlloc,
                        'selected_defect_ids'  => $selectedDefectIds,
                        'selected_damaged_ids' => $selectedDamagedIds,
                    ];
                }

                // Map item -> product_id (for validation)
                $itemProductMap = [];
                foreach ($saleDelivery->items as $it) {
                    $itemProductMap[(int) $it->id] = (int) $it->product_id;
                }

                // ------------------------------------------------------------------
                // 2) Derive rack -> warehouse mapping from GOOD allocations (rack is the source of truth)
                //    + validate racks exist
                // ------------------------------------------------------------------
                $allRackIds = [];
                foreach ($inputById as $itemId => $row) {
                    foreach (($row['good_allocations'] ?? []) as $a) {
                        $rid = (int) ($a['from_rack_id'] ?? 0);
                        if ($rid > 0) $allRackIds[] = $rid;
                    }
                }
                $allRackIds = array_values(array_unique($allRackIds));

                $rackInfo = []; // rack_id => ['warehouse_id'=>X]
                if (!empty($allRackIds)) {
                    $rackRows = DB::table('racks')
                        ->whereIn('id', $allRackIds)
                        ->get(['id', 'warehouse_id']);

                    foreach ($rackRows as $r) {
                        $rackInfo[(int) $r->id] = [
                            'warehouse_id' => (int) $r->warehouse_id,
                        ];
                    }

                    // ensure all racks exist
                    foreach ($allRackIds as $rid) {
                        if (!isset($rackInfo[(int) $rid])) {
                            throw new \RuntimeException("Invalid rack selection. Rack ID {$rid} not found.");
                        }
                    }
                }

                // ------------------------------------------------------------------
                // 3) Validate: every derived warehouse (from racks / defect / damaged) must belong to active branch
                //    We'll collect all used warehouse IDs from:
                //    - GOOD allocations (via racks.warehouse_id)
                //    - DEFECT/DAMAGED selected rows (via their warehouse_id)
                // ------------------------------------------------------------------
                $usedWarehouseIds = [];

                // from racks
                foreach ($rackInfo as $info) {
                    $wid = (int) ($info['warehouse_id'] ?? 0);
                    if ($wid > 0) $usedWarehouseIds[] = $wid;
                }

                // from defect / damaged rows (pre-validate by querying later, but we can also collect after fetch)
                // We'll validate existence + branch ownership during per-item validation below and add to usedWarehouseIds there.

                // Validate current usedWarehouseIds (from racks) belong to branch
                $usedWarehouseIds = array_values(array_unique($usedWarehouseIds));
                if (!empty($usedWarehouseIds)) {
                    $countValidWh = Warehouse::query()
                        ->where('branch_id', (int) $branchId)
                        ->whereIn('id', $usedWarehouseIds)
                        ->count();

                    if ($countValidWh !== count($usedWarehouseIds)) {
                        throw new \RuntimeException('Invalid warehouse derived from rack selection (must belong to active branch).');
                    }
                }

                // ------------------------------------------------------------------
                // 4) Validate qty breakdown, allocations, DEFECT/DAMAGED rows, and STRICT rack total stock check
                // ------------------------------------------------------------------
                $reservedReduceByProduct = [];

                foreach ($saleDelivery->items as $it) {
                    $itemId   = (int) $it->id;
                    $productId= (int) $it->product_id;
                    $expected = (int) ($it->quantity ?? 0);

                    if (!isset($inputById[$itemId])) {
                        throw new \RuntimeException("Missing input for item ID {$itemId}.");
                    }

                    $good   = (int) ($inputById[$itemId]['good'] ?? 0);
                    $defect = (int) ($inputById[$itemId]['defect'] ?? 0);
                    $damaged= (int) ($inputById[$itemId]['damaged'] ?? 0);

                    $confirmed = $good + $defect + $damaged;

                    if ($confirmed !== $expected) {
                        throw new \RuntimeException("Qty mismatch for item ID {$itemId}. Total selected must equal {$expected}.");
                    }

                    // ---------------------------
                    // GOOD allocations validation
                    // ---------------------------
                    $alloc = $inputById[$itemId]['good_allocations'] ?? [];
                    if ($good > 0) {
                        if (empty($alloc)) {
                            throw new \RuntimeException("GOOD allocation is required when GOOD > 0 (item ID {$itemId}).");
                        }

                        $sumAlloc = 0;

                        // group allocations by warehouse derived from rack
                        $allocByWh = [];

                        foreach ($alloc as $a) {
                            $rid = (int) ($a['from_rack_id'] ?? 0);
                            $qty = (int) ($a['qty'] ?? 0);
                            if ($rid <= 0 || $qty <= 0) {
                                throw new \RuntimeException("Invalid GOOD allocation row (item ID {$itemId}).");
                            }

                            if (!isset($rackInfo[$rid])) {
                                throw new \RuntimeException("Invalid rack selection. Rack ID {$rid} not found.");
                            }

                            $wid = (int) ($rackInfo[$rid]['warehouse_id'] ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("Invalid rack selection. Rack ID {$rid} has no warehouse.");
                            }

                            $sumAlloc += $qty;

                            if (!isset($allocByWh[$wid])) $allocByWh[$wid] = [];
                            $allocByWh[$wid][] = [
                                'from_rack_id' => $rid,
                                'qty'          => $qty,
                            ];
                        }

                        if ($sumAlloc !== $good) {
                            throw new \RuntimeException("GOOD allocation total must equal GOOD qty (item ID {$itemId}).");
                        }

                        // validate warehouses (derived from racks) belong to branch (per item)
                        $wids = array_keys($allocByWh);
                        $wids = array_values(array_unique(array_map('intval', $wids)));

                        if (!empty($wids)) {
                            $countValid = Warehouse::query()
                                ->where('branch_id', (int) $branchId)
                                ->whereIn('id', $wids)
                                ->count();

                            if ($countValid !== count($wids)) {
                                throw new \RuntimeException("Invalid warehouse derived from GOOD allocation (item ID {$itemId}).");
                            }
                        }

                        // ✅ STRICT rack stock total check per warehouse group
                        foreach ($allocByWh as $wid => $allocRows) {
                            $this->assertRackTotalStockEnough(
                                (int) $branchId,
                                (int) $wid,
                                (int) $productId,
                                $allocRows,
                                (string) $saleDelivery->reference
                            );
                        }
                    }

                    // ---------------------------
                    // DEFECT validation (1 id = 1 pc)
                    // warehouse is derived from the selected rows (NOT from saleDelivery item)
                    // ---------------------------
                    $defIds = $inputById[$itemId]['selected_defect_ids'] ?? [];
                    if ($defect > 0) {
                        if (count($defIds) !== $defect) {
                            throw new \RuntimeException("DEFECT selection count must equal DEFECT qty (item ID {$itemId}).");
                        }

                        $rows = ProductDefectItem::query()
                            ->where('branch_id', (int) $branchId)
                            ->where('product_id', (int) $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $defIds)
                            ->get(['id', 'warehouse_id']);

                        if ($rows->count() !== count($defIds)) {
                            throw new \RuntimeException("Some DEFECT IDs are invalid / already moved out (item ID {$itemId}).");
                        }

                        foreach ($rows as $r) {
                            $wid = (int) ($r->warehouse_id ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("DEFECT item warehouse_id is missing (ID {$r->id}).");
                            }

                            $ok = Warehouse::query()
                                ->where('id', (int) $wid)
                                ->where('branch_id', (int) $branchId)
                                ->exists();

                            if (!$ok) {
                                throw new \RuntimeException("Invalid warehouse for DEFECT item (ID {$r->id}).");
                            }

                            $usedWarehouseIds[] = $wid;
                        }
                    }

                    // ---------------------------
                    // DAMAGED validation (1 id = 1 pc)
                    // warehouse is derived from the selected rows (NOT from saleDelivery item)
                    // ---------------------------
                    $damIds = $inputById[$itemId]['selected_damaged_ids'] ?? [];
                    if ($damaged > 0) {
                        if (count($damIds) !== $damaged) {
                            throw new \RuntimeException("DAMAGED selection count must equal DAMAGED qty (item ID {$itemId}).");
                        }

                        $rows = ProductDamagedItem::query()
                            ->where('branch_id', (int) $branchId)
                            ->where('product_id', (int) $productId)
                            ->where('damage_type', 'damaged')
                            ->where('resolution_status', 'pending')
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $damIds)
                            ->get(['id', 'warehouse_id']);

                        if ($rows->count() !== count($damIds)) {
                            throw new \RuntimeException("Some DAMAGED IDs are invalid / already moved out (item ID {$itemId}).");
                        }

                        foreach ($rows as $r) {
                            $wid = (int) ($r->warehouse_id ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("DAMAGED item warehouse_id is missing (ID {$r->id}).");
                            }

                            $ok = Warehouse::query()
                                ->where('id', (int) $wid)
                                ->where('branch_id', (int) $branchId)
                                ->exists();

                            if (!$ok) {
                                throw new \RuntimeException("Invalid warehouse for DAMAGED item (ID {$r->id}).");
                            }

                            $usedWarehouseIds[] = $wid;
                        }
                    }

                    // reserved pool reduce by product (strict)
                    $pid = (int) $it->product_id;
                    if (!isset($reservedReduceByProduct[$pid])) $reservedReduceByProduct[$pid] = 0;
                    $reservedReduceByProduct[$pid] += $confirmed;
                }

                // final unique warehouses derived (optional, for sanity)
                $usedWarehouseIds = array_values(array_unique(array_map('intval', $usedWarehouseIds)));
                if (!empty($usedWarehouseIds)) {
                    $countValidWh2 = Warehouse::query()
                        ->where('branch_id', (int) $branchId)
                        ->whereIn('id', $usedWarehouseIds)
                        ->count();

                    if ($countValidWh2 !== count($usedWarehouseIds)) {
                        throw new \RuntimeException('Invalid warehouse derived from selection (must belong to active branch).');
                    }
                }

                // ------------------------------------------------------------------
                // 5) Decrement reserved pool stock (warehouse NULL)
                // ------------------------------------------------------------------
                $this->decrementReservedPoolStock((int) $branchId, $reservedReduceByProduct, $reference);

                // ------------------------------------------------------------------
                // 6) Create Mutations (OUT) based on derived warehouse per selection
                // ------------------------------------------------------------------
                foreach ($saleDelivery->items as $it) {
                    $itemId   = (int) $it->id;
                    $productId= (int) $it->product_id;

                    $good   = (int) ($inputById[$itemId]['good'] ?? 0);
                    $defect = (int) ($inputById[$itemId]['defect'] ?? 0);
                    $damaged= (int) ($inputById[$itemId]['damaged'] ?? 0);

                    // GOOD: OUT based on allocations; warehouse derived from rack
                    $alloc = $inputById[$itemId]['good_allocations'] ?? [];
                    if ($good > 0 && !empty($alloc)) {

                        // group by warehouse
                        $allocByWh = [];
                        foreach ($alloc as $a) {
                            $fromRackId = (int) ($a['from_rack_id'] ?? 0);
                            $qty = (int) ($a['qty'] ?? 0);
                            if ($fromRackId <= 0 || $qty <= 0) continue;

                            if (!isset($rackInfo[$fromRackId])) {
                                throw new \RuntimeException("Invalid rack selection. Rack ID {$fromRackId} not found.");
                            }

                            $wid = (int) ($rackInfo[$fromRackId]['warehouse_id'] ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("Invalid rack selection. Rack ID {$fromRackId} has no warehouse.");
                            }

                            if (!isset($allocByWh[$wid])) $allocByWh[$wid] = [];
                            $allocByWh[$wid][] = ['from_rack_id' => $fromRackId, 'qty' => $qty];
                        }

                        foreach ($allocByWh as $wid => $rows) {
                            foreach ($rows as $a) {
                                $fromRackId = (int) $a['from_rack_id'];
                                $qty = (int) $a['qty'];

                                $this->mutationController->applyInOut(
                                    (int) $branchId,
                                    (int) $wid,
                                    (int) $productId,
                                    'Out',
                                    (int) $qty,
                                    (string) $reference,
                                    'Sales Delivery OUT (GOOD)',
                                    (string) $mutationDate,
                                    (int) $fromRackId,
                                    'good',
                                    'summary'
                                );
                            }
                        }
                    }

                    // DEFECT: 1 id = 1 pcs, derive warehouse & rack from row
                    $defIds = $inputById[$itemId]['selected_defect_ids'] ?? [];
                    if ($defect > 0 && !empty($defIds)) {
                        foreach ($defIds as $id) {
                            $id = (int) $id;
                            if ($id <= 0) continue;

                            $row = ProductDefectItem::query()
                                ->where('id', $id)
                                ->where('branch_id', (int) $branchId)
                                ->where('product_id', (int) $productId)
                                ->whereNull('moved_out_at')
                                ->lockForUpdate()
                                ->first();

                            if (!$row) {
                                throw new \RuntimeException("DEFECT item not found/invalid: ID {$id}.");
                            }

                            $wid = (int) ($row->warehouse_id ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("DEFECT item warehouse_id is invalid: ID {$id}.");
                            }

                            $rackId = (int) ($row->rack_id ?? 0);
                            if ($rackId <= 0) {
                                throw new \RuntimeException("DEFECT item rack_id is invalid: ID {$id}.");
                            }

                            $okWh = Warehouse::query()
                                ->where('id', (int) $wid)
                                ->where('branch_id', (int) $branchId)
                                ->exists();
                            if (!$okWh) {
                                throw new \RuntimeException("DEFECT item's warehouse does not belong to active branch: ID {$id}.");
                            }

                            $row->update([
                                'moved_out_at'  => now(),
                                'moved_out_ref' => (string) $reference,
                            ]);

                            $this->mutationController->applyInOut(
                                (int) $branchId,
                                (int) $wid,
                                (int) $productId,
                                'Out',
                                1,
                                (string) $reference,
                                'Sales Delivery OUT (DEFECT)',
                                (string) $mutationDate,
                                (int) $rackId,
                                'defect',
                                'summary'
                            );
                        }
                    }

                    // DAMAGED: 1 id = 1 pcs, derive warehouse & rack from row
                    $damIds = $inputById[$itemId]['selected_damaged_ids'] ?? [];
                    if ($damaged > 0 && !empty($damIds)) {
                        foreach ($damIds as $id) {
                            $id = (int) $id;
                            if ($id <= 0) continue;

                            $row = ProductDamagedItem::query()
                                ->where('id', $id)
                                ->where('branch_id', (int) $branchId)
                                ->where('product_id', (int) $productId)
                                ->where('damage_type', 'damaged')
                                ->where('resolution_status', 'pending')
                                ->whereNull('moved_out_at')
                                ->lockForUpdate()
                                ->first();

                            if (!$row) {
                                throw new \RuntimeException("DAMAGED item not found/invalid: ID {$id}.");
                            }

                            $wid = (int) ($row->warehouse_id ?? 0);
                            if ($wid <= 0) {
                                throw new \RuntimeException("DAMAGED item warehouse_id is invalid: ID {$id}.");
                            }

                            $rackId = (int) ($row->rack_id ?? 0);
                            if ($rackId <= 0) {
                                throw new \RuntimeException("DAMAGED item rack_id is invalid: ID {$id}.");
                            }

                            $okWh = Warehouse::query()
                                ->where('id', (int) $wid)
                                ->where('branch_id', (int) $branchId)
                                ->exists();
                            if (!$okWh) {
                                throw new \RuntimeException("DAMAGED item's warehouse does not belong to active branch: ID {$id}.");
                            }

                            $row->update([
                                'moved_out_at'  => now(),
                                'moved_out_ref' => (string) $reference,
                            ]);

                            $this->mutationController->applyInOut(
                                (int) $branchId,
                                (int) $wid,
                                (int) $productId,
                                'Out',
                                1,
                                (string) $reference,
                                'Sales Delivery OUT (DAMAGED)',
                                (string) $mutationDate,
                                (int) $rackId,
                                'damaged',
                                'summary'
                            );
                        }
                    }
                }

                // ------------------------------------------------------------------
                // 7) Update Sale Delivery status & note
                // ------------------------------------------------------------------
                $saleDelivery->update([
                    'status' => 'completed',
                    'confirm_note' => (string) ($request->input('confirm_note') ?? ''),
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });

            session()->flash('success', 'Sale Delivery confirmed successfully.');
            return redirect()->route('sale-deliveries.show', $saleDelivery->id);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }
}
