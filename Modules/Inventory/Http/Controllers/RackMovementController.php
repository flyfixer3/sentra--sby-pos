<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\BranchContext;

use Modules\Inventory\Entities\Rack;
use Modules\Inventory\Entities\RackMovement;
use Modules\Inventory\Entities\RackMovementItem;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;

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

        return view('inventory::rack-movements.show', compact('rackMovement'));
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
            'quantities.*' => 'required|integer|min:1',
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

        if (count($productIds) !== count($conditions) || count($productIds) !== count($quantities)) {
            return back()->withInput()->with('message', 'Items tidak valid (jumlah array tidak sama).');
        }

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
                $bucketCol
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

                // validasi qty berdasarkan stock_racks source rack
                // NOTE: karena ada kemungkinan user input item product yang sama berulang, kita group dulu
                // agar cek stock = total qty per product+condition.
                $group = [];
                foreach ($productIds as $i => $pidRaw) {
                    $pid = (int) $pidRaw;
                    $cond = strtolower(trim((string) ($conditions[$i] ?? 'good')));
                    if (!in_array($cond, ['good', 'defect', 'damaged'], true)) $cond = 'good';
                    $qty = (int) ($quantities[$i] ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    Product::withoutGlobalScopes()->findOrFail($pid);

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

                // insert items + mutations
                foreach ($productIds as $i => $pidRaw) {
                    $pid = (int) $pidRaw;
                    $cond = strtolower(trim((string) ($conditions[$i] ?? 'good')));
                    if (!in_array($cond, ['good', 'defect', 'damaged'], true)) $cond = 'good';
                    $qty = (int) ($quantities[$i] ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    RackMovementItem::withoutGlobalScopes()->create([
                        'rack_movement_id' => (int) $mv->id,
                        'product_id' => $pid,
                        'condition' => $cond,
                        'quantity' => $qty,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                    // =========================================================
                    // ✅ RULE: jangan ubah stock langsung di controller.
                    // Semua pergerakkan stock HARUS pakai MutationController.
                    // =========================================================
                    $noteOut = trim("Rack Movement OUT (" . strtoupper($cond) . ") | From Rack #{$fromRackId} -> To Rack #{$toRackId}" . ($note ? " | {$note}" : ''));
                    $noteIn  = trim("Rack Movement IN (" . strtoupper($cond) . ") | From Rack #{$fromRackId} -> To Rack #{$toRackId}" . ($note ? " | {$note}" : ''));

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
}