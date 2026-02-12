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
                'items.*.warehouse_id' => 'required|integer|min:1',

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
                        'warehouse_id'         => (int) ($row['warehouse_id'] ?? 0),

                        // these are hidden values set by JS
                        'good'                 => (int) ($row['good'] ?? 0),
                        'defect'               => (int) ($row['defect'] ?? 0),
                        'damaged'              => (int) ($row['damaged'] ?? 0),

                        'good_allocations'     => $goodAlloc,
                        'selected_defect_ids'  => $selectedDefectIds,
                        'selected_damaged_ids' => $selectedDamagedIds,
                    ];
                }

                // ------------------------------------------------------------------
                // 2) Validate warehouses belong to branch
                // ------------------------------------------------------------------
                $warehouseIds = array_values(array_unique(array_map(
                    fn ($x) => (int) ($x['warehouse_id'] ?? 0),
                    $inputById
                )));
                $warehouseIds = array_values(array_filter($warehouseIds, fn ($x) => $x > 0));

                if (empty($warehouseIds)) {
                    throw new \RuntimeException('Warehouse selection is required.');
                }

                $countValidWh = Warehouse::query()
                    ->where('branch_id', (int) $branchId)
                    ->whereIn('id', $warehouseIds)
                    ->count();

                if ($countValidWh !== count($warehouseIds)) {
                    throw new \RuntimeException('Invalid warehouse selection (must belong to active branch).');
                }

                // ------------------------------------------------------------------
                // 3) Validate racks belong to EACH item warehouse (per item)
                // ------------------------------------------------------------------
                $rackToWarehouseMap = [];
                foreach ($inputById as $row) {
                    $wid = (int) ($row['warehouse_id'] ?? 0);
                    foreach (($row['good_allocations'] ?? []) as $a) {
                        $rid = (int) ($a['from_rack_id'] ?? 0);
                        if ($rid > 0 && $wid > 0) {
                            $rackToWarehouseMap[$rid] = $wid;
                        }
                    }
                }

                if (!empty($rackToWarehouseMap)) {
                    $rackIds = array_keys($rackToWarehouseMap);

                    $rows = DB::table('racks')
                        ->whereIn('id', $rackIds)
                        ->get(['id', 'warehouse_id']);

                    $actual = [];
                    foreach ($rows as $r) {
                        $actual[(int) $r->id] = (int) $r->warehouse_id;
                    }

                    foreach ($rackToWarehouseMap as $rackId => $expectedWid) {
                        $rackId = (int) $rackId;
                        $expectedWid = (int) $expectedWid;

                        $actualWid = (int) ($actual[$rackId] ?? 0);
                        if ($actualWid <= 0) {
                            throw new \RuntimeException("Invalid rack selection. Rack ID {$rackId} not found.");
                        }

                        if ($actualWid !== $expectedWid) {
                            throw new \RuntimeException("Invalid rack selection. Rack ID {$rackId} does not belong to selected warehouse (expected WH {$expectedWid}).");
                        }

                        $whBranchCheck = Warehouse::query()
                            ->where('id', (int) $actualWid)
                            ->where('branch_id', (int) $branchId)
                            ->exists();

                        if (!$whBranchCheck) {
                            throw new \RuntimeException("Invalid rack selection. Rack's warehouse does not belong to active branch.");
                        }
                    }
                }

                // ------------------------------------------------------------------
                // 4) Validate qty breakdown, allocations, and STRICT rack total stock check
                // ------------------------------------------------------------------
                $totalConfirmedAll = 0;
                $reservedReduceByProduct = [];

                foreach ($saleDelivery->items as $it) {
                    $itemId = (int) $it->id;
                    $expected = (int) ($it->quantity ?? 0);

                    if (!isset($inputById[$itemId])) {
                        throw new \RuntimeException("Missing input for item ID {$itemId}.");
                    }

                    $wid = (int) ($inputById[$itemId]['warehouse_id'] ?? 0);
                    if ($wid <= 0) {
                        throw new \RuntimeException("Warehouse is required for item ID {$itemId}.");
                    }

                    $good   = (int) ($inputById[$itemId]['good'] ?? 0);
                    $defect = (int) ($inputById[$itemId]['defect'] ?? 0);
                    $damaged= (int) ($inputById[$itemId]['damaged'] ?? 0);

                    $confirmed = $good + $defect + $damaged;

                    if ($confirmed !== $expected) {
                        throw new \RuntimeException("Qty mismatch for item ID {$itemId}. Total selected must equal {$expected}.");
                    }

                    // GOOD allocations validation
                    $alloc = $inputById[$itemId]['good_allocations'] ?? [];
                    if ($good > 0) {
                        if (empty($alloc)) {
                            throw new \RuntimeException("GOOD allocation is required when GOOD > 0 (item ID {$itemId}).");
                        }

                        $sumAlloc = 0;
                        foreach ($alloc as $a) {
                            $rid = (int) ($a['from_rack_id'] ?? 0);
                            $qty = (int) ($a['qty'] ?? 0);
                            if ($rid <= 0 || $qty <= 0) {
                                throw new \RuntimeException("Invalid GOOD allocation row (item ID {$itemId}).");
                            }
                            $sumAlloc += $qty;
                        }

                        if ($sumAlloc !== $good) {
                            throw new \RuntimeException("GOOD allocation qty mismatch (item ID {$itemId}). Sum allocation must equal GOOD={$good}.");
                        }

                        // STRICT rack stock check (TOTAL = good+defect+damaged per rack)
                        $this->assertRackTotalStockEnough(
                            (int) $branchId,
                            (int) $wid,
                            (int) $it->product_id,
                            (array) $alloc,
                            (string) $reference
                        );
                    }

                    $selDef = $inputById[$itemId]['selected_defect_ids'] ?? [];
                    $selDam = $inputById[$itemId]['selected_damaged_ids'] ?? [];

                    if ($defect > 0 && count($selDef) !== $defect) {
                        throw new \RuntimeException("Selected DEFECT IDs count must equal defect qty for item ID {$itemId}.");
                    }
                    if ($damaged > 0 && count($selDam) !== $damaged) {
                        throw new \RuntimeException("Selected DAMAGED IDs count must equal damaged qty for item ID {$itemId}.");
                    }

                    $totalConfirmedAll += $confirmed;

                    $pid = (int) $it->product_id;
                    if ($pid > 0 && $confirmed > 0) {
                        $reservedReduceByProduct[$pid] = (int) ($reservedReduceByProduct[$pid] ?? 0) + $confirmed;
                    }

                    // save confirm result per item
                    $it->update([
                        'warehouse_id' => (int) $wid,
                        'qty_good'     => $good,
                        'qty_defect'   => $defect,
                        'qty_damaged'  => $damaged,
                    ]);
                }

                if ($totalConfirmedAll <= 0) {
                    throw new \RuntimeException('Nothing to confirm.');
                }

                // ------------------------------------------------------------------
                // 5) Create mutation OUT per item warehouse
                // ------------------------------------------------------------------
                foreach ($saleDelivery->items as $it) {
                    $productId = (int) $it->product_id;

                    $warehouseId = (int) ($it->warehouse_id ?? 0);
                    if ($warehouseId <= 0) {
                        throw new \RuntimeException("Warehouse is missing on confirmed item. Item ID {$it->id}.");
                    }

                    $good   = (int) ($it->qty_good ?? 0);
                    $defect = (int) ($it->qty_defect ?? 0);
                    $damaged= (int) ($it->qty_damaged ?? 0);

                    $confirmed = $good + $defect + $damaged;
                    if ($confirmed <= 0) continue;

                    $input = $inputById[(int) $it->id] ?? [];

                    // GOOD mutation (per rack allocations)
                    if ($good > 0) {
                        $alloc = $input['good_allocations'] ?? [];
                        foreach ($alloc as $a) {
                            $rackId = (int) ($a['from_rack_id'] ?? 0);
                            $qtyA   = (int) ($a['qty'] ?? 0);
                            if ($rackId <= 0 || $qtyA <= 0) continue;

                            $noteOut = "Sales Delivery OUT #{$reference} | GOOD";
                            $this->mutationController->applyInOut(
                                (int) $saleDelivery->branch_id,
                                (int) $warehouseId,
                                $productId,
                                'Out',
                                (int) $qtyA,
                                $reference,
                                $noteOut,
                                (string) $saleDelivery->getRawOriginal('date'),
                                (int) $rackId
                            );
                        }
                    }

                    // DEFECT mutation (IDs)
                    $selectedDefectIds = $input['selected_defect_ids'] ?? [];
                    if ($defect > 0) {
                        $ids = $selectedDefectIds;

                        $countValid = DB::table('product_defect_items')
                            ->whereIn('id', $ids)
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $warehouseId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->count();

                        if ($countValid !== count($ids)) {
                            throw new \RuntimeException("Some selected DEFECT IDs are invalid/unavailable for product_id {$productId} (WH {$warehouseId}).");
                        }

                        $rows = ProductDefectItem::query()
                            ->whereIn('id', $ids)
                            ->lockForUpdate()
                            ->get(['id', 'rack_id']);

                        if ($rows->contains(fn ($r) => empty($r->rack_id))) {
                            throw new \RuntimeException("Some DEFECT items have no rack_id. Please assign rack first before confirming.");
                        }

                        foreach ($rows->groupBy('rack_id') as $rackId => $group) {
                            $noteOut = "Sales Delivery OUT #{$reference} | DEFECT";
                            $this->mutationController->applyInOut(
                                (int) $saleDelivery->branch_id,
                                (int) $warehouseId,
                                $productId,
                                'Out',
                                (int) $group->count(),
                                $reference,
                                $noteOut,
                                (string) $saleDelivery->getRawOriginal('date'),
                                (int) $rackId
                            );
                        }

                        DB::table('product_defect_items')
                            ->whereIn('id', $ids)
                            ->update([
                                'moved_out_at'             => now(),
                                'moved_out_by'             => auth()->id(),
                                'moved_out_reference_type' => SaleDelivery::class,
                                'moved_out_reference_id'   => (int) $saleDelivery->id,
                            ]);
                    }

                    // DAMAGED mutation (IDs)
                    $selectedDamagedIds = $input['selected_damaged_ids'] ?? [];
                    if ($damaged > 0) {
                        $ids = $selectedDamagedIds;

                        $countValid = DB::table('product_damaged_items')
                            ->whereIn('id', $ids)
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $warehouseId)
                            ->where('product_id', $productId)
                            ->where('damage_type', 'damaged')
                            ->where('resolution_status', 'pending')
                            ->whereNull('moved_out_at')
                            ->count();

                        if ($countValid !== count($ids)) {
                            throw new \RuntimeException("Some selected DAMAGED IDs are invalid/unavailable for product_id {$productId} (WH {$warehouseId}).");
                        }

                        $rows = ProductDamagedItem::query()
                            ->whereIn('id', $ids)
                            ->lockForUpdate()
                            ->get(['id', 'rack_id']);

                        if ($rows->contains(fn ($r) => empty($r->rack_id))) {
                            throw new \RuntimeException("Some DAMAGED items have no rack_id. Please assign rack first before confirming.");
                        }

                        foreach ($rows->groupBy('rack_id') as $rackId => $group) {
                            $noteOut = "Sales Delivery OUT #{$reference} | DAMAGED";

                            $outId = $this->mutationController->applyInOutAndGetMutationId(
                                (int) $saleDelivery->branch_id,
                                (int) $warehouseId,
                                $productId,
                                'Out',
                                (int) $group->count(),
                                $reference,
                                $noteOut,
                                (string) $saleDelivery->getRawOriginal('date'),
                                (int) $rackId
                            );

                            DB::table('product_damaged_items')
                                ->whereIn('id', $group->pluck('id')->all())
                                ->update([
                                    'moved_out_at'             => now(),
                                    'moved_out_by'             => auth()->id(),
                                    'moved_out_reference_type' => SaleDelivery::class,
                                    'moved_out_reference_id'   => (int) $saleDelivery->id,
                                    'mutation_out_id'          => (int) $outId,
                                ]);
                        }
                    }
                }

                // ------------------------------------------------------------------
                // 6) reserved pool decrement
                // ------------------------------------------------------------------
                $this->decrementReservedPoolStock(
                    (int) $branchId,
                    $reservedReduceByProduct,
                    (string) $reference
                );

                $confirmNote = $request->confirm_note ? (string) $request->confirm_note : null;

                // ------------------------------------------------------------------
                // 7) Header update (warehouse per item)
                // ------------------------------------------------------------------
                $saleDelivery->update([
                    'warehouse_id' => null,

                    'status' => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'confirm_note' => $confirmNote,
                    'confirm_note_updated_by' => $confirmNote ? auth()->id() : null,
                    'confirm_note_updated_role' => $confirmNote ? $this->roleString() : null,
                    'confirm_note_updated_at' => $confirmNote ? now() : null,
                    'updated_by' => auth()->id(),
                ]);

                // update SO fulfillment
                $saleOrderId = (int) ($saleDelivery->sale_order_id ?? 0);
                if ($saleOrderId <= 0 && !empty($saleDelivery->sale_id)) {
                    $found = SaleOrder::query()
                        ->where('branch_id', (int) $saleDelivery->branch_id)
                        ->where('sale_id', (int) $saleDelivery->sale_id)
                        ->orderByDesc('id')
                        ->first();
                    if ($found) $saleOrderId = (int) $found->id;
                }

                if ($saleOrderId > 0) {
                    $this->updateSaleOrderFulfillmentStatus((int) $saleOrderId);
                }
            });

            toast('Sale Delivery confirmed successfully', 'success');
            return redirect()->route('sale-deliveries.show', $saleDelivery->id);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }
}
