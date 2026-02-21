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
            'activeBranchId',
            'racksByWarehouse'
        ));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || (int)$active <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('message', 'Please select an active branch (not ALL) before creating adjustment.');
        }

        // hanya support ADD dulu sesuai UI
        $adjType = $request->input('adjustment_type', 'add');
        if ($adjType !== 'add') {
            return redirect()
                ->back()
                ->withInput()
                ->with('message', 'SUB adjustment is not enabled yet.');
        }

        $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty_good' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_defect' => ['nullable', 'integer', 'min:0'],
            'items.*.qty_damaged' => ['nullable', 'integer', 'min:0'],
        ]);

        $warehouseId = (int) $request->warehouse_id;

        // (opsional tapi disarankan) pastikan warehouse milik branch aktif
        $wh = Warehouse::query()->where('id', $warehouseId)->first();
        if (!$wh) {
            return redirect()->back()->withInput()->with('message', 'Warehouse not found.');
        }
        if ((int)$wh->branch_id !== (int)$active) {
            return redirect()
                ->back()
                ->withInput()
                ->with('message', 'Selected warehouse is not in active branch.');
        }

        $date = $request->date;
        $note = (string) ($request->note ?? '');

        try {
            DB::transaction(function () use ($request, $warehouseId, $active, $date, $note) {

                // 1) create header adjustment
                $adjustment = Adjustment::query()->create([
                    'date' => $date,
                    'reference' => 'ADJ',
                    'warehouse_id' => $warehouseId,
                    'note' => $note,
                    'user_id' => Auth::id(),
                    'branch_id' => (int) $active,
                    'type' => 'stock_add',
                ]);

                $items = $request->input('items', []);

                foreach ($items as $idx => $item) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $good      = (int) ($item['qty_good'] ?? 0);
                    $defect    = (int) ($item['qty_defect'] ?? 0);
                    $damaged   = (int) ($item['qty_damaged'] ?? 0);

                    $total = $good + $defect + $damaged;
                    if ($total <= 0) {
                        continue;
                    }

                    // 2) Validasi GOOD allocations harus sum = good (jika good>0)
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
                    }

                    // 3) Validasi per-unit rows count sesuai qty (kalau defect/damaged > 0)
                    $defects = (array) ($item['defects'] ?? []);
                    $damages = (array) ($item['damaged_items'] ?? []);

                    if ($defect > 0 && count($defects) !== $defect) {
                        throw new \RuntimeException("Row #{$idx}: Defect qty ({$defect}) not match defect detail rows (" . count($defects) . ").");
                    }
                    if ($damaged > 0 && count($damages) !== $damaged) {
                        throw new \RuntimeException("Row #{$idx}: Damaged qty ({$damaged}) not match damaged detail rows (" . count($damages) . ").");
                    }

                    // 4) create adjusted product row (detail)
                    $adjusted = AdjustedProduct::query()->create([
                        'adjustment_id' => $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'qty_good' => $good,
                        'qty_defect' => $defect,
                        'qty_damaged' => $damaged,
                        'qty' => $total,
                        'branch_id' => (int) $active,
                    ]);

                    // 5) Persist GOOD allocations
                    if ($good > 0) {
                        foreach ($goodAllocations as $gaIndex => $ga) {
                            $gaQty = (int) ($ga['qty'] ?? 0);
                            $toRackId = (int) ($ga['to_rack_id'] ?? 0);
                            if ($gaQty <= 0) continue;

                            DB::table('stock_racks')->updateOrInsert(
                                [
                                    'warehouse_id' => $warehouseId,
                                    'product_id' => $productId,
                                    'rack_id' => $toRackId,
                                    'quality_status' => 'good',
                                ],
                                [
                                    'qty' => DB::raw('qty + ' . $gaQty),
                                    'updated_at' => now(),
                                    'created_at' => now(),
                                ]
                            );
                        }
                    }

                    // 6) Persist defects per-unit
                    if ($defect > 0) {
                        foreach ($defects as $i => $d) {
                            $toRackId = (int) ($d['to_rack_id'] ?? 0);
                            $defectType = (string) ($d['defect_type'] ?? '');
                            $desc = (string) ($d['defect_description'] ?? '');

                            if ($toRackId <= 0 || trim($defectType) === '') {
                                throw new \RuntimeException("Row #{$idx}: defect detail #" . ($i + 1) . " rack/type is required.");
                            }

                            $photoPath = null;
                            if ($request->hasFile("items.$idx.defects.$i.photo")) {
                                $photo = $request->file("items.$idx.defects.$i.photo");
                                $photoPath = $photo->store('adjustments/defects', 'public');
                            }

                            ProductDefectItem::query()->create([
                                'adjusted_product_id' => $adjusted->id,
                                'product_id' => $productId,
                                'warehouse_id' => $warehouseId,
                                'to_rack_id' => $toRackId,
                                'defect_type' => $defectType,
                                'description' => trim($desc) !== '' ? $desc : null,
                                'photo_path' => $photoPath,
                                'qty' => 1,
                                'branch_id' => (int) $active,
                            ]);

                            DB::table('stock_racks')->updateOrInsert(
                                [
                                    'warehouse_id' => $warehouseId,
                                    'product_id' => $productId,
                                    'rack_id' => $toRackId,
                                    'quality_status' => 'defect',
                                ],
                                [
                                    'qty' => DB::raw('qty + 1'),
                                    'updated_at' => now(),
                                    'created_at' => now(),
                                ]
                            );
                        }
                    }

                    // 7) Persist damaged per-unit  ✅ FIX DISINI
                    if ($damaged > 0) {
                        foreach ($damages as $i => $d) {
                            $toRackId = (int) ($d['to_rack_id'] ?? 0);

                            // ✅ type missing/damaged
                            $damageType = strtolower(trim((string) ($d['damaged_type'] ?? 'damaged')));
                            if (!in_array($damageType, ['damaged', 'missing'], true)) {
                                $damageType = 'damaged';
                            }

                            // ✅ description -> masuk ke reason
                            $desc = trim((string) ($d['damage_description'] ?? ''));

                            if ($toRackId <= 0 || $desc === '') {
                                throw new \RuntimeException("Row #{$idx}: damaged detail #" . ($i + 1) . " rack/description is required.");
                            }

                            $photoPath = null;
                            if ($request->hasFile("items.$idx.damaged_items.$i.photo")) {
                                $photo = $request->file("items.$idx.damaged_items.$i.photo");
                                $photoPath = $photo->store('adjustments/damaged', 'public');
                            }

                            ProductDamagedItem::query()->create([
                                'adjusted_product_id' => $adjusted->id,
                                'product_id' => $productId,
                                'warehouse_id' => $warehouseId,
                                'to_rack_id' => $toRackId,

                                // ✅ damage_type = missing/damaged
                                'damage_type' => $damageType,

                                // ✅ reason = free text description dari form
                                'reason' => $desc,

                                'photo_path' => $photoPath,
                                'qty' => 1,
                                'branch_id' => (int) $active,
                            ]);

                            DB::table('stock_racks')->updateOrInsert(
                                [
                                    'warehouse_id' => $warehouseId,
                                    'product_id' => $productId,
                                    'rack_id' => $toRackId,
                                    'quality_status' => 'damaged',
                                ],
                                [
                                    'qty' => DB::raw('qty + 1'),
                                    'updated_at' => now(),
                                    'created_at' => now(),
                                ]
                            );
                        }
                    }

                    // 8) create mutation
                    $this->mutationController->createFromAdjustmentAdd($adjustment, $adjusted);
                }
            });

            return redirect()
                ->route('adjustments.index')
                ->with('success', 'Adjustment created successfully.');

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
                ->where('rack_id', $rackId)                 // ✅ ENFORCE RACK
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

        // damaged
        $rows = ProductDamagedItem::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)                     // ✅ ENFORCE RACK
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->orderBy('id', 'asc')
            ->limit(1000)
            ->get(['id', 'rack_id', 'reason']);

        $data = $rows->map(function ($r) use ($rackLabelMap) {
            $rackText = $rackLabelMap[(int)$r->rack_id] ?? ('Rack#' . (int)$r->rack_id);

            $parts = [];
            $parts[] = "Rack: {$rackText}";
            $rs = trim((string) $r->reason);
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

        $request->validate([
            'date'         => 'required|date',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'rack_id'      => 'required|integer|exists:racks,id',
            'type'         => 'required|in:defect,damaged,defect_to_good,damaged_to_good',
            'qty'          => 'required|integer|min:1|max:500',
            'product_id'   => 'required|integer|exists:products,id',
            'units'        => 'required|array|min:1',
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

        // ✅ rack belongs to warehouse
        $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

        if (!is_array($request->units) || count($request->units) !== $qty) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Per-unit detail must match Qty. Qty={$qty}, Units=" . (is_array($request->units) ? count($request->units) : 0));
        }

        $isToGood = in_array($type, ['defect_to_good', 'damaged_to_good'], true);

        // validate per unit + image
        for ($i = 0; $i < $qty; $i++) {
            $unit = (array) $request->units[$i];

            if (!$isToGood) {
                if ($type === 'defect') {
                    $defType = trim((string) ($unit['defect_type'] ?? ''));
                    if ($defType === '') {
                        return redirect()->back()->withInput()->with('error', "Defect Type is required for each unit (row #" . ($i + 1) . ").");
                    }
                } else {
                    // ✅ Damaged: dropdown damaged|missing (key tetap 'reason')
                    $damageType = strtolower(trim((string) ($unit['reason'] ?? '')));
                    if ($damageType === '') {
                        return redirect()->back()->withInput()->with('error', "Damaged Type is required for each unit (row #" . ($i + 1) . ").");
                    }
                    if (!in_array($damageType, ['damaged', 'missing'], true)) {
                        return redirect()->back()->withInput()->with('error', "Damaged must be either 'damaged' or 'missing' (row #" . ($i + 1) . ").");
                    }
                    // description optional -> nanti disimpan ke reason column
                }
            } else {
                $resReason = trim((string) ($unit['resolution_reason'] ?? ''));
                if ($resReason === '') {
                    return redirect()->back()->withInput()->with('error', "Resolution Reason is required for each unit (row #" . ($i + 1) . ").");
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

        DB::transaction(function () use ($branchId, $warehouseId, $rackId, $productId, $type, $qty, $date, $request, $warehouse) {

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
                'defect' => 'Quality Reclass (GOOD → DEFECT)',
                'damaged' => 'Quality Reclass (GOOD → DAMAGED)',
                'defect_to_good' => 'Quality Reclass (DEFECT → GOOD) [DELETE]',
                'damaged_to_good' => 'Quality Reclass (DAMAGED → GOOD) [DELETE]',
                default => "Quality Reclass ({$type})",
            };

            $reasons = [];
            for ($i = 0; $i < $qty; $i++) {
                $unit = (array) $request->units[$i];
                if ($type === 'defect') {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['defect_type'] ?? ''));
                } elseif ($type === 'damaged') {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['reason'] ?? ''));
                } else {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['resolution_reason'] ?? ''));
                }
            }

            $reasonText = trim(implode(' | ', $reasons));
            if (strlen($reasonText) > 900) {
                $reasonText = substr($reasonText, 0, 900) . '...';
            }

            $ref = $this->generateQualityReclassReference($type);

            $adjustment = Adjustment::create([
                'reference'    => $ref,
                'date'         => $date,
                'note'         => $noteHuman . ($reasonText ? " | {$reasonText}" : ''),
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
                'created_by'   => (int) Auth::id(),
            ]);

            AdjustedProduct::create([
                'adjustment_id' => $adjustment->id,
                'product_id'    => $productId,
                'rack_id'       => $rackId,
                'quantity'      => $qty,
                'type'          => 'sub',
                'note'          => $noteHuman . ' | NET-ZERO bucket log',
            ]);

            $noteBase = $noteHuman
                . " | {$productLabel}"
                . " | WH: {$warehouseLabel}"
                . " | RACK: {$rackLabel}"
                . " | By: {$userLabel}";

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

            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'Out',
                $qty,
                $ref,
                $noteBase . " | OUT | {$outBucket}",
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
                $noteBase . " | IN | {$inBucket}",
                $date,
                $rackId
            );

            // ==========================================================
            // A) GOOD -> DEFECT / DAMAGED
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
                        // ✅ FIX: damage_type = dropdown, reason = description textarea
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

                            // ✅ type missing/damaged
                            'damage_type'     => $damageType,

                            // ✅ description masuk ke reason
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
            // B) DEFECT -> GOOD (DELETE FIFO)
            // ==========================================================
            if ($type === 'defect_to_good') {

                $ids = ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->limit($qty)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->toArray();

                if (count($ids) < $qty) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', "Not enough DEFECT units in this rack to reclass to GOOD. Available=" . count($ids) . ", Needed={$qty}.")
                    );
                }

                ProductDefectItem::query()
                    ->whereIn('id', $ids)
                    ->delete();

                return;
            }

            // ==========================================================
            // C) DAMAGED -> GOOD (DELETE FIFO)
            // ==========================================================
            if ($type === 'damaged_to_good') {

                $ids = ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->limit($qty)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->toArray();

                if (count($ids) < $qty) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', "Not enough DAMAGED units in this rack to reclass to GOOD. Available=" . count($ids) . ", Needed={$qty}.")
                    );
                }

                ProductDamagedItem::query()
                    ->whereIn('id', $ids)
                    ->delete();

                return;
            }
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
