<?php

namespace Modules\Adjustment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustment\DataTables\AdjustmentsDataTable;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Mutation\Http\Controllers\MutationController;

class AdjustmentController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    public function index(AdjustmentsDataTable $dataTable)
    {
        abort_if(Gate::denies('access_adjustments'), 403);
        return $dataTable->render('adjustment::index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');

        // konsisten seperti modul lain: kalau ALL, jangan boleh create
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('message', 'Please select a specific branch first (not ALL Branch).');
        }

        $activeBranchId = (int) $active;

        $warehouses = Warehouse::query()
            ->where('branch_id', $activeBranchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId = (int) (
            optional($warehouses->firstWhere('is_main', 1))->id
            ?? optional($warehouses->first())->id
            ?? 0
        );

        // ✅ NEW: explicit var for Stock tab (dipakai JS buat restore saat toggle ADD/SUB)
        $defaultStockWarehouseId = $defaultWarehouseId;

        // ✅ NEW: racks mapping for stock_add UI (window.RACKS_BY_WAREHOUSE)
        $warehouseIds = $warehouses->pluck('id')->map(fn ($v) => (int) $v)->values()->all();

        $racksByWarehouse = [];
        if (!empty($warehouseIds)) {
            $rows = DB::table('racks')
                ->whereIn('warehouse_id', $warehouseIds)
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'warehouse_id', 'code', 'name']);

            foreach ($rows as $r) {
                $wid = (int) $r->warehouse_id;
                $label = trim((string)($r->code ?? '')) . ' - ' . trim((string)($r->name ?? ''));
                $racksByWarehouse[$wid][] = [
                    'id' => (int) $r->id,
                    'label' => trim($label, ' -'),
                ];
            }
        }

        return view('adjustment::create', compact(
            'warehouses',
            'defaultWarehouseId',
            'defaultStockWarehouseId', // ✅ NEW
            'activeBranchId',
            'racksByWarehouse'
        ));
    }

    public function stockSubPickerData(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please choose a specific branch first (not 'All Branch').",
            ], 422);
        }

        $branchId    = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');
        $productId   = (int) $request->query('product_id');

        if ($warehouseId <= 0 || $productId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'warehouse_id and product_id are required.',
            ], 422);
        }

        // racks list (for dropdown/filter + label)
        $racks = DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(function ($r) {
                $label = trim((string)($r->code ?? '')) . ' - ' . trim((string)($r->name ?? ''));
                $label = trim($label, ' -');
                return [
                    'id'    => (int) $r->id,
                    'code'  => (string) ($r->code ?? ''),
                    'name'  => (string) ($r->name ?? ''),
                    'label' => $label !== '' ? $label : ('Rack #' . (int)$r->id),
                ];
            })
            ->values();

        // ✅ RESERVED (biar GOOD available sinkron dengan Inventory Stocks)
        $stockHeader = \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['qty_reserved', 'qty_available']);

        $warehouseReserved = (int) ($stockHeader->qty_reserved ?? 0);

        // stock_racks by rack (qty_good/defect/damaged)
        $stockRows = DB::table('stock_racks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->get([
                'rack_id',
                'qty_good',
                'qty_defect',
                'qty_damaged',
                'qty_available',
            ]);

        $stockByRack = [];
        foreach ($stockRows as $sr) {
            $rid = (int) ($sr->rack_id ?? 0);
            if ($rid <= 0) continue;

            $good   = (int) ($sr->qty_good ?? 0);
            $defect = (int) ($sr->qty_defect ?? 0);
            $dam    = (int) ($sr->qty_damaged ?? 0);

            $total = (int) ($sr->qty_available ?? 0);
            if ($total <= 0) $total = $good + $defect + $dam;

            $stockByRack[$rid] = [
                'rack_id' => $rid,
                'total'   => $total,
                'good'    => $good,
                'defect'  => $defect,
                'damaged' => $dam,
            ];
        }

        // defect units (available only)
        $defects = ProductDefectItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('rack_id')
            ->orderBy('id')
            ->get(['id', 'rack_id', 'defect_type', 'description'])
            ->map(function ($d) {
                return [
                    'id'          => (int) $d->id,
                    'rack_id'     => (int) $d->rack_id,
                    'defect_type' => (string) ($d->defect_type ?? ''),
                    'description' => (string) ($d->description ?? ''),
                ];
            })
            ->values();

        // damaged units (available only)
        $damaged = ProductDamagedItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('rack_id')
            ->orderBy('id')
            ->get(['id', 'rack_id', 'damage_type', 'reason'])
            ->map(function ($d) {
                return [
                    'id'          => (int) $d->id,
                    'rack_id'     => (int) $d->rack_id,
                    'damage_type' => (string) ($d->damage_type ?? 'damaged'),
                    'reason'      => (string) ($d->reason ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id'     => $branchId,
                'warehouse_id'  => $warehouseId,
                'product_id'    => $productId,
                'racks'         => $racks,
                'stock_by_rack' => $stockByRack,

                // ✅ NEW
                'warehouse_reserved' => $warehouseReserved,

                'defect_units'  => $defects,
                'damaged_units' => $damaged,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        // =========================================================
        // 0) Branch guard
        // =========================================================
        $active = session('active_branch');
        if ($active === 'all' || $active === null || (int) $active <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('message', 'Please select an active branch (not ALL) before creating adjustment.');
        }
        $branchId = (int) $active;

        // =========================================================
        // 1) Read adj type
        // =========================================================
        $adjType = $request->input('adjustment_type', 'add');
        if (!in_array($adjType, ['add', 'sub'], true)) {
            return redirect()->back()->withInput()->with('message', 'Invalid adjustment type.');
        }

        // =========================================================
        // 2) Base validation
        // =========================================================
        $rules = [
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
        ];

        // ✅ ONLY ADD requires header warehouse_id
        if ($adjType === 'add') {
            $rules['warehouse_id'] = ['required', 'integer', 'exists:warehouses,id'];
        }

        $request->validate($rules);

        $date = (string) $request->date;
        $note = html_entity_decode((string) ($request->note ?? ''), ENT_QUOTES, 'UTF-8');

        $normalizeIds = function ($arr): array {
            $arr = is_array($arr) ? $arr : [];
            $arr = array_map('intval', $arr);
            $arr = array_values(array_unique(array_filter($arr, fn ($x) => $x > 0)));
            return $arr;
        };

        try {

            // =========================================================
            // ======================= ADD =============================
            // =========================================================
            if ($adjType === 'add') {

                $warehouseId = (int) $request->warehouse_id;

                $wh = Warehouse::query()->where('id', $warehouseId)->first();
                if (!$wh) {
                    return redirect()->back()->withInput()->with('message', 'Warehouse not found.');
                }
                if ((int) $wh->branch_id !== $branchId) {
                    return redirect()->back()->withInput()->with('message', 'Selected warehouse is not in active branch.');
                }

                $request->validate([
                    'items.*.qty_good'    => ['nullable', 'integer', 'min:0'],
                    'items.*.qty_defect'  => ['nullable', 'integer', 'min:0'],
                    'items.*.qty_damaged' => ['nullable', 'integer', 'min:0'],
                ]);

                DB::transaction(function () use ($request, $warehouseId, $branchId, $date, $note) {

                    $adjustment = Adjustment::query()->create([
                        'date'         => $date,
                        'reference'    => 'ADJ',
                        'warehouse_id' => $warehouseId,
                        'note'         => $note,
                        'user_id'      => Auth::id(),
                        'branch_id'    => $branchId,
                        'type'         => 'stock_add',
                    ]);

                    $items = $request->input('items', []);
                    $reference = (string) ($adjustment->reference ?? ('ADJ-' . (int) $adjustment->id));

                    foreach ($items as $idx => $item) {

                        $productId = (int) ($item['product_id'] ?? 0);
                        $good      = (int) ($item['qty_good'] ?? 0);
                        $defect    = (int) ($item['qty_defect'] ?? 0);
                        $damaged   = (int) ($item['qty_damaged'] ?? 0);

                        $total = $good + $defect + $damaged;
                        if ($total <= 0) continue;

                        Product::findOrFail($productId);

                        $baseNote = trim(
                            'Adjustment Add #' . (int) $adjustment->id
                            . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
                        );

                        // A) GOOD allocations
                        $goodAllocations = (array) ($item['good_allocations'] ?? []);

                        if ($good > 0) {
                            $sumAlloc = 0;
                            foreach ($goodAllocations as $ga) {
                                $gaQty = (int) ($ga['qty'] ?? 0);
                                $sumAlloc += $gaQty;

                                if ($gaQty > 0 && empty($ga['to_rack_id'])) {
                                    throw new \RuntimeException("Row #{$idx}: GOOD allocation rack is required when qty > 0.");
                                }
                            }

                            if ($sumAlloc !== $good) {
                                throw new \RuntimeException("Row #{$idx}: GOOD allocation total ({$sumAlloc}) must equal GOOD ({$good}).");
                            }

                            foreach ($goodAllocations as $ga) {
                                $gaQty    = (int) ($ga['qty'] ?? 0);
                                $toRackId = (int) ($ga['to_rack_id'] ?? 0);
                                if ($gaQty <= 0) continue;

                                $this->assertRackBelongsToWarehouse($toRackId, $warehouseId);

                                $this->mutationController->applyInOut(
                                    $branchId,
                                    $warehouseId,
                                    $productId,
                                    'In',
                                    $gaQty,
                                    $reference,
                                    $baseNote . ' | GOOD',
                                    $date,
                                    $toRackId,
                                    'good'
                                );

                                AdjustedProduct::query()->create([
                                    'adjustment_id' => (int) $adjustment->id,
                                    'product_id'    => (int) $productId,
                                    'warehouse_id'  => (int) $warehouseId,
                                    'rack_id'       => (int) $toRackId,
                                    'quantity'      => (int) $gaQty,
                                    'type'          => 'add',
                                    'note'          => 'COND=GOOD',
                                ]);
                            }
                        }

                        // B) DEFECT per-unit
                        $defects = (array) ($item['defects'] ?? []);

                        if ($defect > 0 && count($defects) !== $defect) {
                            throw new \RuntimeException("Row #{$idx}: Defect qty ({$defect}) not match defect detail rows (" . count($defects) . ").");
                        }

                        if ($defect > 0) {
                            $countByRack = [];

                            foreach ($defects as $i => $d) {
                                $rackId     = (int) ($d['to_rack_id'] ?? 0);
                                $defectType = (string) ($d['defect_type'] ?? '');
                                $desc       = (string) ($d['defect_description'] ?? '');

                                if ($rackId <= 0 || trim($defectType) === '') {
                                    throw new \RuntimeException("Row #{$idx}: defect detail #" . ($i + 1) . " rack/type is required.");
                                }

                                $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                                $photoPath = null;
                                if ($request->hasFile("items.$idx.defects.$i.photo")) {
                                    $photo = $request->file("items.$idx.defects.$i.photo");
                                    $photoPath = $photo->store('adjustments/defects', 'public');
                                }

                                ProductDefectItem::query()->create([
                                    'branch_id'      => $branchId,
                                    'warehouse_id'   => $warehouseId,
                                    'rack_id'        => $rackId,
                                    'product_id'     => $productId,
                                    'reference_id'   => (int) $adjustment->id,
                                    'reference_type' => Adjustment::class,
                                    'quantity'       => 1,
                                    'defect_type'    => $defectType,
                                    'description'    => trim($desc) !== '' ? $desc : null,
                                    'photo_path'     => $photoPath,
                                    'created_by'     => (int) Auth::id(),
                                ]);

                                $countByRack[$rackId] = ($countByRack[$rackId] ?? 0) + 1;
                            }

                            foreach ($countByRack as $rackId => $qtyRack) {

                                $this->mutationController->applyInOut(
                                    $branchId,
                                    $warehouseId,
                                    $productId,
                                    'In',
                                    (int) $qtyRack,
                                    $reference,
                                    $baseNote . ' | DEFECT',
                                    $date,
                                    (int) $rackId,
                                    'defect'
                                );

                                AdjustedProduct::query()->create([
                                    'adjustment_id' => (int) $adjustment->id,
                                    'product_id'    => (int) $productId,
                                    'warehouse_id'  => (int) $warehouseId,
                                    'rack_id'       => (int) $rackId,
                                    'quantity'      => (int) $qtyRack,
                                    'type'          => 'add',
                                    'note'          => 'COND=DEFECT',
                                ]);
                            }
                        }

                        // C) DAMAGED per-unit
                        $damages = (array) ($item['damaged_items'] ?? []);

                        if ($damaged > 0 && count($damages) !== $damaged) {
                            throw new \RuntimeException("Row #{$idx}: Damaged qty ({$damaged}) not match damaged detail rows (" . count($damages) . ").");
                        }

                        if ($damaged > 0) {
                            $rowsByRack = [];

                            foreach ($damages as $i => $d) {
                                $rackId = (int) ($d['to_rack_id'] ?? 0);

                                $damageType = strtolower(trim((string) ($d['damage_type'] ?? 'damaged')));
                                if (!in_array($damageType, ['damaged', 'missing'], true)) $damageType = 'damaged';

                                $reason = trim((string) ($d['reason'] ?? ''));

                                if ($rackId <= 0 || $reason === '') {
                                    throw new \RuntimeException("Row #{$idx}: damaged detail #" . ($i + 1) . " rack/reason is required.");
                                }

                                $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                                $photoPath = null;
                                if ($request->hasFile("items.$idx.damaged_items.$i.photo")) {
                                    $photo = $request->file("items.$idx.damaged_items.$i.photo");
                                    $photoPath = $photo->store('adjustments/damaged', 'public');
                                }

                                $row = ProductDamagedItem::query()->create([
                                    'branch_id'           => $branchId,
                                    'warehouse_id'        => $warehouseId,
                                    'rack_id'             => $rackId,
                                    'product_id'          => $productId,
                                    'reference_id'        => (int) $adjustment->id,
                                    'reference_type'      => Adjustment::class,
                                    'quantity'            => 1,
                                    'damage_type'         => $damageType,
                                    'reason'              => $reason,
                                    'photo_path'          => $photoPath,
                                    'cause'               => null,
                                    'responsible_user_id' => null,
                                    'resolution_status'   => 'pending',
                                    'resolution_note'     => null,
                                    'mutation_in_id'      => null,
                                    'mutation_out_id'     => null,
                                    'created_by'          => (int) Auth::id(),
                                ]);

                                $rowsByRack[$rackId][] = $row;
                            }

                            foreach ($rowsByRack as $rackId => $rows) {
                                $qtyRack = count($rows);

                                $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                                    $branchId,
                                    $warehouseId,
                                    $productId,
                                    'In',
                                    (int) $qtyRack,
                                    $reference,
                                    $baseNote . ' | DAMAGED',
                                    $date,
                                    (int) $rackId,
                                    'damaged'
                                );

                                foreach ($rows as $row) {
                                    $row->update(['mutation_in_id' => (int) $mutationInId]);
                                }

                                AdjustedProduct::query()->create([
                                    'adjustment_id' => (int) $adjustment->id,
                                    'product_id'    => (int) $productId,
                                    'warehouse_id'  => (int) $warehouseId,
                                    'rack_id'       => (int) $rackId,
                                    'quantity'      => (int) $qtyRack,
                                    'type'          => 'add',
                                    'note'          => 'COND=DAMAGED',
                                ]);
                            }
                        }
                    }
                });

                toast('Adjustment created successfully.', 'success');
                return redirect()->route('adjustments.index');
            }

            // =========================================================
            // ======================= SUB =============================
            // =========================================================
            $request->validate([
                'items.*.qty'  => ['required', 'integer', 'min:1'],
                'items.*.note' => ['required', 'string', 'min:1', 'max:1000'],
            ]);

            DB::transaction(function () use ($request, $branchId, $date, $note, $normalizeIds) {

                $items = $request->input('items', []);

                $fallbackWarehouseId = 0;
                foreach ($items as $it) {
                    if (!empty($it['good_allocations']) && is_array($it['good_allocations'])) {
                        foreach ($it['good_allocations'] as $ga) {
                            $wid = (int) ($ga['warehouse_id'] ?? 0);
                            if ($wid > 0) { $fallbackWarehouseId = $wid; break 2; }
                        }
                    }
                }

                if ($fallbackWarehouseId <= 0) {
                    $fallbackWarehouseId = (int) Warehouse::query()
                        ->where('branch_id', $branchId)
                        ->orderByDesc('is_main')
                        ->value('id') ?: 0;
                }

                if ($fallbackWarehouseId <= 0) {
                    throw new \RuntimeException("No warehouse found for this branch.");
                }

                $adjustment = Adjustment::query()->create([
                    'date'         => $date,
                    'reference'    => 'ADJ',
                    'warehouse_id' => $fallbackWarehouseId,
                    'note'         => $note,
                    'user_id'      => Auth::id(),
                    'branch_id'    => $branchId,
                    'type'         => 'stock_sub',
                ]);

                $reference = (string) ($adjustment->reference ?? ('ADJ-' . (int) $adjustment->id));

                foreach ($items as $idx => $item) {

                    $productId = (int) ($item['product_id'] ?? 0);
                    $expected  = (int) ($item['qty'] ?? 0);

                    $itemNote  = trim(html_entity_decode((string) ($item['note'] ?? ''), ENT_QUOTES, 'UTF-8'));

                    if ($productId <= 0) continue;
                    if ($expected <= 0) {
                        throw new \RuntimeException("Row #{$idx}: Qty must be >= 1.");
                    }
                    if ($itemNote === '') {
                        throw new \RuntimeException("Row #{$idx}: Note is required.");
                    }

                    Product::findOrFail($productId);

                    $mutationNoteBase = trim(
                        'Adjustment SUB #' . (int) $adjustment->id
                        . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
                        . ' | Item: ' . $itemNote
                    );

                    $hasPickMode =
                        isset($item['good_allocations']) ||
                        isset($item['selected_defect_ids']) || isset($item['defect_ids']) ||
                        isset($item['selected_damaged_ids']) || isset($item['damaged_ids']);

                    if (!$hasPickMode) {
                        throw new \RuntimeException("Row #{$idx}: SUB must use Pick Items mode (good_allocations / selected ids).");
                    }

                    // 1) GOOD allocations
                    $goodAlloc = is_array($item['good_allocations'] ?? null) ? $item['good_allocations'] : [];
                    $goodAllocNormalized = [];
                    $goodTotal = 0;

                    foreach ($goodAlloc as $ga) {
                        $wid = (int) ($ga['warehouse_id'] ?? 0);
                        $rid = (int) ($ga['from_rack_id'] ?? ($ga['rack_id'] ?? 0));
                        $q   = (int) ($ga['qty'] ?? 0);

                        if ($wid <= 0 || $rid <= 0 || $q <= 0) continue;

                        $wh = Warehouse::query()->where('id', $wid)->first();
                        if (!$wh || (int)$wh->branch_id !== $branchId) {
                            throw new \RuntimeException("Row #{$idx}: Invalid warehouse in GOOD allocation.");
                        }

                        $this->assertRackBelongsToWarehouse($rid, $wid);

                        $goodAllocNormalized[] = [
                            'warehouse_id' => $wid,
                            'rack_id'      => $rid,
                            'qty'          => $q,
                        ];
                        $goodTotal += $q;
                    }

                    // 2) DEFECT/DAMAGED picked ids
                    $defectIds  = $normalizeIds($item['selected_defect_ids'] ?? ($item['defect_ids'] ?? []));
                    $damagedIds = $normalizeIds($item['selected_damaged_ids'] ?? ($item['damaged_ids'] ?? []));

                    $defectTotal  = count($defectIds);
                    $damagedTotal = count($damagedIds);

                    $totalSelected = $goodTotal + $defectTotal + $damagedTotal;
                    if ($totalSelected !== $expected) {
                        throw new \RuntimeException("Row #{$idx}: Total selected must equal Qty. Qty={$expected}, Selected={$totalSelected}.");
                    }

                    // Mutation OUT for GOOD allocations
                    foreach ($goodAllocNormalized as $ga) {
                        $wid = (int) $ga['warehouse_id'];
                        $rid = (int) $ga['rack_id'];
                        $q   = (int) $ga['qty'];

                        $this->mutationController->applyInOut(
                            $branchId,
                            $wid,
                            $productId,
                            'Out',
                            $q,
                            $reference,
                            $mutationNoteBase . ' | GOOD',
                            $date,
                            $rid,
                            'good'
                        );

                        AdjustedProduct::query()->create([
                            'adjustment_id' => (int) $adjustment->id,
                            'product_id'    => (int) $productId,
                            'warehouse_id'  => (int) $wid,
                            'rack_id'       => (int) $rid,
                            'quantity'      => (int) $q,
                            'type'          => 'sub',
                            'note'          => trim('Item: ' . $itemNote . ' | COND=GOOD'),
                        ]);
                    }

                    // DEFECT picked
                    if ($defectTotal > 0) {
                        $itemsQ = ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $defectIds)
                            ->lockForUpdate()
                            ->get(['id','warehouse_id','rack_id']);

                        if ($itemsQ->count() !== $defectTotal) {
                            throw new \RuntimeException("Row #{$idx}: SUB DEFECT invalid selection (available units mismatch). Need={$defectTotal}, Found={$itemsQ->count()}.");
                        }

                        $group = [];
                        foreach ($itemsQ as $u) {
                            $wid = (int) $u->warehouse_id;
                            $rid = (int) $u->rack_id;

                            $wh = Warehouse::query()->where('id', $wid)->first();
                            if (!$wh || (int)$wh->branch_id !== $branchId) {
                                throw new \RuntimeException("Row #{$idx}: DEFECT unit warehouse invalid.");
                            }
                            $this->assertRackBelongsToWarehouse($rid, $wid);

                            $key = $wid . ':' . $rid;
                            $group[$key]['warehouse_id'] = $wid;
                            $group[$key]['rack_id'] = $rid;
                            $group[$key]['ids'][] = (int) $u->id;
                        }

                        foreach ($group as $g) {
                            $wid = (int) $g['warehouse_id'];
                            $rid = (int) $g['rack_id'];
                            $ids = $g['ids'] ?? [];
                            $qtyRack = count($ids);

                            $this->mutationController->applyInOut(
                                $branchId,
                                $wid,
                                $productId,
                                'Out',
                                $qtyRack,
                                $reference,
                                $mutationNoteBase . ' | DEFECT',
                                $date,
                                $rid,
                                'defect'
                            );

                            // ✅ IMPORTANT: bypass fillable (hard update)
                            ProductDefectItem::query()
                                ->where('branch_id', $branchId)
                                ->where('warehouse_id', $wid)
                                ->where('rack_id', $rid)
                                ->where('product_id', $productId)
                                ->whereNull('moved_out_at')
                                ->whereIn('id', $ids)
                                ->update([
                                    'moved_out_at'             => now(),
                                    'moved_out_by'             => (int) Auth::id(),
                                    'moved_out_reference_type' => Adjustment::class,
                                    'moved_out_reference_id'   => (int) $adjustment->id,
                                    'updated_at'               => now(),
                                ]);

                            AdjustedProduct::query()->create([
                                'adjustment_id' => (int) $adjustment->id,
                                'product_id'    => (int) $productId,
                                'warehouse_id'  => (int) $wid,
                                'rack_id'       => (int) $rid,
                                'quantity'      => (int) $qtyRack,
                                'type'          => 'sub',
                                'note'          => trim('Item: ' . $itemNote . ' | COND=DEFECT'),
                            ]);
                        }
                    }

                    // DAMAGED picked
                    if ($damagedTotal > 0) {
                        $itemsQ = ProductDamagedItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $damagedIds)
                            ->lockForUpdate()
                            ->get(['id','warehouse_id','rack_id']);

                        if ($itemsQ->count() !== $damagedTotal) {
                            throw new \RuntimeException("Row #{$idx}: SUB DAMAGED invalid selection (available units mismatch). Need={$damagedTotal}, Found={$itemsQ->count()}.");
                        }

                        $group = [];
                        foreach ($itemsQ as $u) {
                            $wid = (int) $u->warehouse_id;
                            $rid = (int) $u->rack_id;

                            $wh = Warehouse::query()->where('id', $wid)->first();
                            if (!$wh || (int)$wh->branch_id !== $branchId) {
                                throw new \RuntimeException("Row #{$idx}: DAMAGED unit warehouse invalid.");
                            }
                            $this->assertRackBelongsToWarehouse($rid, $wid);

                            $key = $wid . ':' . $rid;
                            $group[$key]['warehouse_id'] = $wid;
                            $group[$key]['rack_id'] = $rid;
                            $group[$key]['ids'][] = (int) $u->id;
                        }

                        foreach ($group as $g) {
                            $wid = (int) $g['warehouse_id'];
                            $rid = (int) $g['rack_id'];
                            $ids = $g['ids'] ?? [];
                            $qtyRack = count($ids);

                            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                                $branchId,
                                $wid,
                                $productId,
                                'Out',
                                $qtyRack,
                                $reference,
                                $mutationNoteBase . ' | DAMAGED',
                                $date,
                                $rid,
                                'damaged'
                            );

                            // ✅ IMPORTANT: bypass fillable (hard update)
                            ProductDamagedItem::query()
                                ->where('branch_id', $branchId)
                                ->where('warehouse_id', $wid)
                                ->where('rack_id', $rid)
                                ->where('product_id', $productId)
                                ->whereNull('moved_out_at')
                                ->whereIn('id', $ids)
                                ->update([
                                    'moved_out_at'             => now(),
                                    'moved_out_by'             => (int) Auth::id(),
                                    'moved_out_reference_type' => Adjustment::class,
                                    'moved_out_reference_id'   => (int) $adjustment->id,
                                    'mutation_out_id'          => (int) $mutationOutId,
                                    'updated_at'               => now(),
                                ]);

                            AdjustedProduct::query()->create([
                                'adjustment_id' => (int) $adjustment->id,
                                'product_id'    => (int) $productId,
                                'warehouse_id'  => (int) $wid,
                                'rack_id'       => (int) $rid,
                                'quantity'      => (int) $qtyRack,
                                'type'          => 'sub',
                                'note'          => trim('Item: ' . $itemNote . ' | COND=DAMAGED'),
                            ]);
                        }
                    }
                }
            });

            toast('Adjustment created successfully.', 'success');
            return redirect()->route('adjustments.index');

        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('message', $e->getMessage());
        }
    }

    public function show(Adjustment $adjustment)
    {
        abort_if(Gate::denies('show_adjustments'), 403);

        $adjustment->loadMissing([
            'creator',
            'branch',
            'warehouse',
            'adjustedProducts.product',
        ]);

        return view('adjustment::show', compact('adjustment'));
    }

    public function edit(Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to edit an adjustment.");
        }
        $activeBranchId = (int) $active;

        // (optional tapi rapi) pastikan data memang milik branch aktif
        if ((int) $adjustment->branch_id !== $activeBranchId) {
            abort(403, 'You can only edit adjustments from the active branch.');
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', $activeBranchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId = (int) ($adjustment->warehouse_id ?: optional($warehouses->firstWhere('is_main', 1))->id);

        return view('adjustment::edit', compact('adjustment', 'warehouses', 'activeBranchId', 'defaultWarehouseId'));
    }

    public function pickUnits(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please select a specific branch (not All Branch)."
            ], 422);
        }

        $branchId    = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');
        $productId   = (int) $request->query('product_id');
        $condition   = strtolower((string) $request->query('condition', 'defect')); // defect|damaged
        $rackId      = (int) $request->query('rack_id', 0); // REQUIRED for SUB modal (we enforce)

        if ($warehouseId <= 0 || $productId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'warehouse_id and product_id are required.'
            ], 422);
        }

        if (!in_array($condition, ['defect', 'damaged'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid condition. Must be defect or damaged.'
            ], 422);
        }

        // ✅ rack is required to keep rack stock integrity
        if ($rackId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'rack_id is required.'
            ], 422);
        }

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse must belong to active branch.'
            ], 403);
        }

        // ✅ rack belongs to warehouse
        $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

        Product::findOrFail($productId);

        // helper: rack label
        $rackLabelMap = DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(function ($r) {
                $label = trim((string)($r->code ?? '') . ((string)($r->name ?? '') !== '' ? ' - ' . (string)($r->name ?? '') : ''));
                if ($label === '') $label = 'Rack#' . (int)$r->id;
                return [(int)$r->id => $label];
            })
            ->toArray();

        if ($condition === 'defect') {

            $rows = ProductDefectItem::query()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $warehouseId)
                ->where('rack_id', $rackId)
                ->where('product_id', $productId)
                ->whereNull('moved_out_at')
                ->orderBy('id', 'asc')
                ->limit(1000)
                ->get(['id', 'rack_id', 'defect_type', 'description']);

            $data = $rows->map(function ($r) use ($rackLabelMap) {
                $rackText = $rackLabelMap[(int)$r->rack_id] ?? ('Rack#' . (int)$r->rack_id);

                $parts = [];
                $parts[] = "Rack: {$rackText}";
                $dt = trim((string) $r->defect_type);
                $ds = trim((string) $r->description);

                if ($dt !== '') $parts[] = "Type: {$dt}";
                if ($ds !== '') $parts[] = $ds;

                return [
                    'id'      => (int) $r->id,
                    'rack_id' => (int) $r->rack_id,
                    'label'   => implode(' | ', $parts),
                ];
            })->values();

            return response()->json([
                'success'   => true,
                'condition' => 'defect',
                'data'      => $data,
            ]);
        }

        // ✅ damaged: include damage_type + reason
        $rows = ProductDamagedItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('id', 'asc')
            ->limit(1000)
            ->get(['id', 'rack_id', 'damage_type', 'reason']);

        $data = $rows->map(function ($r) use ($rackLabelMap) {
            $rackText = $rackLabelMap[(int)$r->rack_id] ?? ('Rack#' . (int)$r->rack_id);

            $parts = [];
            $parts[] = "Rack: {$rackText}";

            $dt = trim((string) ($r->damage_type ?? ''));
            $rs = trim((string) ($r->reason ?? ''));

            if ($dt !== '') $parts[] = "Type: {$dt}";
            if ($rs !== '') $parts[] = $rs;

            return [
                'id'      => (int) $r->id,
                'rack_id' => (int) $r->rack_id,
                'label'   => implode(' | ', $parts),
            ];
        })->values();

        return response()->json([
            'success'   => true,
            'condition' => 'damaged',
            'data'      => $data,
        ]);
    }

    public function update(Request $request, Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $request->validate([
            'date'          => 'required|date',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'note'          => 'nullable|string|max:1000',

            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',

            'rack_ids'      => 'required|array|min:1',
            'rack_ids.*'    => 'required|integer|exists:racks,id',

            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array|min:1',
            'types.*'       => 'required|in:add,sub',

            'conditions'    => 'required|array|min:1',
            'conditions.*'  => 'required|in:good,defect,damaged',

            'notes'         => 'nullable|array',

            'defects_json'        => 'nullable|array',
            'defects_json.*'      => 'nullable|string',
            'damaged_json'        => 'nullable|array',
            'damaged_json.*'      => 'nullable|string',

            'defect_unit_ids'     => 'nullable|array',
            'defect_unit_ids.*'   => 'nullable|string',
            'damaged_unit_ids'    => 'nullable|array',
            'damaged_unit_ids.*'  => 'nullable|string',
        ]);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Please choose a specific branch first (not 'All Branch') to update an adjustment.");
        }
        $branchId = (int) $active;

        if ((int) $adjustment->branch_id !== $branchId) {
            abort(403, 'You can only update adjustments from the active branch.');
        }

        $newWarehouseId = (int) $request->warehouse_id;

        $wh = Warehouse::findOrFail($newWarehouseId);
        if ((int) $wh->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        $decodeJsonArray = function ($raw): array {
            if ($raw === null) return [];
            if (is_array($raw)) return $raw;
            $raw = trim((string) $raw);
            if ($raw === '') return [];
            $arr = json_decode($raw, true);
            return is_array($arr) ? $arr : [];
        };

        DB::transaction(function () use ($request, $adjustment, $branchId, $newWarehouseId, $decodeJsonArray) {

            // ✅ Guard: kalau unit defect/damaged dari adjustment ini sudah pernah moved_out, jangan boleh update
            $usedDefect = ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->whereNotNull('moved_out_at')
                ->exists();

            $usedDamaged = ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->whereNotNull('moved_out_at')
                ->exists();

            if ($usedDefect || $usedDamaged) {
                throw new \RuntimeException("Cannot update this adjustment because some DEFECT/DAMAGED items from this adjustment have been moved out (already used in another transaction).");
            }

            $reference = (string) $adjustment->reference;

            // rollback mutation lama
            $this->mutationController->rollbackByReference($reference, 'Adjustment');

            // hapus per-unit rows lama (aman karena guard memastikan belum moved_out)
            ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            // hapus detail log
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // update header
            $adjustment->update([
                'date'         => (string) $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $newWarehouseId,
            ]);

            foreach ($request->product_ids as $key => $productId) {

                $productId = (int) $productId;
                $rackId    = (int) ($request->rack_ids[$key] ?? 0);
                $qty       = (int) ($request->quantities[$key] ?? 0);
                $type      = (string) ($request->types[$key] ?? '');
                $condition = strtolower((string) ($request->conditions[$key] ?? 'good'));
                $itemNote  = $request->notes[$key] ?? null;

                if ($rackId <= 0) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Rack is required for every item.')
                    );
                }

                $this->assertRackBelongsToWarehouse($rackId, $newWarehouseId);

                if ($qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Invalid item data.')
                    );
                }

                if (!in_array($condition, ['good', 'defect', 'damaged'], true)) {
                    $condition = 'good';
                }

                Product::findOrFail($productId);

                // =========================
                // per-unit validate
                // =========================
                $defectUnits = [];
                if ($type === 'add' && $condition === 'defect') {
                    $defectUnits = $decodeJsonArray($request->defects_json[$key] ?? null);
                    if (count($defectUnits) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "DEFECT details must be filled per unit. Line #" . ($key + 1) . " Qty={$qty}, Details=" . count($defectUnits))
                        );
                    }
                    foreach ($defectUnits as $i => $u) {
                        $u = (array) $u;
                        if (trim((string) ($u['defect_type'] ?? '')) === '') {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                redirect()->back()->withInput()->with('error', "Defect Type is required for each DEFECT unit (line #" . ($key + 1) . ", row #" . ($i + 1) . ").")
                            );
                        }
                    }
                }

                $damagedUnits = [];
                if ($type === 'add' && $condition === 'damaged') {
                    $damagedUnits = $decodeJsonArray($request->damaged_json[$key] ?? null);
                    if (count($damagedUnits) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "DAMAGED details must be filled per unit. Line #" . ($key + 1) . " Qty={$qty}, Details=" . count($damagedUnits))
                        );
                    }
                    foreach ($damagedUnits as $i => $u) {
                        $u = (array) $u;
                        if (trim((string) ($u['reason'] ?? '')) === '') {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                redirect()->back()->withInput()->with('error', "Damaged Reason is required for each DAMAGED unit (line #" . ($key + 1) . ", row #" . ($i + 1) . ").")
                            );
                        }
                    }
                }

                $defectPickIds = [];
                if ($type === 'sub' && $condition === 'defect') {
                    $defectPickIds = $decodeJsonArray($request->defect_unit_ids[$key] ?? null);
                    if (count($defectPickIds) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "SUB DEFECT must pick unit IDs per qty. Line #" . ($key + 1) . " Qty={$qty}, Picked=" . count($defectPickIds))
                        );
                    }
                    $defectPickIds = array_values(array_unique(array_map('intval', $defectPickIds)));
                    if (count($defectPickIds) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "SUB DEFECT picked IDs must be unique and match Qty. Line #" . ($key + 1) . ".")
                        );
                    }
                }

                $damagedPickIds = [];
                if ($type === 'sub' && $condition === 'damaged') {
                    $damagedPickIds = $decodeJsonArray($request->damaged_unit_ids[$key] ?? null);
                    if (count($damagedPickIds) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "SUB DAMAGED must pick unit IDs per qty. Line #" . ($key + 1) . " Qty={$qty}, Picked=" . count($damagedPickIds))
                        );
                    }
                    $damagedPickIds = array_values(array_unique(array_map('intval', $damagedPickIds)));
                    if (count($damagedPickIds) !== $qty) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            redirect()->back()->withInput()->with('error', "SUB DAMAGED picked IDs must be unique and match Qty. Line #" . ($key + 1) . ".")
                        );
                    }
                }

                // =========================
                // log
                // =========================
                $adjustedNotePieces = [];
                if (!empty($itemNote)) $adjustedNotePieces[] = "Item: {$itemNote}";
                $adjustedNotePieces[] = 'COND=' . strtoupper($condition);
                $adjustedNote = trim(implode(' | ', $adjustedNotePieces));

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $productId,
                    'rack_id'       => $rackId,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'note'          => $adjustedNote ?: null,
                ]);

                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | ' . $adjustment->note : '')
                    . ($itemNote ? ' | Item: ' . $itemNote : '')
                    . ' | ' . strtoupper($condition)
                );

                // =========================
                // GOOD
                // =========================
                if ($condition === 'good') {
                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        $type === 'add' ? 'In' : 'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );
                    continue;
                }

                // =========================
                // ✅ SUB DEFECT (pick ids) - ENFORCE RACK (PATCH)
                // =========================
                if ($type === 'sub' && $condition === 'defect') {

                    $items = ProductDefectItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId) // ✅ PATCH: enforce rack
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $defectPickIds)
                        ->lockForUpdate()
                        ->get();

                    if ($items->count() !== $qty) {
                        throw new \RuntimeException("SUB DEFECT invalid selection (must match selected rack). Need={$qty}, Found={$items->count()}.");
                    }

                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    foreach ($items as $it) {
                        $it->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                        ]);
                    }

                    continue;
                }

                // =========================
                // ✅ SUB DAMAGED (pick ids) - ENFORCE RACK (PATCH)
                // =========================
                if ($type === 'sub' && $condition === 'damaged') {

                    $items = ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId) // ✅ PATCH: enforce rack
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $damagedPickIds)
                        ->lockForUpdate()
                        ->get();

                    if ($items->count() !== $qty) {
                        throw new \RuntimeException("SUB DAMAGED invalid selection (must match selected rack). Need={$qty}, Found={$items->count()}.");
                    }

                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    foreach ($items as $it) {
                        $it->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                            'mutation_out_id'          => (int) $mutationOutId,
                        ]);
                    }

                    continue;
                }

                // =========================
                // ADD DEFECT (create per-unit)
                // =========================
                if ($type === 'add' && $condition === 'defect') {

                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    for ($i = 0; $i < $qty; $i++) {
                        $u = (array) ($defectUnits[$i] ?? []);
                        $unitRackId = (int) ($u['rack_id'] ?? $rackId);
                        $this->assertRackBelongsToWarehouse($unitRackId, $newWarehouseId);

                        ProductDefectItem::create([
                            'branch_id'       => $branchId,
                            'warehouse_id'    => $newWarehouseId,
                            'rack_id'         => $unitRackId,
                            'product_id'      => $productId,
                            'reference_id'    => (int) $adjustment->id,
                            'reference_type'  => Adjustment::class,
                            'quantity'        => 1,
                            'defect_type'     => trim((string) ($u['defect_type'] ?? '')),
                            'description'     => trim((string) ($u['description'] ?? '')) ?: ($itemNote ?: null),
                            'photo_path'      => null,
                            'created_by'      => (int) Auth::id(),
                        ]);
                    }

                    continue;
                }

                // =========================
                // ADD DAMAGED (create per-unit)
                // =========================
                if ($type === 'add' && $condition === 'damaged') {

                    $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    for ($i = 0; $i < $qty; $i++) {
                        $u = (array) ($damagedUnits[$i] ?? []);
                        $unitRackId = (int) ($u['rack_id'] ?? $rackId);
                        $this->assertRackBelongsToWarehouse($unitRackId, $newWarehouseId);

                        ProductDamagedItem::create([
                            'branch_id'          => $branchId,
                            'warehouse_id'       => $newWarehouseId,
                            'rack_id'            => $unitRackId,
                            'product_id'         => $productId,
                            'reference_id'       => (int) $adjustment->id,
                            'reference_type'     => Adjustment::class,
                            'quantity'           => 1,
                            'damage_type'        => 'damaged',
                            'reason'             => trim((string) ($u['reason'] ?? '')),
                            'photo_path'         => null,
                            'cause'              => null,
                            'responsible_user_id'=> null,
                            'resolution_status'  => 'pending',
                            'resolution_note'    => null,
                            'mutation_in_id'     => (int) $mutationInId,
                            'mutation_out_id'    => null,
                            'created_by'         => (int) Auth::id(),
                        ]);
                    }

                    continue;
                }

                throw new \RuntimeException("Unhandled adjustment line (condition={$condition}, type={$type}).");
            }
        });

        toast('Adjustment Updated!', 'info');
        return redirect()->route('adjustments.index');
    }

    public function destroy(Adjustment $adjustment)
    {
        abort_if(Gate::denies('delete_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to delete an adjustment.");
        }
        $branchId = (int) $active;

        // (optional tapi aman) pastikan milik branch aktif
        if ((int) $adjustment->branch_id !== $branchId) {
            abort(403, 'You can only delete adjustments from the active branch.');
        }

        // ✅ Guard: kalau ada unit defect/damaged dari adjustment ini yang sudah moved out → BLOCK DELETE
        $usedDefect = ProductDefectItem::query()
            ->where('reference_type', Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->whereNotNull('moved_out_at')
            ->exists();

        $usedDamaged = ProductDamagedItem::query()
            ->where('reference_type', Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->whereNotNull('moved_out_at')
            ->exists();

        if ($usedDefect || $usedDamaged) {
            toast("Cannot delete: some DEFECT/DAMAGED units from this adjustment have been moved out (already used in another transaction).", 'error');
            return redirect()->back();
        }

        DB::transaction(function () use ($adjustment) {

            // rollback mutation untuk kembalikan stock rack bucket
            $this->mutationController->rollbackByReference((string) $adjustment->reference, 'Adjustment');

            // ✅ hapus per-unit rows defect/damaged yang dibuat oleh adjustment ini (karena belum moved_out)
            ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            // hapus detail log
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // hapus header
            $adjustment->delete();
        });

        toast('Adjustment Deleted!', 'warning');
        return redirect()->route('adjustments.index');
    }

    /**
     * ✅ QUALITY RECLASS + MUTATION LOG (NET ZERO) + SAVE TO ADJUSTMENT INDEX
     * - bikin Adjustment (reference = QRC-xxx) supaya muncul di list Adjustments
     * - bikin AdjustedProduct 1 row (log tampilan)
     * - bikin ProductDefectItem / ProductDamagedItem per unit
     * - upload image per unit
     * - bikin mutation log NET-ZERO (IN lalu OUT qty sama)
     *
     * NOTE: method name jangan diganti.
     */
    public function storeQuality(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            if (!$request->ajax() && !$request->wantsJson() && !$request->expectsJson()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Please choose a specific branch first (not 'All Branch') to submit quality reclass.");
            }

            return response()->json([
                'success' => false,
                'message' => "Please choose a specific branch first (not 'All Branch') to submit quality reclass."
            ], 422);
        }
        $branchId = (int) $active;

        // NOTE:
        // - untuk GOOD -> DEFECT/DAMAGED: tetap pakai 'units' (per-unit detail)
        // - untuk DEFECT/DAMAGED -> GOOD: pakai 'picked_unit_ids' (JSON string / array), + user_note
        $request->validate([
            'date'            => 'required|date',
            'warehouse_id'    => 'required|integer|exists:warehouses,id',
            'rack_id'         => 'required|integer|exists:racks,id',
            'type'            => 'required|in:defect,damaged,defect_to_good,damaged_to_good',
            'qty'             => 'required|integer|min:1|max:500',
            'product_id'      => 'required|integer|exists:products,id',

            'user_note'       => 'nullable|string|max:1000',

            // payload optional, divalidasi manual di bawah sesuai type
            'units'            => 'nullable|array',
            'picked_unit_ids'  => 'nullable',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $rackId      = (int) $request->rack_id;
        $productId   = (int) $request->product_id;
        $type        = (string) $request->type;
        $qty         = (int) $request->qty;
        $date        = (string) $request->date;

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        // rack belongs to warehouse
        $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

        $isToGood = in_array($type, ['defect_to_good', 'damaged_to_good'], true);

        // helper decode picked ids
        $decodePickedIds = function ($raw): array {
            if ($raw === null) return [];
            if (is_array($raw)) return $raw;

            $raw = trim((string) $raw);
            if ($raw === '') return [];

            $arr = json_decode($raw, true);
            return is_array($arr) ? $arr : [];
        };

        // =========================
        // Validate payload
        // =========================
        if ($isToGood) {
            $picked = $decodePickedIds($request->picked_unit_ids);

            // normalize -> int unique
            $picked = array_values(array_unique(array_map('intval', $picked)));

            if (count($picked) !== $qty) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Please pick unit IDs per qty. Qty={$qty}, Picked=" . count($picked));
            }

            // for ToGood: units tidak dipakai, cukup user_note optional
        } else {
            if (!is_array($request->units) || count($request->units) !== $qty) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Per-unit detail must match Qty. Qty={$qty}, Units=" . (is_array($request->units) ? count($request->units) : 0));
            }

            // validate per-unit + image
            for ($i = 0; $i < $qty; $i++) {
                $unit = (array) $request->units[$i];

                if ($type === 'defect') {
                    $defType = trim((string) ($unit['defect_type'] ?? ''));
                    if ($defType === '') {
                        return redirect()->back()->withInput()->with('error', "Defect Type is required for each unit (row #" . ($i + 1) . ").");
                    }
                } else {
                    // DAMAGED (GOOD -> DAMAGED): dropdown damaged|missing (field name tetap units[i][reason])
                    $damageType = strtolower(trim((string) ($unit['reason'] ?? '')));
                    if ($damageType === '') {
                        return redirect()->back()->withInput()->with('error', "Damaged Type is required for each unit (row #" . ($i + 1) . ").");
                    }
                    if (!in_array($damageType, ['damaged', 'missing'], true)) {
                        return redirect()->back()->withInput()->with('error', "Damaged must be either 'damaged' or 'missing' (row #" . ($i + 1) . ").");
                    }
                }

                $file = $request->file("units.$i.photo");
                if ($file) {
                    $mime = strtolower((string) $file->getClientMimeType());
                    if (!str_starts_with($mime, 'image/')) {
                        return redirect()->back()->withInput()->with('error', "Photo must be an image (row #" . ($i + 1) . ").");
                    }
                    if ($file->getSize() > (5 * 1024 * 1024)) {
                        return redirect()->back()->withInput()->with('error', "Photo max size is 5MB (row #" . ($i + 1) . ").");
                    }
                }
            }
        }

        DB::transaction(function () use (
            $branchId, $warehouseId, $rackId, $productId, $type, $qty, $date, $request, $warehouse, $isToGood, $decodePickedIds
        ) {

            $product = Product::findOrFail($productId);

            $rackRow = DB::table('racks')
                ->where('id', $rackId)
                ->first(['code', 'name']);

            $rackLabel = trim(
                (string)($rackRow->code ?? '') .
                ((string)($rackRow->name ?? '') !== '' ? ' - ' . (string)($rackRow->name ?? '') : '')
            );
            if ($rackLabel === '') $rackLabel = 'Rack#' . $rackId;

            $user = Auth::user();
            $userLabel = '';
            if ($user) {
                $uname = trim((string)($user->name ?? ''));
                $uemail = trim((string)($user->email ?? ''));
                $userLabel = $uname !== '' ? $uname : ($uemail !== '' ? $uemail : ('UID ' . (int)Auth::id()));
            } else {
                $userLabel = 'UID ' . (int)Auth::id();
            }

            $productLabel = trim((string)($product->product_name ?? ''));
            $productCode  = trim((string)($product->product_code ?? ''));
            if ($productCode !== '') {
                $productLabel = $productLabel !== '' ? ($productLabel . " ({$productCode})") : $productCode;
            }
            if ($productLabel === '') $productLabel = 'PID ' . $productId;

            $warehouseLabel = trim((string)($warehouse->warehouse_name ?? ''));
            if ($warehouseLabel === '') $warehouseLabel = 'WH ' . $warehouseId;

            $noteHuman = match ($type) {
                'defect'          => 'Quality Reclass (GOOD → DEFECT)',
                'damaged'         => 'Quality Reclass (GOOD → DAMAGED)',
                'defect_to_good'  => 'Quality Reclass (DEFECT → GOOD)',
                'damaged_to_good' => 'Quality Reclass (DAMAGED → GOOD)',
                default           => "Quality Reclass ({$type})",
            };

            // template note base (yang kamu minta dipertahankan)
            $templateNote = $noteHuman
                . " | {$productLabel}"
                . " | WH: {$warehouseLabel}"
                . " | RACK: {$rackLabel}"
                . " | By: {$userLabel}";

            $userNote = trim((string) ($request->user_note ?? ''));
            $finalAdjustmentNote = $templateNote . ($userNote !== '' ? " | UserNote: {$userNote}" : '');

            $ref = $this->generateQualityReclassReference($type);

            $adjustment = Adjustment::create([
                'reference'    => $ref,
                'date'         => $date,
                'note'         => $finalAdjustmentNote,
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
                'created_by'   => (int) Auth::id(),
            ]);

            // log list (AdjustedProduct) - biar muncul di show/index
            AdjustedProduct::create([
                'adjustment_id' => $adjustment->id,
                'product_id'    => $productId,
                'rack_id'       => $rackId,
                'quantity'      => $qty,
                'type'          => 'sub',
                'note'          => $noteHuman . ' | NET-ZERO bucket log',
            ]);

            // bucket direction
            $outBucket = 'GOOD';
            $inBucket  = 'DEFECT';

            if ($type === 'damaged') {
                $outBucket = 'GOOD';
                $inBucket  = 'DAMAGED';
            }
            if ($type === 'defect_to_good') {
                $outBucket = 'DEFECT';
                $inBucket  = 'GOOD';
            }
            if ($type === 'damaged_to_good') {
                $outBucket = 'DAMAGED';
                $inBucket  = 'GOOD';
            }

            // MUTATION: net-zero (OUT then IN)
            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'Out',
                $qty,
                $ref,
                $templateNote . " | OUT | {$outBucket}",
                $date,
                $rackId
            );

            $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $qty,
                $ref,
                $templateNote . " | IN | {$inBucket}",
                $date,
                $rackId
            );

            // ==========================================================
            // A) GOOD -> DEFECT / DAMAGED (existing behavior)
            // ==========================================================
            if ($type === 'defect' || $type === 'damaged') {

                for ($i = 0; $i < $qty; $i++) {
                    $unit = (array) $request->units[$i];

                    $photoPath = null;
                    $photoFile = $request->file("units.$i.photo");
                    if ($photoFile) {
                        $photoPath = $this->storeQualityImage($photoFile, $type);
                    }

                    if ($type === 'defect') {
                        ProductDefectItem::create([
                            'branch_id'      => $branchId,
                            'warehouse_id'   => $warehouseId,
                            'rack_id'        => $rackId,
                            'product_id'     => $productId,
                            'reference_id'   => $adjustment->id,
                            'reference_type' => Adjustment::class,
                            'quantity'       => 1,
                            'defect_type'    => trim((string) ($unit['defect_type'] ?? '')),
                            'description'    => trim((string) ($unit['description'] ?? '')) ?: null,
                            'photo_path'     => $photoPath,
                            'created_by'     => (int) Auth::id(),
                        ]);
                    } else {
                        $damageType = strtolower(trim((string) ($unit['reason'] ?? 'damaged')));
                        if (!in_array($damageType, ['damaged', 'missing'], true)) {
                            $damageType = 'damaged';
                        }

                        $desc = trim((string) ($unit['description'] ?? ''));
                        $desc = $desc !== '' ? $desc : null;

                        ProductDamagedItem::create([
                            'branch_id'       => $branchId,
                            'warehouse_id'    => $warehouseId,
                            'rack_id'         => $rackId,
                            'product_id'      => $productId,
                            'reference_id'    => $adjustment->id,
                            'reference_type'  => Adjustment::class,
                            'quantity'        => 1,

                            'damage_type'     => $damageType,
                            'reason'          => $desc,

                            'photo_path'      => $photoPath,
                            'created_by'      => (int) Auth::id(),
                            'mutation_in_id'  => (int) $mutationInId,
                            'mutation_out_id' => null,
                        ]);
                    }
                }

                return;
            }

            // ==========================================================
            // B) DEFECT -> GOOD (PICK IDs + SOFT DELETE / MOVE OUT)
            // ==========================================================
            if ($type === 'defect_to_good') {

                $picked = $decodePickedIds($request->picked_unit_ids);
                $picked = array_values(array_unique(array_map('intval', $picked)));

                $items = ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $picked)
                    ->lockForUpdate()
                    ->get(['id']);

                if ($items->count() !== $qty) {
                    throw new \RuntimeException("Invalid DEFECT selection. Need={$qty}, Found={$items->count()} (must match rack & still available).");
                }

                foreach ($items as $it) {
                    $it->update([
                        'moved_out_at'             => now(),
                        'moved_out_by'             => (int) Auth::id(),
                        'moved_out_reference_type' => Adjustment::class,
                        'moved_out_reference_id'   => (int) $adjustment->id,
                    ]);
                }

                return;
            }

            // ==========================================================
            // C) DAMAGED -> GOOD (PICK IDs + SOFT DELETE / MOVE OUT)
            // ==========================================================
            if ($type === 'damaged_to_good') {

                $picked = $decodePickedIds($request->picked_unit_ids);
                $picked = array_values(array_unique(array_map('intval', $picked)));

                $items = ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $picked)
                    ->lockForUpdate()
                    ->get(['id']);

                if ($items->count() !== $qty) {
                    throw new \RuntimeException("Invalid DAMAGED selection. Need={$qty}, Found={$items->count()} (must match rack & still available).");
                }

                foreach ($items as $it) {
                    $it->update([
                        'moved_out_at'             => now(),
                        'moved_out_by'             => (int) Auth::id(),
                        'moved_out_reference_type' => Adjustment::class,
                        'moved_out_reference_id'   => (int) $adjustment->id,
                        'mutation_out_id'          => (int) $mutationOutId,
                    ]);
                }

                return;
            }

            throw new \RuntimeException("Unhandled quality type: {$type}");
        });

        toast('Quality reclass saved successfully.', 'success');

        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Quality reclass saved successfully.',
            ]);
        }

        return redirect()->route('adjustments.index');
    }

    private function generateQualityReclassReference(string $type): string
    {
        $t = strtoupper(substr($type, 0, 3)); // DEF / DAM
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return 'QRC-' . $t . '-' . now()->format('Ymd-His') . '-' . $rand;
    }

    private function storeQualityImage($file, string $type): string
    {
        // mapping type -> folder
        $folder = 'quality/misc';

        if (in_array($type, ['defect', 'defect_to_good'], true)) {
            $folder = 'quality/defects';
        } elseif (in_array($type, ['damaged', 'damaged_to_good'], true)) {
            $folder = 'quality/damaged';
        }

        return $file->store($folder, 'public');
    }

    public function qualityProducts(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please select a specific branch (not All Branch)."
            ], 422);
        }

        $branchId    = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');
        $rackId      = (int) $request->query('rack_id', 0); // optional

        // purpose: good | defect | damaged | total
        $purpose = strtolower((string) $request->query('purpose', 'good'));
        if (!in_array($purpose, ['good', 'defect', 'damaged', 'total'], true)) {
            $purpose = 'good';
        }

        if ($warehouseId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'warehouse_id is required.'
            ], 422);
        }

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        $q = DB::table('stock_racks')
            ->leftJoin('products', 'products.id', '=', 'stock_racks.product_id')
            ->where('stock_racks.branch_id', $branchId)
            ->where('stock_racks.warehouse_id', $warehouseId);

        if ($rackId > 0) {
            $q->where('stock_racks.rack_id', $rackId);
        }

        $rows = $q->selectRaw('
                stock_racks.product_id,
                COALESCE(SUM(stock_racks.qty_available), 0) as qty_available,
                COALESCE(SUM(stock_racks.qty_good), 0) as good_qty,
                COALESCE(SUM(stock_racks.qty_defect), 0) as defect_qty,
                COALESCE(SUM(stock_racks.qty_damaged), 0) as damaged_qty,
                MAX(products.product_code) as product_code,
                MAX(products.product_name) as product_name
            ')
            ->groupBy('stock_racks.product_id')
            ->orderByRaw('MAX(products.product_name)')
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $goodQty    = (int) ($r->good_qty ?? 0);
            $defectQty  = (int) ($r->defect_qty ?? 0);
            $damagedQty = (int) ($r->damaged_qty ?? 0);

            // ✅ total available = good + defect + damaged (atau pakai qty_available jika sudah benar)
            $totalAvailable = $goodQty + $defectQty + $damagedQty;
            $qtyAvailableCol = (int) ($r->qty_available ?? 0);
            if ($qtyAvailableCol > 0) {
                $totalAvailable = $qtyAvailableCol;
            }

            $availableQty = 0;
            $badgeText = '';

            if ($purpose === 'good') {
                $availableQty = $goodQty;
                $badgeText = 'GOOD';
            } elseif ($purpose === 'defect') {
                $availableQty = $defectQty;
                $badgeText = 'DEFECT';
            } elseif ($purpose === 'damaged') {
                $availableQty = $damagedQty;
                $badgeText = 'DAMAGED';
            } else { // total
                $availableQty = $totalAvailable;
                $badgeText = 'TOTAL';
            }

            if ($availableQty <= 0) {
                continue;
            }

            $label =
                trim(($r->product_code ?? '') . ' ' . ($r->product_name ?? '')) .
                ' (' . $badgeText . ': ' . number_format($availableQty) . ')';

            $data[] = [
                'id'            => (int) $r->product_id,
                'text'          => $label,
                'available_qty' => $availableQty,

                // extra info
                'total_available' => $totalAvailable,
                'good_qty'      => $goodQty,
                'defect_qty'    => $defectQty,
                'damaged_qty'   => $damagedQty,
            ];
        }

        return response()->json([
            'success'      => true,
            'purpose'      => $purpose,
            'warehouse_id' => $warehouseId,
            'rack_id'      => $rackId > 0 ? $rackId : null,
            'data'         => $data,
        ]);
    }

    public function racks(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please select a specific branch (not All Branch)."
            ], 422);
        }

        $branchId = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');

        if ($warehouseId <= 0) {
            return response()->json([
                'success' => false,
                'message' => "warehouse_id is required."
            ], 422);
        }

        $wh = Warehouse::findOrFail($warehouseId);
        if ((int)$wh->branch_id !== $branchId) {
            return response()->json([
                'success' => false,
                'message' => "Warehouse must belong to active branch."
            ], 403);
        }

        $rows = DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $data = [];
        foreach ($rows as $r) {
            $label = trim((string)($r->code ?? '')) . ' - ' . trim((string)($r->name ?? ''));
            $data[] = [
                'id' => (int)$r->id,
                'label' => trim($label, ' -'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function assertRackBelongsToWarehouse(int $rackId, int $warehouseId): void
    {
        $ok = DB::table('racks')
            ->where('id', $rackId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (!$ok) {
            abort(422, "Invalid rack: rack_id={$rackId} does not belong to warehouse_id={$warehouseId}.");
        }
    }
}
