<?php

namespace Modules\Adjustment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustment\DataTables\AdjustmentsDataTable;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Product\Entities\Product;
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

        $branchId = $this->getActiveBranchId();
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($branchId);

        $warehouses = DB::table('warehouses')
            ->where('branch_id', $branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        return view('adjustment::create', [
            'activeBranchId' => $branchId,
            'defaultWarehouseId' => $defaultWarehouseId,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $request->validate([
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'warehouse_id'  => 'required|integer',

            'product_ids'   => 'required|array',
            'product_ids.*' => 'required|integer',

            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array',
            'types.*'       => 'required|in:add,sub',

            'notes'         => 'nullable|array',
        ]);

        DB::transaction(function () use ($request) {

            $branchId = $this->getActiveBranchId();
            $warehouseId = (int) $request->warehouse_id;

            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);

            $adjustment = Adjustment::create([
                'date'         => $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
            ]);

            foreach ($request->product_ids as $key => $productId) {

                $productId = (int) $productId;
                $qty = (int) ($request->quantities[$key] ?? 0);
                $type = $request->types[$key] ?? null;
                $itemNote = $request->notes[$key] ?? null;

                if ($productId <= 0 || $qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \RuntimeException('Data item adjustment tidak valid (product/qty/type).');
                }

                Product::findOrFail($productId);

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $productId,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'note'          => $itemNote,
                ]);

                $mutationType = $type === 'add' ? 'In' : 'Out';

                // IMPORTANT: note diawali "Adjustment" supaya rollbackByReference() bisa filter dengan aman
                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | '.$adjustment->note : '')
                    . ($itemNote ? ' | Item: '.$itemNote : '')
                );

               $this->mutationController->applyInOut(
                    $branchId,
                    $warehouseId,
                    $productId,
                    $mutationType,
                    $qty,
                    $adjustment->reference,
                    $mutationNote,
                    $request->date
                );
            }
        });

        toast('Adjustment Created!', 'success');
        return redirect()->route('adjustments.index');
    }

    public function show(Adjustment $adjustment)
    {
        abort_if(Gate::denies('show_adjustments'), 403);
        return view('adjustment::show', compact('adjustment'));
    }

    public function edit(Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $branchId = $this->getActiveBranchId();
        $defaultWarehouseId = $adjustment->warehouse_id
            ? (int) $adjustment->warehouse_id
            : $this->resolveDefaultWarehouseId($branchId);

        $warehouses = DB::table('warehouses')
            ->where('branch_id', $branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        return view('adjustment::edit', [
            'adjustment' => $adjustment,
            'activeBranchId' => $branchId,
            'defaultWarehouseId' => $defaultWarehouseId,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(Request $request, Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $request->validate([
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'warehouse_id'  => 'required|integer',

            'product_ids'   => 'required|array',
            'product_ids.*' => 'required|integer',

            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array',
            'types.*'       => 'required|in:add,sub',

            'notes'         => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $adjustment) {

            $branchId = $this->getActiveBranchId();
            $newWarehouseId = (int) $request->warehouse_id;

            $this->assertWarehouseBelongsToBranch($newWarehouseId, $branchId);

            // 1) rollback mutation lama + balikin stock
            $this->mutationController->rollbackByReference($adjustment->reference, 'Adjustment');

            // 2) hapus detail lama
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // 3) update header (JANGAN update reference)
            $adjustment->update([
                'date'         => $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $newWarehouseId,
            ]);

            // 4) create detail baru + mutation baru + update stock via MutationController
            foreach ($request->product_ids as $key => $productId) {

                $productId = (int) $productId;
                $qty = (int) ($request->quantities[$key] ?? 0);
                $type = $request->types[$key] ?? null;
                $itemNote = $request->notes[$key] ?? null;

                if ($productId <= 0 || $qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \RuntimeException('Data item adjustment tidak valid (product/qty/type).');
                }

                Product::findOrFail($productId);

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $productId,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'note'          => $itemNote,
                ]);

                $mutationType = $type === 'add' ? 'In' : 'Out';

                // pakai NOTE yang sudah ter-update (karena adjustment->note sudah diupdate)
                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | '.$adjustment->note : '')
                    . ($itemNote ? ' | Item: '.$itemNote : '')
                );

                $this->mutationController->applyInOut(
                    $branchId,
                    $newWarehouseId,
                    $productId,
                    $mutationType,
                    $qty,
                    $adjustment->reference,
                    $mutationNote,
                    $request->date
                );

            }
        });

        toast('Adjustment Updated!', 'info');
        return redirect()->route('adjustments.index');
    }

    public function destroy(Adjustment $adjustment)
    {
        abort_if(Gate::denies('delete_adjustments'), 403);

        DB::transaction(function () use ($adjustment) {
            $this->mutationController->rollbackByReference($adjustment->reference, 'Adjustment');
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();
            $adjustment->delete();
        });

        toast('Adjustment Deleted!', 'warning');
        return redirect()->route('adjustments.index');
    }

    private function getActiveBranchId(): int
    {
        $active = session('active_branch');

        if ($active === 'all' || $active === null) {
            if (auth()->check() && isset(auth()->user()->branch_id) && auth()->user()->branch_id) {
                return (int) auth()->user()->branch_id;
            }
            return 1;
        }

        return (int) $active;
    }

    private function resolveDefaultWarehouseId(int $branchId): int
    {
        $mainId = DB::table('warehouses')
            ->where('branch_id', $branchId)
            ->where('is_main', 1)
            ->value('id');

        if (!$mainId) {
            throw new \RuntimeException("Main warehouse untuk branch_id={$branchId} belum ada.");
        }

        return (int) $mainId;
    }

    private function assertWarehouseBelongsToBranch(int $warehouseId, int $branchId): void
    {
        $exists = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('branch_id', $branchId)
            ->exists();

        if (!$exists) {
            throw new \RuntimeException("Warehouse (id={$warehouseId}) tidak belong ke branch (id={$branchId}).");
        }
    }
}
