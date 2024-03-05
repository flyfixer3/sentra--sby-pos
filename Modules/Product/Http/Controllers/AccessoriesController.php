<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Accessory;
use Modules\Product\DataTables\ProductAccessoriesDataTable;

class AccessoriesController extends Controller
{

    public function index(ProductAccessoriesDataTable $dataTable) {
        abort_if(Gate::denies('access_product_accessories'), 403);

        return $dataTable->render('product::accessories.index');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_product_accessories'), 403);

        $request->validate([
            'accessory_code' => 'required|unique:accessories,accessory_code',
            'accessory_name' => 'required'
        ]);

        Accessory::create([
            'accessory_code' => $request->accessory_code,
            'accessory_name' => $request->accessory_name,
            
        ]);

        toast('Product Accessory Created!', 'success');

        return redirect()->back();
    }


    public function edit($id) {
        abort_if(Gate::denies('access_product_accessories'), 403);

        $accessory = Accessory::findOrFail($id);

        return view('product::accessories.edit', compact('accessory'));
    }


    public function update(Request $request, $id) {
        abort_if(Gate::denies('access_product_accessories'), 403);

        $request->validate([
            'code' => 'required|unique:accessories,accessory_code,' . $id,
            'accessory_name' => 'required'
        ]);

        Accessory::findOrFail($id)->update([
            'code' => $request->accessory_code,
            'accessory_name' => $request->accessory_name,
        ]);

        toast('Product Accessory Updated!', 'info');

        return redirect()->route('product-accessories.index');
    }


    public function destroy($id) {
        abort_if(Gate::denies('access_product_accessories'), 403);

        $accessory = Accessory::findOrFail($id);

        if ($accessory->products()->isNotEmpty()) {
            return back()->withErrors('Can\'t delete beacuse there are products associated with this accessory.');
        }

        $accessory->delete();

        toast('Product Accessory Deleted!', 'warning');

        return redirect()->route('product-accessories.index');
    }
}
