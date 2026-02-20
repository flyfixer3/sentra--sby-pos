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
                        // warehouse_id is fixed per Sale Delivery item (not from modal filter)
                        'warehouse_id'         => 0,

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
                // 2) Validate warehouses belong to branch (warehouse is FIXED per SaleDelivery item)
                // ------------------------------------------------------------------
                $warehouseIds = [];
                foreach ($saleDelivery->items as $it) {
                    $wid = (int) ($it->warehouse_id ?? 0);
                    if ($wid > 0) $warehouseIds[] = $wid;
                }
                $warehouseIds = array_values(array_unique($warehouseIds));

                if (empty($warehouseIds)) {
                    throw new \RuntimeException('Warehouse is not set on Sale Delivery items. Please set warehouse per item first.');
                }

                $countValidWh = Warehouse::query()
                    ->where('branch_id', (int) $branchId)
                    ->whereIn('id', $warehouseIds)
                    ->count();

                if ($countValidWh !== count($warehouseIds)) {
                    throw new \RuntimeException('Invalid warehouse on Sale Delivery items (must belong to active branch).');
                }

                // ------------------------------------------------------------------
                // 3) Validate racks belong to EACH item warehouse (per item)
                // ------------------------------------------------------------------
                $itemWarehouseMap = [];
                foreach ($saleDelivery->items as $it) {
                    $itemWarehouseMap[(int) $it->id] = (int) ($it->warehouse_id ?? 0);
                }

                $rackToWarehouseMap = [];
                foreach ($inputById as $itemId => $row) {
                    $wid = (int) ($itemWarehouseMap[(int) $itemId] ?? 0);
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

                    // ✅ warehouse fixed per item, not from modal dropdown
                    $wid = (int) ($it->warehouse_id ?? 0);
                    if ($wid <= 0) {
                        throw new \RuntimeException("Warehouse is not set for this Sale Delivery item (item ID {$itemId}). Please set warehouse first.");
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
                            throw new \RuntimeException("GOOD allocation total must equal GOOD qty (item ID {$itemId}).");
                        }

                        // ✅ STRICT rack stock total check
                        $this->assertRackTotalStockEnough(
                            (int) $branchId,
                            (int) $wid,
                            (int) $it->product_id,
                            $alloc,
                            $reference
                        );
                    }

                    // DEFECT validation (each ID = 1 pc)
                    $defIds = $inputById[$itemId]['selected_defect_ids'] ?? [];
                    if ($defect > 0) {
                        if (count($defIds) !== $defect) {
                            throw new \RuntimeException("DEFECT selection count must equal DEFECT qty (item ID {$itemId}).");
                        }

                        $countDef = ProductDefectItem::query()
                            ->where('branch_id', (int) $branchId)
                            ->where('warehouse_id', (int) $wid)
                            ->where('product_id', (int) $it->product_id)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $defIds)
                            ->count();

                        if ($countDef !== count($defIds)) {
                            throw new \RuntimeException("Some DEFECT IDs are invalid / already moved out (item ID {$itemId}).");
                        }
                    }

                    // DAMAGED validation (each ID = 1 pc)
                    $damIds = $inputById[$itemId]['selected_damaged_ids'] ?? [];
                    if ($damaged > 0) {
                        if (count($damIds) !== $damaged) {
                            throw new \RuntimeException("DAMAGED selection count must equal DAMAGED qty (item ID {$itemId}).");
                        }

                        $countDam = ProductDamagedItem::query()
                            ->where('branch_id', (int) $branchId)
                            ->where('warehouse_id', (int) $wid)
                            ->where('product_id', (int) $it->product_id)
                            ->where('damage_type', 'damaged')
                            ->where('resolution_status', 'pending')
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $damIds)
                            ->count();

                        if ($countDam !== count($damIds)) {
                            throw new \RuntimeException("Some DAMAGED IDs are invalid / already moved out (item ID {$itemId}).");
                        }
                    }

                    $totalConfirmedAll += $confirmed;

                    // reserved pool reduce by product (strict)
                    $pid = (int) $it->product_id;
                    if (!isset($reservedReduceByProduct[$pid])) $reservedReduceByProduct[$pid] = 0;
                    $reservedReduceByProduct[$pid] += $confirmed;
                }

                // ------------------------------------------------------------------
                // 5) Decrement reserved pool stock (warehouse NULL)
                // ------------------------------------------------------------------
                $this->decrementReservedPoolStock((int) $branchId, $reservedReduceByProduct, $reference);

                // ------------------------------------------------------------------
                // 6) Create Mutations (OUT)
                // ------------------------------------------------------------------
                foreach ($saleDelivery->items as $it) {
                    $itemId = (int) $it->id;

                    $wid = (int) ($it->warehouse_id ?? 0);
                    if ($wid <= 0) {
                        throw new \RuntimeException("Warehouse is not set for this Sale Delivery item (item ID {$itemId}). Please set warehouse first.");
                    }

                    $good   = (int) ($inputById[$itemId]['good'] ?? 0);
                    $defect = (int) ($inputById[$itemId]['defect'] ?? 0);
                    $damaged= (int) ($inputById[$itemId]['damaged'] ?? 0);

                    // GOOD: create from allocations
                    $alloc = $inputById[$itemId]['good_allocations'] ?? [];
                    if ($good > 0 && !empty($alloc)) {
                        foreach ($alloc as $a) {
                            $fromRackId = (int) ($a['from_rack_id'] ?? 0);
                            $qty = (int) ($a['qty'] ?? 0);
                            if ($fromRackId <= 0 || $qty <= 0) continue;

                            $this->mutationController->storeMutation([
                                'branch_id' => (int) $branchId,
                                'warehouse_id' => (int) $wid,
                                'rack_id' => (int) $fromRackId,
                                'reference' => $reference,
                                'product_id' => (int) $it->product_id,
                                'quantity' => (int) $qty,
                                'note' => 'Sales Delivery OUT (GOOD)',
                                'type' => 'out',
                                'condition' => 'good',
                            ]);
                        }
                    }

                    // DEFECT: move out each defect item id
                    $defIds = $inputById[$itemId]['selected_defect_ids'] ?? [];
                    if ($defect > 0 && !empty($defIds)) {
                        foreach ($defIds as $id) {
                            $id = (int) $id;
                            if ($id <= 0) continue;

                            $row = ProductDefectItem::query()
                                ->where('id', $id)
                                ->where('branch_id', (int) $branchId)
                                ->where('warehouse_id', (int) $wid)
                                ->where('product_id', (int) $it->product_id)
                                ->whereNull('moved_out_at')
                                ->lockForUpdate()
                                ->first();

                            if (!$row) {
                                throw new \RuntimeException("DEFECT item not found/invalid: ID {$id}.");
                            }

                            $this->mutationController->storeMutation([
                                'branch_id' => (int) $branchId,
                                'warehouse_id' => (int) $wid,
                                'rack_id' => (int) ($row->rack_id ?? 0),
                                'reference' => $reference,
                                'product_id' => (int) $it->product_id,
                                'quantity' => 1,
                                'note' => 'Sales Delivery OUT (DEFECT)',
                                'type' => 'out',
                                'condition' => 'defect',
                            ]);

                            $row->update([
                                'moved_out_at' => now(),
                                'moved_out_ref' => $reference,
                            ]);
                        }
                    }

                    // DAMAGED: move out each damaged item id
                    $damIds = $inputById[$itemId]['selected_damaged_ids'] ?? [];
                    if ($damaged > 0 && !empty($damIds)) {
                        foreach ($damIds as $id) {
                            $id = (int) $id;
                            if ($id <= 0) continue;

                            $row = ProductDamagedItem::query()
                                ->where('id', $id)
                                ->where('branch_id', (int) $branchId)
                                ->where('warehouse_id', (int) $wid)
                                ->where('product_id', (int) $it->product_id)
                                ->where('damage_type', 'damaged')
                                ->where('resolution_status', 'pending')
                                ->whereNull('moved_out_at')
                                ->lockForUpdate()
                                ->first();

                            if (!$row) {
                                throw new \RuntimeException("DAMAGED item not found/invalid: ID {$id}.");
                            }

                            $this->mutationController->storeMutation([
                                'branch_id' => (int) $branchId,
                                'warehouse_id' => (int) $wid,
                                'rack_id' => (int) ($row->rack_id ?? 0),
                                'reference' => $reference,
                                'product_id' => (int) $it->product_id,
                                'quantity' => 1,
                                'note' => 'Sales Delivery OUT (DAMAGED)',
                                'type' => 'out',
                                'condition' => 'damaged',
                            ]);

                            $row->update([
                                'moved_out_at' => now(),
                                'moved_out_ref' => $reference,
                            ]);
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
