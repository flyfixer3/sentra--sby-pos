<?php

namespace Modules\Branch\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Branch\DataTables\BranchesDataTable;

class BranchController extends Controller
{
    public function index(BranchesDataTable $dataTable)
    {
        abort_if(Gate::denies('access_branches'), 403);

        return $dataTable->render('branch::index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_branch'), 403);
        return view('branch::create');
    }

   public function store(\Illuminate\Http\Request $request)
{
    abort_if(Gate::denies('create_branch'), 403);

    $request->validate([
        'branch_name' => 'required|string|max:100',
        'branch_address' => 'nullable|string|max:255',
        'branch_phone' => 'nullable|string|max:20',
        'warehouse_option' => 'required|in:select_existing,create_new',
        'existing_warehouse_id' => 'required_if:warehouse_option,select_existing|nullable|exists:warehouses,id',
        'new_warehouse_code' => 'required_if:warehouse_option,create_new|nullable|string|max:50',
        'new_warehouse_name' => 'required_if:warehouse_option,create_new|nullable|string|max:100',
    ]);

    // 1. Buat Branch terlebih dahulu
    $branch = \Modules\Branch\Entities\Branch::create([
        'name' => $request->branch_name,
        'address' => $request->branch_address,
        'phone' => $request->branch_phone,
        'is_active' => true,
        'created_by' => auth()->id(),
    ]);

    // 2. Assign warehouse ke branch
    if ($request->warehouse_option === 'select_existing') {
        $warehouse = \Modules\Product\Entities\Warehouse::whereNull('branch_id')
            ->findOrFail($request->existing_warehouse_id);

        // Pastikan tidak ada warehouse lain yang jadi main (safety)
        \Modules\Product\Entities\Warehouse::where('branch_id', $branch->id)->update(['is_main' => false]);

        $warehouse->update([
            'branch_id' => $branch->id,
            'is_main' => true,
        ]);
    } elseif ($request->warehouse_option === 'create_new') {
        \Modules\Product\Entities\Warehouse::create([
            'warehouse_code' => $request->new_warehouse_code,
            'warehouse_name' => $request->new_warehouse_name,
            'branch_id' => $branch->id,
            'is_main' => true,
        ]);
    }

    return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
}




    public function edit($id)
    {
        abort_if(Gate::denies('edit_branch'), 403);
        $branch = \Modules\Branch\Entities\Branch::findOrFail($id);
        return view('branch::edit', compact('branch'));
    }

    public function update(\Illuminate\Http\Request $request, $id)
    {
        abort_if(Gate::denies('edit_branch'), 403);

        $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $branch = \Modules\Branch\Entities\Branch::findOrFail($id);
        $branch->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy($id)
    {
        abort_if(Gate::denies('delete_branch'), 403);
        $branch = \Modules\Branch\Entities\Branch::findOrFail($id);
        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }
}
