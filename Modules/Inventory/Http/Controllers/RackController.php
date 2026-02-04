<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Support\BranchContext;
use Modules\Inventory\Entities\Rack;
use Modules\Product\Entities\Warehouse;

class RackController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('access_racks'), 403);

        $branchId = BranchContext::id();

        $q = Rack::query()
            ->select('racks.*')
            ->join('warehouses', 'warehouses.id', '=', 'racks.warehouse_id')
            ->when($branchId !== 'all', function ($query) use ($branchId) {
                $query->where('warehouses.branch_id', (int) $branchId);
            });

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

        $racks = $q->orderBy('racks.id', 'desc')->paginate(20)->withQueryString();

        $warehouses = Warehouse::query()
            ->when($branchId !== 'all', function ($w) use ($branchId) {
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

        $warehouses = Warehouse::query()
            ->when($branchId !== 'all', function ($w) use ($branchId) {
                $w->where('branch_id', (int) $branchId);
            })
            ->orderBy('warehouse_name')
            ->get();

        return view('inventory::racks.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_racks'), 403);

        $branchId = BranchContext::id();

        $request->validate([
            'warehouse_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $warehouse = Warehouse::findOrFail((int) $request->warehouse_id);
        if ($branchId !== 'all') {
            abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);
        }

        // unik per warehouse: code
        $code = strtoupper(trim((string) $request->code));

        $exists = Rack::query()
            ->where('warehouse_id', (int) $warehouse->id)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('message', 'Rack code sudah dipakai di gudang tersebut.');
        }

        Rack::create([
            'warehouse_id' => (int) $warehouse->id,
            'code' => strtoupper(trim((string) $request->code)),
            'name' => $request->name ? trim((string) $request->name) : null,
            'description' => $request->description ? trim((string) $request->description) : null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        toast('Rack Created!', 'success');
        return redirect()->route('inventory.racks.index');
    }

    public function edit(Rack $rack)
    {
        abort_if(Gate::denies('edit_racks'), 403);

        $branchId = BranchContext::id();

        $warehouse = Warehouse::findOrFail((int) $rack->warehouse_id);
        if ($branchId !== 'all') {
            abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);
        }

        $warehouses = Warehouse::query()
            ->when($branchId !== 'all', function ($w) use ($branchId) {
                $w->where('branch_id', (int) $branchId);
            })
            ->orderBy('warehouse_name')
            ->get();

        return view('inventory::racks.edit', compact('rack', 'warehouses'));
    }

    public function update(Request $request, Rack $rack)
    {
        abort_if(Gate::denies('edit_racks'), 403);

        $branchId = BranchContext::id();

        $request->validate([
            'warehouse_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $warehouse = Warehouse::findOrFail((int) $request->warehouse_id);
        if ($branchId !== 'all') {
            abort_unless((int) $warehouse->branch_id === (int) $branchId, 403);
        }

        $exists = Rack::query()
            ->where('warehouse_id', (int) $warehouse->id)
            ->where('code', strtoupper(trim((string) $request->code)))
            ->where('id', '!=', (int) $rack->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('message', 'Rack code sudah dipakai di gudang tersebut.');
        }

        DB::transaction(function () use ($rack, $warehouse, $request) {
            $rack->update([
                'warehouse_id' => (int) $warehouse->id,
                'code' => strtoupper(trim((string) $request->code)),
                'name' => $request->name ? trim((string) $request->name) : null,
                'description' => $request->description ? trim((string) $request->description) : null,
                'updated_by' => auth()->id(),
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
