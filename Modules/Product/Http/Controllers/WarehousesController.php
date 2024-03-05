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


    public function store(Request $request) {
        abort_if(Gate::denies('access_warehouses'), 403);

        $request->validate([
            'warehouse_code' => 'required|unique:warehouses,warehouse_code',
            'warehouse_name' => 'required'
        ]);

        Warehouse::create([
            'warehouse_code' => $request->warehouse_code,
            'warehouse_name' => $request->warehouse_name,
            
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
            'warehouse_name' => 'required'
        ]);

        Warehouse::findOrFail($id)->update([
            'warehouse_code' => $request->warehouse_code,
            'warehouse_name' => $request->warehouse_name,
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
