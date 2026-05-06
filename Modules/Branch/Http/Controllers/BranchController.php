<?php

namespace Modules\Branch\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Branch\DataTables\BranchesDataTable;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;

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

        return view('branch::create', [
            'entities' => Entity::query()->where('is_active', true)->orderBy('name')->get(),
            'availableWarehouses' => Warehouse::query()->whereNull('branch_id')->orderBy('warehouse_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_branch'), 403);

        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'branch_name' => 'required|string|max:100',
            'branch_address' => 'nullable|string|max:255',
            'branch_phone' => 'nullable|string|max:20',
            'warehouse_option' => 'required|in:select_existing,create_new',
            'existing_warehouse_id' => 'required_if:warehouse_option,select_existing|nullable|exists:warehouses,id',
            'new_warehouse_code' => 'required_if:warehouse_option,create_new|nullable|string|max:50',
            'new_warehouse_name' => 'required_if:warehouse_option,create_new|nullable|string|max:100',
        ]);

        $branch = Branch::create([
            'entity_id' => (int) $request->input('entity_id'),
            'name' => $request->branch_name,
            'address' => $request->branch_address,
            'phone' => $request->branch_phone,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        if ($request->warehouse_option === 'select_existing') {
            $warehouse = Warehouse::query()
                ->whereNull('branch_id')
                ->findOrFail($request->existing_warehouse_id);

            Warehouse::query()->where('branch_id', $branch->id)->update(['is_main' => false]);

            $warehouse->update([
                'branch_id' => $branch->id,
                'is_main' => true,
            ]);
        } elseif ($request->warehouse_option === 'create_new') {
            Warehouse::create([
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
        $branch = Branch::query()->with('entity')->findOrFail($id);

        return view('branch::edit', [
            'branch' => $branch,
            'entities' => Entity::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        abort_if(Gate::denies('edit_branch'), 403);

        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'branch_name' => 'required|string|max:100',
            'branch_address' => 'nullable|string|max:255',
            'branch_phone' => 'nullable|string|max:20',
        ]);

        $branch = Branch::findOrFail($id);
        $branch->update([
            'entity_id' => (int) $request->input('entity_id'),
            'name' => $request->branch_name,
            'address' => $request->branch_address,
            'phone' => $request->branch_phone,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy($id)
    {
        abort_if(Gate::denies('delete_branch'), 403);
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }
}
