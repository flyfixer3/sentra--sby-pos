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
            ], 422)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $branchId    = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');
        $productId   = (int) $request->query('product_id');

        if ($warehouseId <= 0 || $productId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'warehouse_id and product_id are required.',
            ], 422)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        // =========================================================
        // 1) racks list (dropdown/filter + label)
        // =========================================================
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

        // Untuk mapping label cepat di UI
        $rackLabelMap = [];
        foreach ($racks as $rk) {
            $rackLabelMap[(int)$rk['id']] = (string)$rk['label'];
        }

        // =========================================================
        // 2) TOTAL per rack: dari stock_racks.qty_available (truth)
        // =========================================================
        $stockTotals = DB::table('stock_racks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->get(['rack_id', 'qty_available'])
            ->mapWithKeys(function ($r) {
                return [(int)$r->rack_id => (int)($r->qty_available ?? 0)];
            })
            ->toArray();

        // =========================================================
        // 3) DEFECT per rack: hitung dari product_defect_items
        //    hanya yang belum moved_out (moved_out_at IS NULL)
        // =========================================================
        $defectCounts = DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->selectRaw('rack_id, COUNT(*) as cnt')
            ->groupBy('rack_id')
            ->get()
            ->mapWithKeys(function ($r) {
                return [(int)$r->rack_id => (int)($r->cnt ?? 0)];
            })
            ->toArray();

        // =========================================================
        // 4) DAMAGED per rack: hitung dari product_damaged_items
        //    hanya yang belum moved_out (moved_out_at IS NULL)
        // =========================================================
        $damagedCounts = DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->selectRaw('rack_id, COUNT(*) as cnt')
            ->groupBy('rack_id')
            ->get()
            ->mapWithKeys(function ($r) {
                return [(int)$r->rack_id => (int)($r->cnt ?? 0)];
            })
            ->toArray();

        // =========================================================
        // 5) Build stock_by_rack (SYNC dengan Inventory logic)
        //    total = qty_available
        //    good  = total - defect - damaged (min 0)
        // =========================================================
        $allRackIds = [];

        // Gabungkan semua sumber rack_id (racks master + stock totals + defect + damaged)
        foreach (array_keys($rackLabelMap) as $rid) $allRackIds[(int)$rid] = true;
        foreach (array_keys($stockTotals) as $rid) $allRackIds[(int)$rid] = true;
        foreach (array_keys($defectCounts) as $rid) $allRackIds[(int)$rid] = true;
        foreach (array_keys($damagedCounts) as $rid) $allRackIds[(int)$rid] = true;

        $stockByRack = [];
        foreach (array_keys($allRackIds) as $rid) {
            $rid = (int) $rid;
            if ($rid <= 0) continue;

            $total  = (int) ($stockTotals[$rid] ?? 0);
            $defect = (int) ($defectCounts[$rid] ?? 0);
            $dam    = (int) ($damagedCounts[$rid] ?? 0);

            $good = $total - $defect - $dam;
            if ($good < 0) $good = 0;

            $stockByRack[$rid] = [
                'rack_id' => $rid,
                'total'   => $total,
                'good'    => $good,
                'defect'  => $defect,
                'damaged' => $dam,
                'rack_label' => $rackLabelMap[$rid] ?? ('Rack #' . $rid),
            ];
        }

        // =========================================================
        // 6) defect units list (untuk checkbox pick 1pc per ID)
        // =========================================================
        $defects = ProductDefectItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('rack_id')
            ->orderBy('id')
            ->get(['id', 'rack_id', 'defect_type', 'description'])
            ->map(function ($d) use ($rackLabelMap) {
                return [
                    'id'          => (int) $d->id,
                    'rack_id'     => (int) $d->rack_id,
                    'rack_label'  => $rackLabelMap[(int)$d->rack_id] ?? ('Rack #' . (int)$d->rack_id),
                    'defect_type' => (string) ($d->defect_type ?? ''),
                    'description' => (string) ($d->description ?? ''),
                ];
            })
            ->values();

        // =========================================================
        // 7) damaged units list (untuk checkbox pick 1pc per ID)
        // =========================================================
        $damaged = ProductDamagedItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('rack_id')
            ->orderBy('id')
            ->get(['id', 'rack_id', 'damage_type', 'reason'])
            ->map(function ($d) use ($rackLabelMap) {
                return [
                    'id'          => (int) $d->id,
                    'rack_id'     => (int) $d->rack_id,
                    'rack_label'  => $rackLabelMap[(int)$d->rack_id] ?? ('Rack #' . (int)$d->rack_id),
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

                // per unit list
                'defect_units'  => $defects,
                'damaged_units' => $damaged,
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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
        // 1) Read adj type (normalize + map aliases)
        // =========================================================
        $rawAdjType = (string) $request->input('adjustment_type', 'add');
        $adjType = strtolower(trim($rawAdjType));

        $adjType = match ($adjType) {
            'add', 'stock_add' => 'add',
            'sub', 'stock_sub' => 'sub',
            default => $adjType,
        };

        if (!in_array($adjType, ['add', 'sub'], true)) {
            return redirect()->back()->withInput()->with('message', 'Invalid adjustment type.');
        }

        // =========================================================
        // 2) Normalize items (support JSON string + camelCase)
        // =========================================================
        $rawItems = $request->input('items', null);

        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                $request->merge(['items' => $decoded]);
            }
        }

        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $i => $it) {
                if (!is_array($it)) continue;

                if (!isset($it['product_id']) && isset($it['productId'])) $it['product_id'] = $it['productId'];
                if (!isset($it['warehouse_id']) && isset($it['warehouseId'])) $it['warehouse_id'] = $it['warehouseId'];
                if (!isset($it['rack_id']) && isset($it['rackId'])) $it['rack_id'] = $it['rackId'];

                if (isset($it['condition'])) {
                    $it['condition'] = strtolower(trim((string) $it['condition']));
                }

                $items[$i] = $it;
            }
            $request->merge(['items' => $items]);
        }

        // =========================================================
        // 3) Base validation
        // =========================================================
        $rules = [
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
        ];

        // ADD needs header warehouse_id
        if ($adjType === 'add') {
            $rules['warehouse_id'] = ['required', 'integer', 'exists:warehouses,id'];
            $rules['items.*.qty_good']    = ['nullable', 'integer', 'min:0'];
            $rules['items.*.qty_defect']  = ['nullable', 'integer', 'min:0'];
            $rules['items.*.qty_damaged'] = ['nullable', 'integer', 'min:0'];
        }

        // SUB (NEW FIX): follow UI payload (good_allocations + selected IDs)
        // NOTE: we DO NOT require items.*.warehouse_id/rack_id/condition anymore,
        // because SUB can span multiple warehouses/racks per product.
        if ($adjType === 'sub') {
            $rules['items.*.qty']  = ['required', 'integer', 'min:1'];
            $rules['items.*.note'] = ['required', 'string', 'min:1', 'max:1000'];

            // optional arrays (will be validated manually)
            $rules['items.*.good_allocations'] = ['nullable', 'array'];
            $rules['items.*.selected_defect_ids'] = ['nullable', 'array'];
            $rules['items.*.selected_damaged_ids'] = ['nullable', 'array'];

            // backward compat (if older frontend sends these keys)
            $rules['items.*.defect_unit_ids'] = ['nullable'];
            $rules['items.*.damaged_unit_ids'] = ['nullable'];
        }

        $messages = [
            'items.required' => 'Items is required.',
            'items.array' => 'Items must be an array.',
            'items.*.product_id.required' => 'Product is required for every line.',
            'items.*.qty.required' => 'Expected qty is required for every SUB line.',
            'items.*.note.required' => 'Note is required for every SUB line.',
        ];

        $request->validate($rules, $messages);

        $date = (string) $request->date;
        $note = html_entity_decode((string) ($request->note ?? ''), ENT_QUOTES, 'UTF-8');

        $decodeJsonArray = function ($raw): array {
            if ($raw === null) return [];
            if (is_array($raw)) return $raw;
            $raw = trim((string) $raw);
            if ($raw === '') return [];
            $arr = json_decode($raw, true);
            return is_array($arr) ? $arr : [];
        };

        $normalizeIds = function ($arr): array {
            $arr = is_array($arr) ? $arr : [];
            $arr = array_map('intval', $arr);
            $arr = array_values(array_unique(array_filter($arr, fn ($x) => $x > 0)));
            return $arr;
        };

        try {

            // =========================================================
            // ======================= ADD (UNCHANGED LOGIC) ============
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

                DB::transaction(function () use ($request, $warehouseId, $branchId, $date, $note) {

                    $adjustment = Adjustment::query()->create([
                        'date'         => $date,
                        'reference'    => 'ADJ',
                        'warehouse_id' => $warehouseId,
                        'note'         => $note,
                        'created_by'   => Auth::id(),
                        'branch_id'    => $branchId,
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
                                    'good',
                                    'summary'
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
                                    'defect',
                                    'summary'
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
                                    'damaged',
                                    'summary'
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
            // ======================= SUB (FIXED) ======================
            // =========================================================
            DB::transaction(function () use ($request, $branchId, $date, $note, $decodeJsonArray, $normalizeIds) {

                $items = (array) $request->input('items', []);
                if (empty($items)) {
                    throw new \RuntimeException("Items is required.");
                }

                // Helper: validate warehouse belongs to branch
                $assertWarehouseInBranch = function (int $warehouseId) use ($branchId): void {
                    $wh = Warehouse::findOrFail($warehouseId);
                    if ((int) $wh->branch_id !== $branchId) {
                        throw new \RuntimeException("Selected warehouse_id={$warehouseId} is not in active branch.");
                    }
                };

                // Helper: find "header warehouse" for Adjustment (table requires warehouse_id)
                $pickHeaderWarehouseId = function () use ($items, $branchId): int {
                    // 1) from first good allocation
                    foreach ($items as $it) {
                        $ga = (array) ($it['good_allocations'] ?? []);
                        if (!empty($ga)) {
                            $first = (array) ($ga[0] ?? []);
                            $wid = (int) ($first['warehouse_id'] ?? 0);
                            if ($wid > 0) return $wid;
                        }
                    }

                    // 2) fallback from first defect/damaged unit ID (read from DB)
                    foreach ($items as $it) {
                        $pid = (int) ($it['product_id'] ?? 0);

                        $defIds = $it['selected_defect_ids'] ?? ($it['defect_unit_ids'] ?? null);
                        $defIds = is_array($defIds) ? $defIds : $this->decodeJsonArrayCompat($defIds);
                        $defIds = array_values(array_unique(array_map('intval', $defIds)));

                        if (!empty($defIds)) {
                            $row = ProductDefectItem::query()
                                ->where('branch_id', $branchId)
                                ->where('product_id', $pid)
                                ->whereIn('id', $defIds)
                                ->first(['warehouse_id']);
                            if ($row && (int)$row->warehouse_id > 0) return (int)$row->warehouse_id;
                        }

                        $damIds = $it['selected_damaged_ids'] ?? ($it['damaged_unit_ids'] ?? null);
                        $damIds = is_array($damIds) ? $damIds : $this->decodeJsonArrayCompat($damIds);
                        $damIds = array_values(array_unique(array_map('intval', $damIds)));

                        if (!empty($damIds)) {
                            $row = ProductDamagedItem::query()
                                ->where('branch_id', $branchId)
                                ->where('product_id', $pid)
                                ->whereIn('id', $damIds)
                                ->first(['warehouse_id']);
                            if ($row && (int)$row->warehouse_id > 0) return (int)$row->warehouse_id;
                        }
                    }

                    // 3) final fallback: branch main warehouse
                    $wh = Warehouse::query()
                        ->where('branch_id', $branchId)
                        ->orderByDesc('is_main')
                        ->orderBy('id')
                        ->first();

                    if ($wh) return (int) $wh->id;

                    throw new \RuntimeException("No warehouse found for this branch.");
                };

                // Small helper because closure above uses $this (PHP strict), so provide a safe local decoder
                // (works for string JSON OR array OR null)
                $decodeRaw = function ($raw): array {
                    if ($raw === null) return [];
                    if (is_array($raw)) return $raw;
                    $raw = trim((string) $raw);
                    if ($raw === '') return [];
                    $arr = json_decode($raw, true);
                    return is_array($arr) ? $arr : [];
                };

                $headerWarehouseId = $pickHeaderWarehouseId();
                $assertWarehouseInBranch($headerWarehouseId);

                $adjustment = Adjustment::query()->create([
                    'date'         => $date,
                    'reference'    => 'ADJ',
                    'warehouse_id' => $headerWarehouseId,
                    'note'         => $note,
                    'created_by'   => Auth::id(),
                    'branch_id'    => $branchId,
                ]);

                $reference = (string) ($adjustment->reference ?? ('ADJ-' . (int) $adjustment->id));

                foreach ($items as $idx => $it) {

                    $productId = (int) ($it['product_id'] ?? 0);
                    $expected  = (int) ($it['qty'] ?? 0);
                    $itemNote  = html_entity_decode((string) ($it['note'] ?? ''), ENT_QUOTES, 'UTF-8');

                    if ($productId <= 0) {
                        throw new \RuntimeException("Invalid product at line #" . ($idx + 1));
                    }
                    if ($expected <= 0) {
                        throw new \RuntimeException("Expected qty is invalid at line #" . ($idx + 1));
                    }
                    if (trim($itemNote) === '') {
                        throw new \RuntimeException("Note is required at line #" . ($idx + 1));
                    }

                    Product::findOrFail($productId);

                    // ---------- A) GOOD allocations (multi wh/rack) ----------
                    $goodAlloc = (array) ($it['good_allocations'] ?? []);
                    $goodTotal = 0;

                    // group good by (warehouse_id,rack_id)
                    $goodGroups = []; // key: wid|rid => qty

                    foreach ($goodAlloc as $g) {
                        $g = (array) $g;

                        $wid = (int) ($g['warehouse_id'] ?? 0);
                        $rid = (int) ($g['from_rack_id'] ?? 0);
                        $qty = (int) ($g['qty'] ?? 0);

                        if ($qty <= 0) continue;
                        if ($wid <= 0 || $rid <= 0) {
                            throw new \RuntimeException("GOOD allocation invalid at line #" . ($idx + 1) . " (warehouse/rack required when qty > 0).");
                        }

                        $assertWarehouseInBranch($wid);
                        $this->assertRackBelongsToWarehouse($rid, $wid);

                        $goodTotal += $qty;
                        $k = $wid . '|' . $rid;
                        $goodGroups[$k] = ($goodGroups[$k] ?? 0) + $qty;
                    }

                    // ---------- B) DEFECT picked IDs ----------
                    $defRaw = $it['selected_defect_ids'] ?? ($it['defect_unit_ids'] ?? null);
                    $defIds = $normalizeIds($decodeRaw($defRaw));

                    // ---------- C) DAMAGED picked IDs ----------
                    $damRaw = $it['selected_damaged_ids'] ?? ($it['damaged_unit_ids'] ?? null);
                    $damIds = $normalizeIds($decodeRaw($damRaw));

                    $defTotal = count($defIds);
                    $damTotal = count($damIds);

                    $totalSelected = $goodTotal + $defTotal + $damTotal;
                    if ($totalSelected !== $expected) {
                        throw new \RuntimeException(
                            "Line #" . ($idx + 1) . ": total selected must match Expected. Expected={$expected}, Selected={$totalSelected} (GOOD={$goodTotal}, DEF={$defTotal}, DAM={$damTotal})"
                        );
                    }

                    // =========================================================
                    // 1) Process GOOD groups -> mutation OUT (good)
                    // =========================================================
                    foreach ($goodGroups as $k => $qty) {
                        [$wid, $rid] = explode('|', $k);
                        $wid = (int) $wid;
                        $rid = (int) $rid;
                        $qty = (int) $qty;

                        if ($qty <= 0) continue;

                        $mutationNote = trim(
                            'Adjustment Sub #' . (int) $adjustment->id
                            . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
                            . ' | GOOD | ' . trim($itemNote)
                        );

                        AdjustedProduct::query()->create([
                            'adjustment_id' => (int) $adjustment->id,
                            'product_id'    => (int) $productId,
                            'warehouse_id'  => (int) $wid,
                            'rack_id'       => (int) $rid,
                            'quantity'      => (int) $qty,
                            'type'          => 'sub',
                            'note'          => 'COND=GOOD | ' . trim($itemNote),
                        ]);

                        $this->mutationController->applyInOut(
                            $branchId,
                            $wid,
                            $productId,
                            'Out',
                            $qty,
                            $reference,
                            $mutationNote,
                            $date,
                            $rid,
                            'good',
                            'summary'
                        );
                    }

                    // =========================================================
                    // 2) Process DEFECT IDs -> moved_out then mutation OUT defect
                    // =========================================================
                    if ($defTotal > 0) {
                        $rows = ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $defIds)
                            ->lockForUpdate()
                            ->get(['id', 'warehouse_id', 'rack_id']);

                        if ($rows->count() !== $defTotal) {
                            throw new \RuntimeException("Line #" . ($idx + 1) . ": Invalid DEFECT selection (some ids not available anymore). Need={$defTotal}, Found={$rows->count()}.");
                        }

                        // group by wh/rack
                        $group = [];
                        foreach ($rows as $r) {
                            $wid = (int) $r->warehouse_id;
                            $rid = (int) $r->rack_id;

                            $assertWarehouseInBranch($wid);
                            $this->assertRackBelongsToWarehouse($rid, $wid);

                            $key = $wid . '|' . $rid;
                            $group[$key][] = (int) $r->id;
                        }

                        // moved_out first
                        ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $defIds)
                            ->update([
                                'moved_out_at'             => now(),
                                'moved_out_by'             => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id'   => (int) $adjustment->id,
                                'updated_at'               => now(),
                            ]);

                        // mutation per group
                        foreach ($group as $k => $ids) {
                            [$wid, $rid] = explode('|', $k);
                            $wid = (int) $wid;
                            $rid = (int) $rid;
                            $qty = count($ids);

                            $mutationNote = trim(
                                'Adjustment Sub #' . (int) $adjustment->id
                                . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
                                . ' | DEFECT | ' . trim($itemNote)
                            );

                            AdjustedProduct::query()->create([
                                'adjustment_id' => (int) $adjustment->id,
                                'product_id'    => (int) $productId,
                                'warehouse_id'  => (int) $wid,
                                'rack_id'       => (int) $rid,
                                'quantity'      => (int) $qty,
                                'type'          => 'sub',
                                'note'          => 'COND=DEFECT | ' . trim($itemNote),
                            ]);

                            $this->mutationController->applyInOut(
                                $branchId,
                                $wid,
                                $productId,
                                'Out',
                                $qty,
                                $reference,
                                $mutationNote,
                                $date,
                                $rid,
                                'defect',
                                'summary'
                            );
                        }
                    }

                    // =========================================================
                    // 3) Process DAMAGED IDs -> moved_out then mutation OUT damaged + set mutation_out_id
                    // =========================================================
                    if ($damTotal > 0) {
                        $rows = ProductDamagedItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $damIds)
                            ->lockForUpdate()
                            ->get(['id', 'warehouse_id', 'rack_id']);

                        if ($rows->count() !== $damTotal) {
                            throw new \RuntimeException("Line #" . ($idx + 1) . ": Invalid DAMAGED selection (some ids not available anymore). Need={$damTotal}, Found={$rows->count()}.");
                        }

                        $group = [];
                        foreach ($rows as $r) {
                            $wid = (int) $r->warehouse_id;
                            $rid = (int) $r->rack_id;

                            $assertWarehouseInBranch($wid);
                            $this->assertRackBelongsToWarehouse($rid, $wid);

                            $key = $wid . '|' . $rid;
                            $group[$key][] = (int) $r->id;
                        }

                        // moved_out first
                        ProductDamagedItem::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $damIds)
                            ->update([
                                'moved_out_at'             => now(),
                                'moved_out_by'             => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id'   => (int) $adjustment->id,
                                'updated_at'               => now(),
                            ]);

                        // mutation per group
                        foreach ($group as $k => $ids) {
                            [$wid, $rid] = explode('|', $k);
                            $wid = (int) $wid;
                            $rid = (int) $rid;
                            $qty = count($ids);

                            $mutationNote = trim(
                                'Adjustment Sub #' . (int) $adjustment->id
                                . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
                                . ' | DAMAGED | ' . trim($itemNote)
                            );

                            AdjustedProduct::query()->create([
                                'adjustment_id' => (int) $adjustment->id,
                                'product_id'    => (int) $productId,
                                'warehouse_id'  => (int) $wid,
                                'rack_id'       => (int) $rid,
                                'quantity'      => (int) $qty,
                                'type'          => 'sub',
                                'note'          => 'COND=DAMAGED | ' . trim($itemNote),
                            ]);

                            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                                $branchId,
                                $wid,
                                $productId,
                                'Out',
                                $qty,
                                $reference,
                                $mutationNote,
                                $date,
                                $rid,
                                'damaged',
                                'summary'
                            );

                            ProductDamagedItem::query()
                                ->where('branch_id', $branchId)
                                ->where('product_id', $productId)
                                ->whereIn('id', $ids)
                                ->update([
                                    'mutation_out_id' => (int) $mutationOutId,
                                    'updated_at'      => now(),
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

            $this->mutationController->rollbackByReference($reference, 'Adjustment');

            ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

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
                        $rackId,
                        'good',
                        'summary'
                    );
                    continue;
                }

                // =========================
                // ✅ SUB DEFECT (FIX ORDER)
                // =========================
                if ($type === 'sub' && $condition === 'defect') {

                    $items = ProductDefectItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $defectPickIds)
                        ->lockForUpdate()
                        ->get(['id']);

                    if ($items->count() !== $qty) {
                        throw new \RuntimeException("SUB DEFECT invalid selection (must match selected rack). Need={$qty}, Found={$items->count()}.");
                    }

                    // ✅ moved_out dulu
                    ProductDefectItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $defectPickIds)
                        ->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                            'updated_at'               => now(),
                        ]);

                    // ✅ baru mutation (sync akan baca moved_out_at terbaru)
                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId,
                        'defect',
                        'summary'
                    );

                    continue;
                }

                // =========================
                // ✅ SUB DAMAGED (FIX ORDER)
                // =========================
                if ($type === 'sub' && $condition === 'damaged') {

                    $items = ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $damagedPickIds)
                        ->lockForUpdate()
                        ->get(['id']);

                    if ($items->count() !== $qty) {
                        throw new \RuntimeException("SUB DAMAGED invalid selection (must match selected rack). Need={$qty}, Found={$items->count()}.");
                    }

                    // ✅ moved_out dulu
                    ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->whereIn('id', $damagedPickIds)
                        ->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                            'updated_at'               => now(),
                        ]);

                    // ✅ baru mutation & ambil id
                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId,
                        'damaged',
                        'summary'
                    );

                    // set mutation_out_id
                    ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $damagedPickIds)
                        ->update([
                            'mutation_out_id' => (int) $mutationOutId,
                            'updated_at'      => now(),
                        ]);

                    continue;
                }

                // =========================
                // ADD DEFECT
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
                        $rackId,
                        'defect',
                        'summary'
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
                // ADD DAMAGED
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
                        $rackId,
                        'damaged',
                        'summary'
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
        $activeBranchId = (int) session('active_branch');
        if ($activeBranchId <= 0) {
            return redirect()->back()->with('error', 'Active branch not set.');
        }

        $type = (string) $request->input('type', 'defect');

        // TO-GOOD flow (JANGAN DIUBAH LOGICNYA)
        $isToGood = in_array($type, ['defect_to_good', 'damaged_to_good'], true);
        $isClassic = !$isToGood;

        /**
         * =========================================================
         * ✅ PATCH #1 (PENTING): Normalize payload untuk Classic flow
         * Problem kamu: UI pakai "quantity" tapi backend baca "qty" -> kebaca 0 -> dipaksa jadi 1.
         * Jadi kita mapping:
         * - quantity -> qty
         * - productId -> product_id
         * - rackId -> rack_id
         * - defects/damaged_items kalau JSON string -> array
         * =========================================================
         */
        if ($isClassic) {
            $itemsPatch = $request->input('items', []);
            if (is_array($itemsPatch)) {
                foreach ($itemsPatch as $idx => $it) {
                    if (!is_array($it)) continue;

                    // product_id aliases
                    if (!isset($it['product_id']) && isset($it['productId'])) {
                        $it['product_id'] = $it['productId'];
                    }

                    // rack_id aliases
                    if (!isset($it['rack_id']) && isset($it['rackId'])) {
                        $it['rack_id'] = $it['rackId'];
                    }

                    // qty aliases (INI KUNCI BUG KAMU)
                    if (!isset($it['qty']) && isset($it['quantity'])) {
                        $it['qty'] = $it['quantity'];
                    }

                    // normalize numeric
                    if (isset($it['qty'])) {
                        $it['qty'] = (int) $it['qty'];
                    }

                    // normalize defects / damaged_items (support json-string)
                    if (isset($it['defects']) && is_string($it['defects'])) {
                        $raw = trim($it['defects']);
                        $decoded = $raw !== '' ? json_decode($raw, true) : [];
                        if (is_array($decoded)) $it['defects'] = $decoded;
                    }

                    if (isset($it['damaged_items']) && is_string($it['damaged_items'])) {
                        $raw = trim($it['damaged_items']);
                        $decoded = $raw !== '' ? json_decode($raw, true) : [];
                        if (is_array($decoded)) $it['damaged_items'] = $decoded;
                    }

                    $itemsPatch[$idx] = $it;
                }

                $request->merge(['items' => $itemsPatch]);
            }
        }

        /**
         * =========================================================
         * ✅ PATCH #2 (tetap seperti kamu): AUTO-ASSIGN rack_id untuk classic GOOD->ISSUE
         * tapi jangan “memaksa qty jadi 1” karena sekarang qty sudah ternormalisasi
         * =========================================================
         */
        if ($isClassic) {
            $warehouseIdPatch = (int) $request->input('warehouse_id', 0);

            // only attempt if warehouse is selected
            if ($warehouseIdPatch > 0) {
                $itemsPatch = $request->input('items', []);
                if (is_array($itemsPatch)) {

                    foreach ($itemsPatch as $idx => $it) {
                        if (!is_array($it)) continue;

                        $productId = (int) ($it['product_id'] ?? 0);
                        $rackId    = (int) ($it['rack_id'] ?? 0);
                        $qty       = (int) ($it['qty'] ?? 0);

                        // ❗ Jangan default qty=1 kalau qty sebenarnya belum kebaca karena key beda.
                        // Karena PATCH #1 sudah mapping quantity->qty.
                        // Di sini kalau qty <=0, biarkan ketangkep validation (biar jelas).
                        if ($qty <= 0) {
                            continue;
                        }

                        // kalau sudah ada rack_id -> skip
                        if ($productId > 0 && $rackId > 0) {
                            continue;
                        }

                        // kalau product_id kosong -> biarkan nanti ketangkep validation
                        if ($productId <= 0) {
                            continue;
                        }

                        // cari rack berdasarkan stock GOOD
                        $best = DB::table('stock_racks')
                            ->where('branch_id', $activeBranchId)
                            ->where('warehouse_id', $warehouseIdPatch)
                            ->where('product_id', $productId)
                            ->where('qty_good', '>', 0)
                            ->orderByDesc('qty_good')
                            ->orderBy('rack_id')
                            ->first(['rack_id', 'qty_good']);

                        if (!$best || (int)($best->rack_id ?? 0) <= 0) {
                            throw new \RuntimeException(
                                "Line #" . ($idx + 1) . ": Rack is required, but no GOOD stock rack found for this product in selected warehouse."
                            );
                        }

                        $autoRackId = (int) $best->rack_id;
                        $goodQtyOnRack = (int) ($best->qty_good ?? 0);

                        if ($qty > $goodQtyOnRack) {
                            throw new \RuntimeException(
                                "Line #" . ($idx + 1) . ": Qty={$qty} exceeds GOOD stock on auto rack (Rack ID={$autoRackId}, GOOD={$goodQtyOnRack}). Please choose rack manually or reduce qty."
                            );
                        }

                        // assign
                        $itemsPatch[$idx]['rack_id'] = $autoRackId;
                    }

                    $request->merge(['items' => $itemsPatch]);
                }
            }
        }

        // =========================
        // VALIDATION
        // =========================
        if ($isClassic) {
            $request->validate([
                'date' => ['required', 'date'],
                'type' => ['required', 'in:defect,damaged'],
                'warehouse_id' => ['required', 'integer', 'min:1'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer', 'min:1'],
                'items.*.rack_id' => ['required', 'integer', 'min:1'],
                'items.*.qty' => ['required', 'integer', 'min:1'],
                'user_note' => ['nullable', 'string', 'max:1000'],
                'items.*.defects' => ['nullable', 'array'],
                'items.*.damaged_items' => ['nullable', 'array'],
            ]);
        } else {
            $request->validate([
                'date' => ['required', 'date'],
                'type' => ['required', 'in:defect_to_good,damaged_to_good'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer', 'min:1'],
                'items.*.qty' => ['required', 'integer', 'min:1'],
                'items.*.selected_unit_ids' => ['required', 'array', 'min:1'],
                'items.*.selected_unit_ids.*' => ['required', 'integer', 'min:1'],
                'items.*.item_note' => ['nullable', 'string', 'max:255'],
                'items.*.user_note' => ['nullable', 'string', 'max:255'],
                'user_note' => ['nullable', 'string', 'max:255'],
            ]);
        }

        // =========================
        // DATA PREP
        // =========================
        $globalNote = html_entity_decode((string) $request->input('user_note', ''), ENT_QUOTES, 'UTF-8');
        $globalNote = trim($globalNote);

        $warehouseId = $isClassic ? (int) $request->input('warehouse_id') : 0;

        // helper: normalize detail array (support array OR json string OR null)
        $normalizeDetailArray = function ($raw): array {
            if ($raw === null) return [];
            if (is_array($raw)) return array_values($raw);

            $rawStr = trim((string) $raw);
            if ($rawStr === '') return [];

            $decoded = json_decode($rawStr, true);
            if (is_array($decoded)) return array_values($decoded);

            return [];
        };

        try {
            DB::beginTransaction();

            // =========================
            // Create adjustment header
            // =========================
            $adjustment = new \Modules\Adjustment\Entities\Adjustment();
            $adjustment->date = (string) $request->input('date');
            $adjustment->reference = $this->generateQualityReclassReference($type);
            $adjustment->branch_id = $activeBranchId;
            $adjustment->created_by = Auth::id();
            $adjustment->warehouse_id = $isClassic ? $warehouseId : null;

            if ($isClassic) {
                $adjustment->note = trim(
                    'Quality Reclass GOOD -> ' . strtoupper($type) . ($globalNote ? ' | ' . $globalNote : '')
                );
            } else {
                $adjustment->note = trim(
                    'Quality Reclass ' . strtoupper(str_replace('_to_good', '', $type)) . ' -> GOOD' . ($globalNote ? ' | ' . $globalNote : '')
                );
            }

            $adjustment->save();

            // =========================
            // (A) CLASSIC: GOOD -> DEFECT / DAMAGED
            // =========================
            if ($isClassic) {
                $wh = Warehouse::query()->where('id', $warehouseId)->first();
                if (!$wh) {
                    throw new \RuntimeException("Warehouse not found.");
                }
                if ((int) $wh->branch_id !== $activeBranchId) {
                    throw new \RuntimeException("Selected warehouse is not in active branch.");
                }

                $items = (array) $request->input('items', []);
                if (empty($items)) {
                    throw new \RuntimeException("Items is required.");
                }

                $reference = (string) $adjustment->reference;
                $date = (string) $adjustment->date;

                foreach ($items as $idx => $it) {
                    $productId = (int) ($it['product_id'] ?? 0);
                    $rackId    = (int) ($it['rack_id'] ?? 0);
                    $qty       = (int) ($it['qty'] ?? 0);

                    if ($productId <= 0 || $rackId <= 0 || $qty <= 0) {
                        throw new \RuntimeException("Invalid item at line #" . ($idx + 1));
                    }

                    $this->assertRackBelongsToWarehouse($rackId, $warehouseId);
                    Product::findOrFail($productId);

                    $itemNote = trim(
                        'Quality Reclass #' . (int) $adjustment->id . ' | GOOD->' . strtoupper($type) . ($globalNote ? ' | ' . $globalNote : '')
                    );

                    if ($type === 'defect') {
                        $defects = $normalizeDetailArray($it['defects'] ?? null);

                        if (count($defects) !== $qty) {
                            throw new \RuntimeException(
                                "Line #" . ($idx + 1) . ": Defect detail rows must match Qty. Qty={$qty}, Details=" . count($defects)
                            );
                        }

                        for ($i = 0; $i < $qty; $i++) {
                            $d = (array) ($defects[$i] ?? []);
                            $defectType = trim((string) ($d['defect_type'] ?? ''));
                            if ($defectType === '') {
                                throw new \RuntimeException(
                                    "Line #" . ($idx + 1) . ": defect_type is required for unit #" . ($i + 1)
                                );
                            }
                        }

                        // OUT GOOD dulu
                        $this->mutationController->applyInOut(
                            $activeBranchId,
                            $warehouseId,
                            $productId,
                            'Out',
                            $qty,
                            $reference,
                            $itemNote,
                            $date,
                            $rackId,
                            'good',
                            'summary'
                        );

                        // Create per-unit rows
                        for ($i = 0; $i < $qty; $i++) {
                            $d = (array) ($defects[$i] ?? []);
                            $defectType = trim((string) ($d['defect_type'] ?? ''));
                            $desc       = trim((string) ($d['description'] ?? ''));

                            $photoPath = null;
                            if ($request->hasFile("items.$idx.defects.$i.photo")) {
                                $photoPath = $this->storeQualityImage($request->file("items.$idx.defects.$i.photo"), $type);
                            }

                            ProductDefectItem::query()->create([
                                'branch_id'      => $activeBranchId,
                                'warehouse_id'   => $warehouseId,
                                'rack_id'        => $rackId,
                                'product_id'     => $productId,
                                'reference_id'   => (int) $adjustment->id,
                                'reference_type' => Adjustment::class,
                                'quantity'       => 1,
                                'defect_type'    => $defectType,
                                'description'    => $desc !== '' ? $desc : null,
                                'photo_path'     => $photoPath,
                                'created_by'     => (int) Auth::id(),
                            ]);
                        }

                        // IN DEFECT (net-zero)
                        $this->mutationController->applyInOut(
                            $activeBranchId,
                            $warehouseId,
                            $productId,
                            'In',
                            $qty,
                            $reference,
                            $itemNote,
                            $date,
                            $rackId,
                            'defect',
                            'summary'
                        );

                    } else {
                        $damagedItems = $normalizeDetailArray($it['damaged_items'] ?? null);

                        if (count($damagedItems) !== $qty) {
                            throw new \RuntimeException(
                                "Line #" . ($idx + 1) . ": Damaged detail rows must match Qty. Qty={$qty}, Details=" . count($damagedItems)
                            );
                        }

                        for ($i = 0; $i < $qty; $i++) {
                            $d = (array) ($damagedItems[$i] ?? []);
                            $reason = trim((string) ($d['reason'] ?? ''));
                            if ($reason === '') {
                                throw new \RuntimeException(
                                    "Line #" . ($idx + 1) . ": damaged reason is required for unit #" . ($i + 1)
                                );
                            }
                        }

                        // OUT GOOD dulu
                        $this->mutationController->applyInOut(
                            $activeBranchId,
                            $warehouseId,
                            $productId,
                            'Out',
                            $qty,
                            $reference,
                            $itemNote,
                            $date,
                            $rackId,
                            'good',
                            'summary'
                        );

                        // IN damaged dulu -> ambil mutation id untuk mutation_in_id
                        $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                            $activeBranchId,
                            $warehouseId,
                            $productId,
                            'In',
                            $qty,
                            $reference,
                            $itemNote,
                            $date,
                            $rackId,
                            'damaged',
                            'summary'
                        );

                        // Create per-unit rows
                        for ($i = 0; $i < $qty; $i++) {
                            $d = (array) ($damagedItems[$i] ?? []);
                            $damageType = strtolower(trim((string) ($d['damage_type'] ?? 'damaged')));
                            if (!in_array($damageType, ['damaged', 'missing'], true)) $damageType = 'damaged';

                            $reason = trim((string) ($d['reason'] ?? ''));
                            $desc   = trim((string) ($d['description'] ?? ''));

                            $photoPath = null;
                            if ($request->hasFile("items.$idx.damaged_items.$i.photo")) {
                                $photoPath = $this->storeQualityImage($request->file("items.$idx.damaged_items.$i.photo"), $type);
                            }

                            ProductDamagedItem::query()->create([
                                'branch_id'           => $activeBranchId,
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
                                'resolution_note'     => $desc !== '' ? $desc : null,
                                'mutation_in_id'      => (int) $mutationInId,
                                'mutation_out_id'     => null,
                                'created_by'          => (int) Auth::id(),
                            ]);
                        }
                    }

                    // log ke AdjustedProduct (1 row per item)
                    $noteQrc = 'QRC GOOD->' . strtoupper($type) . ($globalNote ? ' | ' . $globalNote : '');
                    if (mb_strlen($noteQrc) > 255) $noteQrc = mb_substr($noteQrc, 0, 255);

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id'    => (int) $productId,
                        'warehouse_id'  => (int) $warehouseId,
                        'rack_id'       => (int) $rackId,
                        'quantity'      => (int) $qty,
                        'type'          => 'add',
                        'note'          => $noteQrc,
                    ]);
                }

                DB::commit();
                return redirect()->route('adjustments.index')->with('success', 'Quality reclass saved.');
            }

            // =========================
            // (B) TO-GOOD: DEFECT/DAMAGED -> GOOD (BIARKAN PUNYA KAMU)
            // =========================
            $items = (array) $request->input('items', []);
            $fromCondition = ($type === 'damaged_to_good') ? 'damaged' : 'defect';

            if (empty($items)) {
                throw new \RuntimeException("Items is required.");
            }

            $buildAdjustedNote = function (string $fromCond, string $toCond, string $itemNote): string {
                $itemNote = trim($itemNote);
                $base = 'QRC ' . strtoupper($fromCond) . '->' . strtoupper($toCond);
                if ($itemNote !== '') $base .= ' | ' . $itemNote;
                if (mb_strlen($base) > 255) $base = mb_substr($base, 0, 255);
                return $base;
            };

            $headerWarehouseId = null;

            foreach ($items as $rowIdx => $row) {
                if (!is_array($row)) continue;

                $productId = (int) ($row['product_id'] ?? 0);
                $expectedQty = (int) ($row['qty'] ?? 0);
                $pickedIds = $row['selected_unit_ids'] ?? [];

                $itemNote = '';
                if (isset($row['item_note'])) {
                    $itemNote = (string) $row['item_note'];
                } elseif (isset($row['user_note'])) {
                    $itemNote = (string) $row['user_note'];
                } else {
                    $itemNote = $globalNote;
                }

                $itemNote = html_entity_decode($itemNote, ENT_QUOTES, 'UTF-8');
                $itemNote = trim($itemNote);

                if ($productId <= 0 || $expectedQty <= 0 || !is_array($pickedIds)) {
                    throw new \RuntimeException("Invalid items row at index #" . ($rowIdx + 1));
                }
                if ($itemNote === '') {
                    throw new \RuntimeException("Item note is required at line #" . ($rowIdx + 1));
                }

                $pickedIds = array_values(array_unique(array_map('intval', $pickedIds)));
                $pickedIds = array_values(array_filter($pickedIds, fn ($x) => $x > 0));

                if (count($pickedIds) !== $expectedQty) {
                    throw new \RuntimeException(
                        "Qty mismatch at line #" . ($rowIdx + 1) . ". Expected={$expectedQty}, Picked=" . count($pickedIds)
                    );
                }

                if ($fromCondition === 'defect') {
                    $units = ProductDefectItem::query()
                        ->where('branch_id', $activeBranchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $pickedIds)
                        ->whereNull('moved_out_at')
                        ->lockForUpdate()
                        ->get(['id', 'warehouse_id', 'rack_id']);
                } else {
                    $units = ProductDamagedItem::query()
                        ->where('branch_id', $activeBranchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $pickedIds)
                        ->whereNull('moved_out_at')
                        ->lockForUpdate()
                        ->get(['id', 'warehouse_id', 'rack_id']);
                }

                if ($units->count() !== count($pickedIds)) {
                    throw new \RuntimeException("Some picked IDs are invalid / already moved out at line #" . ($rowIdx + 1));
                }

                $groups = [];
                foreach ($units as $u) {
                    $wid = (int) $u->warehouse_id;
                    $rid = (int) $u->rack_id;
                    $key = $wid . '|' . $rid;

                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'warehouse_id' => $wid,
                            'rack_id' => $rid,
                            'ids' => [],
                        ];
                    }

                    $groups[$key]['ids'][] = (int) $u->id;

                    if ($headerWarehouseId === null && $wid > 0) {
                        $headerWarehouseId = $wid;
                    }
                }

                if ($adjustment->warehouse_id === null && $headerWarehouseId !== null) {
                    $adjustment->warehouse_id = (int) $headerWarehouseId;
                    $adjustment->save();
                }

                $reference = (string) $adjustment->reference;
                $now = now();

                foreach ($groups as $g) {
                    $wid = (int) $g['warehouse_id'];
                    $rid = (int) $g['rack_id'];
                    $groupIds = (array) $g['ids'];
                    $qty = count($groupIds);
                    if ($qty <= 0) continue;

                    $this->assertRackBelongsToWarehouse($rid, $wid);

                    $mutationNote = trim(
                        'Quality Reclass #' . (int) $adjustment->id . ' | ' . strtoupper($fromCondition) . '->GOOD' . ' | ' . $itemNote
                    );

                    // moved_out dulu
                    if ($fromCondition === 'defect') {
                        ProductDefectItem::query()
                            ->where('branch_id', $activeBranchId)
                            ->where('product_id', $productId)
                            ->whereIn('id', $groupIds)
                            ->whereNull('moved_out_at')
                            ->update([
                                'moved_out_at' => $now,
                                'moved_out_by' => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id' => (int) $adjustment->id,
                                'updated_at' => $now,
                            ]);
                    } else {
                        ProductDamagedItem::query()
                            ->where('branch_id', $activeBranchId)
                            ->where('product_id', $productId)
                            ->whereIn('id', $groupIds)
                            ->whereNull('moved_out_at')
                            ->update([
                                'moved_out_at' => $now,
                                'moved_out_by' => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id' => (int) $adjustment->id,
                                'updated_at' => $now,
                            ]);
                    }

                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                        $activeBranchId,
                        $wid,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $adjustment->date,
                        $rid,
                        $fromCondition,
                        'summary'
                    );

                    $this->mutationController->applyInOutAndGetMutationId(
                        $activeBranchId,
                        $wid,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $adjustment->date,
                        $rid,
                        'good',
                        'summary'
                    );

                    $noteQrc = $buildAdjustedNote($fromCondition, 'good', $itemNote);

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => (int) $productId,
                        'warehouse_id' => (int) $wid,
                        'rack_id' => (int) $rid,
                        'quantity' => (int) $qty,
                        'type' => 'add',
                        'note' => $noteQrc,
                    ]);

                    if ($fromCondition === 'damaged' && (int) $mutationOutId > 0) {
                        ProductDamagedItem::query()
                            ->where('branch_id', $activeBranchId)
                            ->where('product_id', $productId)
                            ->whereIn('id', $groupIds)
                            ->update([
                                'mutation_out_id' => (int) $mutationOutId,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('adjustments.index')->with('success', 'Quality Issue → Good saved.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function qualityToGoodPicker(Request $request)
    {
        $activeBranchId = (int) session('active_branch');
        if ($activeBranchId <= 0) {
            return response()->json(['success' => false, 'message' => 'Active branch not set.'], 422);
        }

        $warehouseId = (int) $request->query('warehouse_id', 0);
        $productId   = (int) $request->query('product_id', 0);
        $condition   = (string) $request->query('condition', 'defect'); // defect | damaged

        if ($warehouseId <= 0 || $productId <= 0) {
            return response()->json(['success' => false, 'message' => 'warehouse_id and product_id are required.'], 422);
        }

        if (!in_array($condition, ['defect', 'damaged'], true)) {
            $condition = 'defect';
        }

        try {
            // racks list (untuk filter & grouping label)
            // FIX: racks table pakai "code" dan "name", bukan "rack_name"
            $racks = \Modules\Inventory\Entities\Rack::query()
                ->where('warehouse_id', $warehouseId)
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(function ($r) {
                    $code = trim((string) ($r->code ?? ''));
                    $name = trim((string) ($r->name ?? ''));
                    $label = trim($code . ' - ' . $name, ' -');

                    return [
                        'id'    => (int) $r->id,
                        'label' => $label !== '' ? $label : ('Rack #' . (int) $r->id),
                    ];
                })
                ->values()
                ->all();

            // units list
            if ($condition === 'defect') {
                $units = \Modules\Product\Entities\ProductDefectItem::query()
                    ->where('branch_id', $activeBranchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->orderBy('rack_id')
                    ->orderBy('id')
                    ->get(['id', 'rack_id', 'defect_type', 'description'])
                    ->map(fn($u) => [
                        'id'      => (int) $u->id,
                        'rack_id' => (int) $u->rack_id,
                        'info'    => (string) ($u->defect_type ?? ''),
                    ])
                    ->values()
                    ->all();
            } else {
                $units = \Modules\Product\Entities\ProductDamagedItem::query()
                    ->where('branch_id', $activeBranchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->orderBy('rack_id')
                    ->orderBy('id')
                    ->get(['id', 'rack_id', 'damage_type', 'reason'])
                    ->map(fn($u) => [
                        'id'      => (int) $u->id,
                        'rack_id' => (int) $u->rack_id,
                        'info'    => (string) ($u->damage_type ?? 'damaged'),
                    ])
                    ->values()
                    ->all();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'racks' => $racks,
                    'units' => $units,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function qualityToGoodPickerData(Request $request)
    {
        try {
            $branchId    = (int) session('active_branch');
            $productId   = (int) $request->get('product_id');
            $warehouseId = (int) $request->get('warehouse_id');
            $condition   = strtolower(trim((string) $request->get('condition', 'defect')));

            if ($branchId <= 0) {
                return response()->json(['success' => false, 'message' => 'Active branch not set.'], 400);
            }
            if ($productId <= 0) {
                return response()->json(['success' => false, 'message' => 'product_id is required.'], 400);
            }
            if (!in_array($condition, ['defect', 'damaged'], true)) {
                return response()->json(['success' => false, 'message' => 'condition must be defect|damaged'], 400);
            }

            // racks by warehouse
            // FIX: orderBy rack_name -> orderBy code,name
            $rackQuery = Rack::query()->where('branch_id', $branchId);

            if ($warehouseId > 0) {
                $rackQuery->where('warehouse_id', $warehouseId);
            }

            $racks = $rackQuery
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(function ($r) {
                    $code = trim((string) ($r->code ?? ''));
                    $name = trim((string) ($r->name ?? ''));
                    $label = trim($code . ' - ' . $name, ' -');

                    return [
                        'id'    => (int) $r->id,
                        'label' => $label !== '' ? $label : ('Rack#' . (int) $r->id),
                    ];
                })
                ->values()
                ->toArray();

            // units (defect / damaged)
            $units = [];

            if ($condition === 'defect') {
                $q = ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at');

                if ($warehouseId > 0) {
                    $q->where('warehouse_id', $warehouseId);
                }

                $rows = $q->orderBy('rack_id')->orderBy('id')->get();

                $units = $rows->map(function ($u) {
                    $dt = trim((string) ($u->defect_type ?? ''));
                    $ds = trim((string) ($u->description ?? ''));

                    $info = 'Type: ' . ($dt !== '' ? $dt : '-');
                    if ($ds !== '') $info .= ' | Desc: ' . $ds;

                    return [
                        'id'           => (int) $u->id,
                        'rack_id'      => (int) $u->rack_id,
                        'warehouse_id' => (int) $u->warehouse_id,
                        'info'         => $info,
                    ];
                })->values()->toArray();
            }

            if ($condition === 'damaged') {
                $q = ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at');

                if ($warehouseId > 0) {
                    $q->where('warehouse_id', $warehouseId);
                }

                $rows = $q->orderBy('rack_id')->orderBy('id')->get();

                $units = $rows->map(function ($u) {
                    $tp = trim((string) ($u->damage_type ?? 'damaged'));
                    $rs = trim((string) ($u->reason ?? ''));

                    $info = 'Type: ' . ($tp !== '' ? $tp : 'damaged');
                    if ($rs !== '') $info .= ' | Reason: ' . $rs;

                    return [
                        'id'           => (int) $u->id,
                        'rack_id'      => (int) $u->rack_id,
                        'warehouse_id' => (int) $u->warehouse_id,
                        'info'         => $info,
                    ];
                })->values()->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'racks' => $racks,
                    'units' => $units,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
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
