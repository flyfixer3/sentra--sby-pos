<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\BranchContext;
use Modules\Inventory\Entities\Rack;
use Modules\Inventory\Entities\StockRack;
use Modules\Product\Entities\Warehouse;

class RackController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('access_racks'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');

        $branchId = $isAll ? 'all' : (int) $branchIdRaw;

        $q = Rack::withoutGlobalScopes()
            ->select([
                'racks.*',
                'warehouses.warehouse_name as warehouse_name',
                'warehouses.branch_id as warehouse_branch_id',
                'branches.name as branch_name',
            ])
            ->join('warehouses', 'warehouses.id', '=', 'racks.warehouse_id')
            ->leftJoin('branches', 'branches.id', '=', 'warehouses.branch_id');

        if (!$isAll) {
            // bisa pakai racks.branch_id karena sudah kita tambah
            $q->where('racks.branch_id', (int) $branchId);
        }

        if ($request->filled('warehouse_id')) {
            $wid = (int) $request->warehouse_id;
            $q->where('racks.warehouse_id', $wid);
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $q->where(function ($w) use ($s) {
                $w->where('racks.code', 'like', "%{$s}%")
                ->orWhere('racks.name', 'like', "%{$s}%")
                ->orWhere('racks.description', 'like', "%{$s}%");
            });
        }

        $racks = $q->orderBy('racks.id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // dropdown warehouses: pakai withoutGlobalScopes supaya mode ALL aman
        $warehouses = Warehouse::withoutGlobalScopes()
            ->when(!$isAll, function ($w) use ($branchId) {
                $w->where('branch_id', (int) $branchId);
            })
            ->orderBy('warehouse_name')
            ->get();

        return view('inventory::racks.index', compact('racks', 'warehouses'));
    }

    public function create()
    {
        abort_if(Gate::denies('create_racks'), 403);

        $branchId = BranchContext::id();
        $isAll = ($branchId === 'all' || $branchId === null || $branchId === '');

        if ($isAll) {
            toast('Please select a specific branch first. You cannot create Rack in "All Branches" mode.', 'error');
            return redirect()->route('inventory.racks.index');
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', (int) $branchId)
            ->orderBy('warehouse_name')
            ->get();

        return view('inventory::racks.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_racks'), 403);

        $branchId = BranchContext::id();
        $isAll = ($branchId === 'all' || $branchId === null || $branchId === '');

        if ($isAll) {
            toast('Please select a specific branch first. You cannot create Rack in "All Branches" mode.', 'error');
            return redirect()->route('inventory.racks.index');
        }

        $request->validate([
            'warehouse_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail((int) $request->warehouse_id);

        // wajib match active branch
        abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);

        $code = strtoupper(trim((string) $request->code));

        $exists = Rack::withoutGlobalScopes()
            ->where('warehouse_id', (int) $warehouse->id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('message', 'Rack code sudah dipakai di gudang tersebut.');
        }

        Rack::withoutGlobalScopes()->create([
            'warehouse_id' => (int) $warehouse->id,
            'branch_id'    => (int) $warehouse->branch_id, // ✅ ikut warehouse
            'code'         => $code,
            'name'         => $request->name ? trim((string) $request->name) : null,
            'description'  => $request->description ? trim((string) $request->description) : null,
            'created_by'   => auth()->id(),
            'updated_by'   => auth()->id(),
        ]);

        toast('Rack Created!', 'success');
        return redirect()->route('inventory.racks.index');
    }

    public function generateCode($warehouseId)
    {
        abort_if(Gate::denies('create_racks'), 403);

        $branchId = BranchContext::id();
        $isAll = ($branchId === 'all' || $branchId === null || $branchId === '');

        if ($isAll) {
            return response()->json([
                'message' => 'Please select a specific branch first. You cannot generate Rack code in "All Branches" mode.'
            ], 422);
        }

        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail((int) $warehouseId);
        abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);

        $codes = Rack::withoutGlobalScopes()
            ->where('warehouse_id', (int) $warehouse->id)
            ->pluck('code')
            ->map(fn($c) => strtoupper(trim((string) $c)))
            ->toArray();

        $used = [];
        foreach ($codes as $c) {
            if (preg_match('/^R(\d{1,})$/', $c, $m)) {
                $used[(int) $m[1]] = true;
            }
        }

        $next = 1;
        while (isset($used[$next])) $next++;

        $code = 'R' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'code' => $code,
        ]);
    }

    public function edit(Rack $rack)
    {
        abort_if(Gate::denies('edit_racks'), 403);

        $branchId = BranchContext::id();
        $isAll = ($branchId === 'all' || $branchId === null || $branchId === '');

        if ($isAll) {
            toast('Please select a specific branch first. You cannot edit Rack in "All Branches" mode.', 'error');
            return redirect()->route('inventory.racks.index');
        }

        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail((int) $rack->warehouse_id);
        abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);

        $warehouses = Warehouse::withoutGlobalScopes()
            ->where('branch_id', (int) $branchId)
            ->orderBy('warehouse_name')
            ->get();

        return view('inventory::racks.edit', compact('rack', 'warehouses'));
    }

    public function show(Rack $rack)
    {
        abort_if(Gate::denies('access_racks'), 403);

        $branchId = BranchContext::id();
        $isAll = ($branchId === 'all' || $branchId === null || $branchId === '');

        $rack = Rack::withoutGlobalScopes()
            ->select([
                'racks.*',
                'warehouses.warehouse_name as warehouse_name',
                'warehouses.branch_id as warehouse_branch_id',
                'branches.name as branch_name',
            ])
            ->join('warehouses', 'warehouses.id', '=', 'racks.warehouse_id')
            ->leftJoin('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->where('racks.id', (int) $rack->id)
            ->firstOrFail();

        if (!$isAll) {
            abort_unless((int) $rack->branch_id === (int) $branchId, 403);
        }

        $products = StockRack::withoutGlobalScopes()
            ->from('stock_racks as sr')
            ->join('products as p', 'p.id', '=', 'sr.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sr.rack_id', (int) $rack->id)
            ->select([
                'sr.product_id',
                DB::raw('MAX(p.product_code) as product_code'),
                DB::raw('MAX(p.product_name) as product_name'),
                DB::raw('MAX(p.product_unit) as product_unit'),
                DB::raw('MAX(c.category_name) as category_name'),
                DB::raw('COALESCE(SUM(sr.qty_total), 0) as qty_total'),
                DB::raw('COALESCE(SUM(sr.qty_good), 0) as qty_good'),
                DB::raw('COALESCE(SUM(sr.qty_defect), 0) as qty_defect'),
                DB::raw('COALESCE(SUM(sr.qty_damaged), 0) as qty_damaged'),
            ])
            ->groupBy('sr.product_id')
            ->orderByRaw('MAX(p.product_name) asc')
            ->get()
            ->map(function ($row) {
                $fallbackTotal = (int) ($row->qty_good ?? 0) + (int) ($row->qty_defect ?? 0) + (int) ($row->qty_damaged ?? 0);
                $resolvedTotal = (int) ($row->qty_total ?? 0);
                $row->qty_total = $resolvedTotal > 0 ? $resolvedTotal : $fallbackTotal;

                return $row;
            })
            ->filter(function ($row) {
                return (int) ($row->qty_total ?? 0) > 0
                    || (int) ($row->qty_good ?? 0) > 0
                    || (int) ($row->qty_defect ?? 0) > 0
                    || (int) ($row->qty_damaged ?? 0) > 0;
            })
            ->values();

        return view('inventory::racks.show', compact('rack', 'products'));
    }

    public function update(Request $request, Rack $rack)
    {
        abort_if(Gate::denies('edit_racks'), 403);

        $branchIdRaw = BranchContext::id();
        $isAll = ($branchIdRaw === 'all' || $branchIdRaw === null || $branchIdRaw === '');
        $branchId = $isAll ? 'all' : (int) $branchIdRaw;

        $request->validate([
            'warehouse_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail((int) $request->warehouse_id);

        if (!$isAll) {
            abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);
        }

        $code = strtoupper(trim((string) $request->code));

        $exists = Rack::withoutGlobalScopes()
            ->where('warehouse_id', (int) $warehouse->id)
            ->where('code', $code)
            ->where('id', '!=', (int) $rack->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('message', 'Rack code sudah dipakai di gudang tersebut.');
        }

        DB::transaction(function () use ($rack, $warehouse, $request, $code) {
            $rack->update([
                'warehouse_id' => (int) $warehouse->id,
                'branch_id'    => (int) $warehouse->branch_id, // ✅ sync branch
                'code'         => $code,
                'name'         => $request->name ? trim((string) $request->name) : null,
                'description'  => $request->description ? trim((string) $request->description) : null,
                'updated_by'   => auth()->id(),
            ]);
        });

        toast('Rack Updated!', 'info');
        return redirect()->route('inventory.racks.index');
    }

    public function destroy(Rack $rack)
    {
        abort_if(Gate::denies('delete_racks'), 403);

        $branchId = BranchContext::id();

        $warehouse = Warehouse::findOrFail((int) $rack->warehouse_id);
        if ($branchId !== 'all') {
            abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);
        }

        // kalau rack sudah dipakai di stock_racks, tolak (biar aman)
        $used = $rack->stockRacks()->exists();
        if ($used) {
            toast('Rack tidak bisa dihapus karena sudah dipakai pada Stock Rack.', 'error');
            return redirect()->back();
        }

        $rack->delete();

        toast('Rack Deleted!', 'warning');
        return redirect()->route('inventory.racks.index');
    }
}
