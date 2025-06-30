<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Warehouse;
use Modules\Product\DataTables\WarehouseDataTable;

class WarehousesController extends Controller
{

    public function index(WarehouseDataTable $dataTable) {
        abort_if(Gate::denies('access_warehouses'), 403);

        return $dataTable->render('product::warehouses.index');
    }

    public function preview($id)
    {
        $warehouse = \Modules\Product\Entities\Warehouse::findOrFail($id);

        return response()->json([
            'warehouse_code' => $warehouse->warehouse_code,
            'warehouse_name' => $warehouse->warehouse_name,
            'is_main' => $warehouse->is_main,
        ]);
    }



    public function store(Request $request)
    {
        abort_if(Gate::denies('access_warehouses'), 403);

        $request->validate([
            'warehouse_code' => 'required|unique:warehouses,warehouse_code',
            'warehouse_name' => 'required',
            'branch_id' => 'required|exists:branches,id',
            'is_main' => 'nullable|boolean',
        ]);

        if ($request->boolean('is_main')) {
            // Reset all other warehouses on that branch
            Warehouse::where('branch_id', $request->branch_id)->update(['is_main' => false]);
        }

        Warehouse::create([
            'warehouse_code' => $request->warehouse_code,
            'warehouse_name' => $request->warehouse_name,
            'branch_id' => $request->branch_id,
            'is_main' => $request->boolean('is_main'),
        ]);

        toast('Warehouse Created!', 'success');
        return redirect()->back();
    }



    public function edit($id) {
        abort_if(Gate::denies('access_warehouses'), 403);

        $warehouse = Warehouse::findOrFail($id);

        return view('product::warehouses.edit', compact('warehouse'));
    }


    public function update(Request $request, $id) {
        abort_if(Gate::denies('access_warehouses'), 403);

        $request->validate([
            'warehouse_code' => 'required|unique:warehouses,warehouse_code,' . $id,
            'warehouse_name' => 'required',
            'is_main' => 'nullable|boolean',
        ]);

        $warehouse = Warehouse::findOrFail($id);

        if ($request->boolean('is_main')) {
            Warehouse::where('branch_id', $warehouse->branch_id)->update(['is_main' => false]);
        }

        $warehouse->update([
            'warehouse_code' => $request->warehouse_code,
            'warehouse_name' => $request->warehouse_name,
            'is_main' => $request->boolean('is_main'),
        ]);


        toast('Product Warehouse Updated!', 'info');

        return redirect()->route('product-warehouses.index');
    }


    public function destroy($id) {
        abort_if(Gate::denies('access_warehouses'), 403);

        $warehouse = Warehouse::findOrFail($id);

        if ($warehouse->products()->isNotEmpty()) {
            return back()->withErrors('Can\'t delete beacuse there are products associated with this warehouse.');
        }

        $warehouse->delete();

        toast('Product Warehouse Deleted!', 'warning');

        return redirect()->route('product-warehouses.index');
    }
}
