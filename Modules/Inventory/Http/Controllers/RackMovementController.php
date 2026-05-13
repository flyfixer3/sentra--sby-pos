<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\DefectTypeSupport;
use App\Support\BranchContext;

use Modules\Inventory\Entities\Rack;
use Modules\Inventory\Entities\RackMovement;
use Modules\Inventory\Entities\RackMovementItem;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;

use Modules\Mutation\Http\Controllers\MutationController;

class RackMovementController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('access_rack_movements'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');

        $q = RackMovement::withoutGlobalScopes()
            ->with([
                'fromWarehouse:id,warehouse_name,branch_id',
                'toWarehouse:id,warehouse_name,branch_id',
                'fromRack:id,code,name,warehouse_id',
                'toRack:id,code,name,warehouse_id',
                'branch:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('id');

        if (!$isAll) {
            $q->where('branch_id', (int) $branchIdRaw);
        } elseif ($request->filled('branch_id')) {
            $q->where('branch_id', (int) $request->branch_id);
        }

        if ($request->filled('q')) {
            $s = trim((string) $request->q);
            $q->where(function ($w) use ($s) {
                $w->where('reference', 'like', "%{$s}%")
                  ->orWhere('note', 'like', "%{$s}%");
            });
        }

        $movements = $q->paginate(20)->withQueryString();

        $branches = collect();
        if ($isAll) {
            $branches = DB::table('branches')->select('id', 'name')->orderBy('name')->get();
        }

        return view('inventory::rack-movements.index', compact('movements', 'isAll', 'branches'));
    }

    public function create()
    {
        abort_if(Gate::denies('create_rack_movements'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');

        if ($isAll) {
            toast('Please select a specific branch first. You cannot create Rack Movement in "All Branches" mode.', 'error');
            return redirect()->route('inventory.rack-movements.index');
        }

        $branchId = (int) $branchIdRaw;

        $reference = $this->generateReference();

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        // racks akan di-load via AJAX endpoint (biar UX enak)
        return view('inventory::rack-movements.create', compact('reference', 'warehouses'));
    }

    public function show(RackMovement $rackMovement)
    {
        abort_if(Gate::denies('show_rack_movements'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');

        if (!$isAll) {
            abort_unless((int) $rackMovement->branch_id === (int) $branchIdRaw, 403);
        }

        $rackMovement->load([
            'items.product:id,product_name,product_code,product_unit',
            'fromWarehouse:id,warehouse_name',
            'toWarehouse:id,warehouse_name',
            'fromRack:id,code,name',
            'toRack:id,code,name',
            'branch:id,name',
        ]);

        $defectIds = [];
        $damagedIds = [];

        foreach ($rackMovement->items as $item) {
            $condition = strtolower((string) ($item->condition ?? 'good'));
            if ($condition === 'defect') {
                $ids = $this->parseStoredUnitIds($item->defect_item_ids ?? null);
                $item->setAttribute('display_unit_ids', $ids);
                $defectIds = array_merge($defectIds, $ids);
            } elseif ($condition === 'damaged') {
                $ids = $this->parseStoredUnitIds($item->damaged_item_ids ?? null);
                $item->setAttribute('display_unit_ids', $ids);
                $damagedIds = array_merge($damagedIds, $ids);
            } else {
                $item->setAttribute('display_unit_ids', []);
            }
        }

        $defectIds = array_values(array_unique(array_filter(array_map('intval', $defectIds))));
        $damagedIds = array_values(array_unique(array_filter(array_map('intval', $damagedIds))));

        $defectUnitDetails = collect();
        if (!empty($defectIds)) {
            $defectUnitDetails = ProductDefectItem::query()
                ->whereIn('id', $defectIds)
                ->get(['id', 'product_id', 'warehouse_id', 'rack_id', 'defect_types', 'description', 'photo_path', 'created_at', 'updated_at'])
                ->mapWithKeys(function ($unit) {
                    return [(int) $unit->id => [
                        'id' => (int) $unit->id,
                        'condition' => 'DEFECT',
                        'type_reason' => DefectTypeSupport::text($unit->defect_types ?? [], '-'),
                        'description' => (string) ($unit->description ?? ''),
                        'photo_url' => $unit->photo_path ? asset('storage/' . $unit->photo_path) : null,
                        'warehouse_id' => (int) ($unit->warehouse_id ?? 0),
                        'rack_id' => (int) ($unit->rack_id ?? 0),
                    ]];
                });
        }

        $damagedUnitDetails = collect();
        if (!empty($damagedIds)) {
            $damagedUnitDetails = ProductDamagedItem::withoutGlobalScopes()
                ->whereIn('id', $damagedIds)
                ->get(['id', 'product_id', 'warehouse_id', 'rack_id', 'damage_type', 'reason', 'resolution_note', 'photo_path', 'created_at', 'updated_at'])
                ->mapWithKeys(function ($unit) {
                    $description = trim((string) ($unit->reason ?? ''));
                    if ($description === '') {
                        $description = trim((string) ($unit->resolution_note ?? ''));
                    }

                    return [(int) $unit->id => [
                        'id' => (int) $unit->id,
                        'condition' => 'DAMAGED',
                        'type_reason' => (string) ($unit->damage_type ?? '-'),
                        'description' => $description,
                        'photo_url' => $unit->photo_path ? asset('storage/' . $unit->photo_path) : null,
                        'warehouse_id' => (int) ($unit->warehouse_id ?? 0),
                        'rack_id' => (int) ($unit->rack_id ?? 0),
                    ]];
                });
        }

        return view('inventory::rack-movements.show', compact('rackMovement', 'defectUnitDetails', 'damagedUnitDetails'));
    }

    /**
     * AJAX: rack list by warehouse
     */
    public function racksByWarehouse(Request $request)
    {
        abort_if(Gate::denies('create_rack_movements'), 403);

        $warehouseId = (int) $request->get('warehouse_id');
        if ($warehouseId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid warehouse_id'], 422);
        }

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');
        if ($isAll) {
            return response()->json(['success' => false, 'message' => 'Please select a specific branch first.'], 422);
        }

        $branchId = (int) $branchIdRaw;

        $wh = Warehouse::withoutGlobalScopes()->findOrFail($warehouseId);
        abort_unless((int) $wh->branch_id === $branchId, 403);

        $racks = Rack::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('branch_id', $branchId)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $options = $racks->map(function ($r) {
            $label = trim((string) ($r->code ?? '')) !== ''
                ? (($r->code ?? '-') . ' - ' . ($r->name ?? '-'))
                : (string) ($r->name ?? '-');
            return ['id' => (int) $r->id, 'label' => $label];
        })->values();

        return response()->json([
            'success' => true,
            'racks' => $options,
        ]);
    }

    public function pickerData(Request $request)
    {
        abort_if(Gate::denies('create_rack_movements'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');
        if ($isAll) {
            return response()->json(['success' => false, 'message' => 'Please select a specific branch first.'], 422);
        }

        $branchId = (int) $branchIdRaw;
        $warehouseId = (int) $request->query('warehouse_id');
        $rackId = (int) $request->query('rack_id');
        $productId = (int) $request->query('product_id');
        $condition = strtolower(trim((string) $request->query('condition')));

        if ($warehouseId <= 0 || $rackId <= 0 || $productId <= 0 || !in_array($condition, ['defect', 'damaged'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid picker parameters.'], 422);
        }

        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($warehouseId);
        abort_unless((int) $warehouse->branch_id === $branchId, 403);

        $rack = Rack::withoutGlobalScopes()->findOrFail($rackId);
        abort_unless((int) $rack->branch_id === $branchId, 403);
        abort_unless((int) $rack->warehouse_id === $warehouseId, 422);

        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $rackLabel = trim((string) ($rack->code ?? '')) !== ''
            ? (($rack->code ?? '-') . ' - ' . ($rack->name ?? '-'))
            : (string) ($rack->name ?? ('Rack #' . $rackId));

        $stockRow = DB::table('stock_racks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)
            ->where('product_id', $productId)
            ->first();

        $stockSummary = [
            'warehouse_id' => $warehouseId,
            'warehouse_label' => (string) ($warehouse->warehouse_name ?? ('Warehouse #' . $warehouseId)),
            'rack_id' => $rackId,
            'rack_label' => $rackLabel,
            'good' => (int) ($stockRow->qty_good ?? 0),
            'defect' => (int) ($stockRow->qty_defect ?? 0),
            'damaged' => (int) ($stockRow->qty_damaged ?? 0),
        ];

        if ($condition === 'defect') {
            $items = ProductDefectItem::query()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $warehouseId)
                ->where('rack_id', $rackId)
                ->where('product_id', $productId)
                ->whereNull('moved_out_at')
                ->orderBy('id')
                ->get(['id', 'defect_types', 'description', 'photo_path'])
                ->map(function ($item) use ($product, $warehouse, $rackLabel) {
                    return [
                        'id' => (int) $item->id,
                        'product_code' => (string) ($product->product_code ?? '-'),
                        'product_name' => (string) ($product->product_name ?? '-'),
                        'condition' => 'DEFECT',
                        'quality_text' => DefectTypeSupport::text($item->defect_types ?? [], '-'),
                        'description' => (string) ($item->description ?? ''),
                        'photo_url' => $item->photo_path ? asset('storage/' . $item->photo_path) : null,
                        'warehouse' => (string) ($warehouse->warehouse_name ?? '-'),
                        'rack' => $rackLabel,
                    ];
                })
                ->values();
        } else {
            $items = ProductDamagedItem::query()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $warehouseId)
                ->where('rack_id', $rackId)
                ->where('product_id', $productId)
                ->whereNull('moved_out_at')
                ->where(function ($q) {
                    $q->whereNull('resolution_status')
                        ->orWhere('resolution_status', 'pending');
                })
                ->orderBy('id')
                ->get(['id', 'damage_type', 'reason', 'photo_path'])
                ->map(function ($item) use ($product, $warehouse, $rackLabel) {
                    return [
                        'id' => (int) $item->id,
                        'product_code' => (string) ($product->product_code ?? '-'),
                        'product_name' => (string) ($product->product_name ?? '-'),
                        'condition' => 'DAMAGED',
                        'quality_text' => (string) ($item->damage_type ?? 'damaged'),
                        'description' => (string) ($item->reason ?? ''),
                        'photo_url' => $item->photo_path ? asset('storage/' . $item->photo_path) : null,
                        'warehouse' => (string) ($warehouse->warehouse_name ?? '-'),
                        'rack' => $rackLabel,
                    ];
                })
                ->values();
        }

        return response()->json([
            'success' => true,
            'items' => $items,
            'stock_summary' => $stockSummary,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_rack_movements'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');

        if ($isAll) {
            toast('Please select a specific branch first. You cannot create Rack Movement in "All Branches" mode.', 'error');
            return redirect()->route('inventory.rack-movements.index');
        }

        $branchId = (int) $branchIdRaw;

        $request->validate([
            'reference' => 'required|string|max:255',
            'date' => 'required|date',
            'note' => 'nullable|string|max:1000',

            'from_warehouse_id' => 'required|integer',
            'from_rack_id' => 'required|integer',
            'to_warehouse_id' => 'required|integer',
            'to_rack_id' => 'required|integer',

            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer',
            'conditions' => 'required|array|min:1',
            'conditions.*' => 'required|string',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'nullable|integer|min:0',
            'defect_item_ids' => 'nullable|array',
            'defect_item_ids.*' => 'nullable|string',
            'damaged_item_ids' => 'nullable|array',
            'damaged_item_ids.*' => 'nullable|string',
        ]);

        $reference = trim((string) $request->reference);
        $date = (string) $request->date;
        $note = $request->note ? trim((string) $request->note) : null;

        $fromWarehouseId = (int) $request->from_warehouse_id;
        $toWarehouseId = (int) $request->to_warehouse_id;
        $fromRackId = (int) $request->from_rack_id;
        $toRackId = (int) $request->to_rack_id;

        if ($fromWarehouseId === $toWarehouseId && $fromRackId === $toRackId) {
            return back()->withInput()->with('message', 'From Rack dan To Rack tidak boleh sama.');
        }

        // Validasi ownership branch
        $fromWarehouse = Warehouse::withoutGlobalScopes()->findOrFail($fromWarehouseId);
        $toWarehouse = Warehouse::withoutGlobalScopes()->findOrFail($toWarehouseId);
        abort_unless((int) $fromWarehouse->branch_id === $branchId, 403);
        abort_unless((int) $toWarehouse->branch_id === $branchId, 403);

        $fromRack = Rack::withoutGlobalScopes()->findOrFail($fromRackId);
        $toRack = Rack::withoutGlobalScopes()->findOrFail($toRackId);
        abort_unless((int) $fromRack->warehouse_id === $fromWarehouseId, 422);
        abort_unless((int) $toRack->warehouse_id === $toWarehouseId, 422);
        abort_unless((int) $fromRack->branch_id === $branchId, 403);
        abort_unless((int) $toRack->branch_id === $branchId, 403);

        // Pastikan reference unik
        $existsRef = RackMovement::withoutGlobalScopes()->where('reference', $reference)->exists();
        if ($existsRef) {
            return back()->withInput()->with('message', 'Reference sudah dipakai. Silakan refresh halaman untuk generate reference baru.');
        }

        $productIds = $request->product_ids;
        $conditions = $request->conditions;
        $quantities = $request->quantities;
        $defectItemIds = $request->defect_item_ids ?? [];
        $damagedItemIds = $request->damaged_item_ids ?? [];

        if (count($productIds) !== count($conditions) || count($productIds) !== count($quantities)) {
            return back()->withInput()->with('message', 'Items tidak valid (jumlah array tidak sama).');
        }

        $decodeIds = function ($raw): array {
            if (is_array($raw)) {
                $arr = $raw;
            } else {
                $raw = trim((string) $raw);
                if ($raw === '') return [];
                $decoded = json_decode($raw, true);
                $arr = is_array($decoded) ? $decoded : explode(',', $raw);
            }

            $ids = [];
            foreach ($arr as $id) {
                $id = (int) $id;
                if ($id > 0) $ids[$id] = $id;
            }
            return array_values($ids);
        };

        // Helper: resolve stock bucket column
        $bucketCol = function (string $cond): string {
            $c = strtolower(trim($cond));
            return match ($c) {
                'good' => 'qty_good',
                'defect' => 'qty_defect',
                'damaged' => 'qty_damaged',
                default => 'qty_good',
            };
        };

        // =========================================================
        // TRANSACTION: create header+items + apply mutation
        // =========================================================
        try {
            $movement = DB::transaction(function () use (
                $branchId,
                $reference,
                $date,
                $note,
                $fromWarehouseId,
                $toWarehouseId,
                $fromRackId,
                $toRackId,
                $productIds,
                $conditions,
                $quantities,
                $defectItemIds,
                $damagedItemIds,
                $bucketCol,
                $decodeIds
            ) {
                $mv = RackMovement::withoutGlobalScopes()->create([
                    'branch_id' => $branchId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'from_rack_id' => $fromRackId,
                    'to_warehouse_id' => $toWarehouseId,
                    'to_rack_id' => $toRackId,
                    'reference' => $reference,
                    'date' => $date,
                    'note' => $note,
                    'status' => 'completed',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                // validasi qty berdasarkan stock_racks source rack.
                // GOOD tetap qty-based. DEFECT/DAMAGED wajib unit-id based.
                $group = [];
                $rowSelections = [];
                foreach ($productIds as $i => $pidRaw) {
                    $pid = (int) $pidRaw;
                    $cond = strtolower(trim((string) ($conditions[$i] ?? 'good')));
                    if (!in_array($cond, ['good', 'defect', 'damaged'], true)) {
                        throw new \RuntimeException("Condition tidak valid pada baris #" . ($i + 1) . ".");
                    }
                    $qty = (int) ($quantities[$i] ?? 0);
                    if ($pid <= 0) {
                        throw new \RuntimeException("Product tidak valid pada baris #" . ($i + 1) . ".");
                    }

                    Product::withoutGlobalScopes()->findOrFail($pid);

                    $selectedDefectIds = $decodeIds($defectItemIds[$i] ?? null);
                    $selectedDamagedIds = $decodeIds($damagedItemIds[$i] ?? null);

                    if ($cond === 'good') {
                        if ($qty <= 0) {
                            throw new \RuntimeException("Quantity GOOD wajib lebih dari 0 pada baris #" . ($i + 1) . ".");
                        }
                        $selectedDefectIds = [];
                        $selectedDamagedIds = [];
                    } elseif ($cond === 'defect') {
                        if (count($selectedDefectIds) <= 0) {
                            throw new \RuntimeException("DEFECT wajib pilih item/unit ID pada baris #" . ($i + 1) . ".");
                        }
                        $selectedDamagedIds = [];
                        $qty = count($selectedDefectIds);
                    } elseif ($cond === 'damaged') {
                        if (count($selectedDamagedIds) <= 0) {
                            throw new \RuntimeException("DAMAGED wajib pilih item/unit ID pada baris #" . ($i + 1) . ".");
                        }
                        $selectedDefectIds = [];
                        $qty = count($selectedDamagedIds);
                    }

                    $rowSelections[$i] = [
                        'quantity' => $qty,
                        'defect_ids' => $selectedDefectIds,
                        'damaged_ids' => $selectedDamagedIds,
                    ];

                    $key = $pid . ':' . $cond;
                    $group[$key] = ($group[$key] ?? 0) + $qty;
                }

                foreach ($group as $key => $qtyNeed) {
                    [$pidStr, $cond] = explode(':', $key);
                    $pid = (int) $pidStr;
                    $col = $bucketCol($cond);

                    $row = DB::table('stock_racks')
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $fromWarehouseId)
                        ->where('rack_id', $fromRackId)
                        ->where('product_id', $pid)
                        ->lockForUpdate()
                        ->first();

                    $available = (int) (($row->{$col} ?? 0));
                    if ($available < $qtyNeed) {
                        throw new \RuntimeException("Stock tidak cukup di From Rack untuk product_id={$pid} condition={$cond}. Available={$available}, Need={$qtyNeed}");
                    }
                }

                $validateUniqueIds = function (array $ids, string $label): void {
                    if (count($ids) !== count(array_unique($ids))) {
                        throw new \RuntimeException("Selected {$label} item IDs tidak boleh duplikat.");
                    }
                };

                foreach ($rowSelections as $i => $selection) {
                    $pid = (int) $productIds[$i];
                    $cond = strtolower(trim((string) ($conditions[$i] ?? 'good')));

                    if ($cond === 'defect') {
                        $ids = $selection['defect_ids'];
                        $validateUniqueIds($ids, 'DEFECT');
                        $items = ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $fromWarehouseId)
                            ->where('rack_id', $fromRackId)
                            ->where('product_id', $pid)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $ids)
                            ->lockForUpdate()
                            ->get(['id']);

                        if ($items->count() !== count($ids)) {
                            throw new \RuntimeException("DEFECT selection invalid pada baris #" . ($i + 1) . ". Pastikan item masih tersedia di source rack dan product/condition sesuai.");
                        }
                    }

                    if ($cond === 'damaged') {
                        $ids = $selection['damaged_ids'];
                        $validateUniqueIds($ids, 'DAMAGED');
                        $items = ProductDamagedItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $fromWarehouseId)
                            ->where('rack_id', $fromRackId)
                            ->where('product_id', $pid)
                            ->whereNull('moved_out_at')
                            ->where(function ($q) {
                                $q->whereNull('resolution_status')
                                    ->orWhere('resolution_status', 'pending');
                            })
                            ->whereIn('id', $ids)
                            ->lockForUpdate()
                            ->get(['id']);

                        if ($items->count() !== count($ids)) {
                            throw new \RuntimeException("DAMAGED selection invalid pada baris #" . ($i + 1) . ". Pastikan item masih pending/available di source rack dan product/condition sesuai.");
                        }
                    }
                }

                // insert items + mutations
                foreach ($productIds as $i => $pidRaw) {
                    $pid = (int) $pidRaw;
                    $cond = strtolower(trim((string) ($conditions[$i] ?? 'good')));
                    if (!in_array($cond, ['good', 'defect', 'damaged'], true)) $cond = 'good';
                    $qty = (int) ($rowSelections[$i]['quantity'] ?? ($quantities[$i] ?? 0));
                    if ($pid <= 0 || $qty <= 0) continue;

                    $selectedDefectIds = (array) ($rowSelections[$i]['defect_ids'] ?? []);
                    $selectedDamagedIds = (array) ($rowSelections[$i]['damaged_ids'] ?? []);

                    RackMovementItem::withoutGlobalScopes()->create([
                        'rack_movement_id' => (int) $mv->id,
                        'product_id' => $pid,
                        'condition' => $cond,
                        'quantity' => $qty,
                        'defect_item_ids' => $cond === 'defect' ? $selectedDefectIds : null,
                        'damaged_item_ids' => $cond === 'damaged' ? $selectedDamagedIds : null,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                    // =========================================================
                    // ✅ RULE: jangan ubah stock langsung di controller.
                    // Semua pergerakkan stock HARUS pakai MutationController.
                    // =========================================================
                    $noteOut = trim("Rack Movement OUT (" . strtoupper($cond) . ") | From Rack #{$fromRackId} -> To Rack #{$toRackId}" . ($note ? " | {$note}" : ''));
                    $noteIn  = trim("Rack Movement IN (" . strtoupper($cond) . ") | From Rack #{$fromRackId} -> To Rack #{$toRackId}" . ($note ? " | {$note}" : ''));

                    if ($cond === 'defect') {
                        ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $fromWarehouseId)
                            ->where('rack_id', $fromRackId)
                            ->where('product_id', $pid)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $selectedDefectIds)
                            ->update([
                                'warehouse_id' => $toWarehouseId,
                                'rack_id' => $toRackId,
                                'updated_at' => now(),
                            ]);
                    }

                    if ($cond === 'damaged') {
                        ProductDamagedItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $fromWarehouseId)
                            ->where('rack_id', $fromRackId)
                            ->where('product_id', $pid)
                            ->whereNull('moved_out_at')
                            ->whereIn('id', $selectedDamagedIds)
                            ->update([
                                'warehouse_id' => $toWarehouseId,
                                'rack_id' => $toRackId,
                                'updated_by' => auth()->id(),
                                'updated_at' => now(),
                            ]);
                    }

                    // OUT dari source rack
                    $this->mutationController->applyInOut(
                        $branchId,
                        $fromWarehouseId,
                        $pid,
                        'Out',
                        $qty,
                        $reference,
                        $noteOut,
                        $date,
                        $fromRackId,
                        $cond,
                        'summary'
                    );

                    // IN ke destination rack
                    $this->mutationController->applyInOut(
                        $branchId,
                        $toWarehouseId,
                        $pid,
                        'In',
                        $qty,
                        $reference,
                        $noteIn,
                        $date,
                        $toRackId,
                        $cond,
                        'summary'
                    );
                }

                return $mv;
            });

            toast('Rack Movement Created!', 'success');
            return redirect()->route('inventory.rack-movements.show', $movement->id);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('message', $e->getMessage());
        }
    }

    private function generateReference(): string
    {
        // Format: RM-YYYYMMDD-XXXX (increment harian)
        $date = now()->format('Ymd');
        $prefix = "RM-{$date}-";

        $last = RackMovement::withoutGlobalScopes()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $next = 1;
        if (is_string($last) && str_starts_with($last, $prefix)) {
            $tail = substr($last, strlen($prefix));
            if (is_numeric($tail)) {
                $next = ((int) $tail) + 1;
            }
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function parseStoredUnitIds($raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        $ids = [];
        foreach ($items as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
