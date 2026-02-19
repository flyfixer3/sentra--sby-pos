<?php

namespace Modules\Adjustment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustment\DataTables\AdjustmentsDataTable;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
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

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create an adjustment.");
        }

        $activeBranchId = (int) $active;

        $warehouses = Warehouse::query()
            ->where('branch_id', $activeBranchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId = (int) optional($warehouses->firstWhere('is_main', 1))->id;
        if (!$defaultWarehouseId) {
            $defaultWarehouseId = (int) optional($warehouses->first())->id;
        }

        return view('adjustment::create', compact('warehouses', 'activeBranchId', 'defaultWarehouseId'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $request->validate([
            'date'          => 'required|date',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'note'          => 'nullable|string|max:1000',

            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',

            'rack_ids'      => 'required|array|min:1',
            'rack_ids.*'    => 'required|integer|exists:racks,id',

            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array|min:1',
            'types.*'       => 'required|in:add,sub',

            // ✅ NEW: condition
            'conditions'    => 'required|array|min:1',
            'conditions.*'  => 'required|in:good,defect,damaged',

            // ✅ NEW: extra note inputs
            'defect_types'       => 'nullable|array',
            'defect_types.*'     => 'nullable|string|max:255',
            'damaged_reasons'    => 'nullable|array',
            'damaged_reasons.*'  => 'nullable|string|max:1000',

            'notes'         => 'nullable|array',
        ]);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create an adjustment.");
        }
        $branchId = (int) $active;

        $warehouseId = (int) $request->warehouse_id;

        $wh = Warehouse::findOrFail($warehouseId);
        if ((int) $wh->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        DB::transaction(function () use ($request, $branchId, $warehouseId) {

            $adjustment = Adjustment::create([
                'date'         => (string) $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
                'created_by'   => Auth::id(),
            ]);

            $reference = (string) $adjustment->reference;

            foreach ($request->product_ids as $key => $productId) {

                $productId  = (int) $productId;
                $rackId     = (int) ($request->rack_ids[$key] ?? 0);
                $qty        = (int) ($request->quantities[$key] ?? 0);
                $type       = (string) ($request->types[$key] ?? '');
                $condition  = strtolower((string) ($request->conditions[$key] ?? 'good'));
                $itemNote   = $request->notes[$key] ?? null;

                $defectType = trim((string) ($request->defect_types[$key] ?? ''));
                $damagedReason = trim((string) ($request->damaged_reasons[$key] ?? ''));

                if ($rackId <= 0) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Rack is required for every item.')
                    );
                }

                // ✅ validate rack belongs to warehouse
                $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                if ($qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Invalid item data.')
                    );
                }

                if (!in_array($condition, ['good', 'defect', 'damaged'], true)) {
                    $condition = 'good';
                }

                Product::findOrFail($productId);

                // ✅ validate extra fields for ADD only
                if ($type === 'add' && $condition === 'defect' && $defectType === '') {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Defect Type is required when condition = DEFECT (Add).')
                    );
                }
                if ($type === 'add' && $condition === 'damaged' && $damagedReason === '') {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Damaged Reason is required when condition = DAMAGED (Add).')
                    );
                }

                // ✅ save adjusted_products (log)
                $adjustedNotePieces = [];
                if (!empty($itemNote)) $adjustedNotePieces[] = "Item: {$itemNote}";
                $adjustedNotePieces[] = 'COND=' . strtoupper($condition);
                if ($type === 'add' && $condition === 'defect' && $defectType !== '') $adjustedNotePieces[] = "DEFECT_TYPE={$defectType}";
                if ($type === 'add' && $condition === 'damaged' && $damagedReason !== '') $adjustedNotePieces[] = "REASON={$damagedReason}";
                $adjustedNote = trim(implode(' | ', $adjustedNotePieces));

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $productId,
                    'rack_id'       => $rackId,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'note'          => $adjustedNote ?: null,
                ]);

                // ✅ build mutation note (bucket-aware)
                $mutationType = $type === 'add' ? 'In' : 'Out';
                $bucketLabel  = strtoupper($condition); // GOOD/DEFECT/DAMAGED

                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | ' . $adjustment->note : '')
                    . ($itemNote ? ' | Item: ' . $itemNote : '')
                    . ' | ' . $bucketLabel
                );

                // =========================================================
                // CASE A: GOOD (default)
                // =========================================================
                if ($condition === 'good') {
                    $this->mutationController->applyInOut(
                        $branchId,
                        $warehouseId,
                        $productId,
                        $mutationType,
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    continue;
                }

                // =========================================================
                // CASE B: DEFECT / DAMAGED
                // =========================================================

                // SUB: auto-pick FIFO from defect/damaged items table
                if ($type === 'sub') {

                    if ($condition === 'defect') {
                        $items = ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $warehouseId)
                            ->where('rack_id', $rackId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->orderBy('id')
                            ->limit($qty)
                            ->lockForUpdate()
                            ->get();

                        if ($items->count() < $qty) {
                            throw new \RuntimeException("Not enough DEFECT items to SUB. Need {$qty}, have {$items->count()}.");
                        }

                        $this->mutationController->applyInOut(
                            $branchId,
                            $warehouseId,
                            $productId,
                            'Out',
                            $qty,
                            $reference,
                            $mutationNote,
                            (string) $request->date,
                            $rackId
                        );

                        foreach ($items as $it) {
                            $it->update([
                                'moved_out_at'             => now(),
                                'moved_out_by'             => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id'   => (int) $adjustment->id,
                            ]);
                        }

                        continue;
                    }

                    // damaged
                    $items = ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->orderBy('id')
                        ->limit($qty)
                        ->lockForUpdate()
                        ->get();

                    if ($items->count() < $qty) {
                        throw new \RuntimeException("Not enough DAMAGED items to SUB. Need {$qty}, have {$items->count()}.");
                    }

                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $warehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    foreach ($items as $it) {
                        $it->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                            'mutation_out_id'          => (int) $mutationOutId,
                        ]);
                    }

                    continue;
                }

                // ADD: create mutation IN + create per-unit rows into defect/damaged tables
                if ($condition === 'defect') {

                    $this->mutationController->applyInOut(
                        $branchId,
                        $warehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    for ($i = 0; $i < $qty; $i++) {
                        ProductDefectItem::create([
                            'branch_id'       => $branchId,
                            'warehouse_id'    => $warehouseId,
                            'rack_id'         => $rackId,
                            'product_id'      => $productId,
                            'reference_id'    => (int) $adjustment->id,
                            'reference_type'  => Adjustment::class,
                            'quantity'        => 1,
                            'defect_type'     => $defectType,
                            'description'     => $itemNote,
                            'photo_path'      => null,
                            'created_by'      => (int) Auth::id(),
                        ]);
                    }

                    continue;
                }

                // damaged (ADD)
                $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                    $branchId,
                    $warehouseId,
                    $productId,
                    'In',
                    $qty,
                    $reference,
                    $mutationNote,
                    (string) $request->date,
                    $rackId
                );

                for ($i = 0; $i < $qty; $i++) {
                    ProductDamagedItem::create([
                        'branch_id'        => $branchId,
                        'warehouse_id'     => $warehouseId,
                        'rack_id'          => $rackId,
                        'product_id'       => $productId,
                        'reference_id'     => (int) $adjustment->id,
                        'reference_type'   => Adjustment::class,
                        'quantity'         => 1,
                        'damage_type'      => 'damaged',
                        'reason'           => $damagedReason,
                        'photo_path'       => null,
                        'cause'            => null,
                        'responsible_user_id' => null,
                        'resolution_status'   => 'pending',
                        'resolution_note'     => null,
                        'mutation_in_id'      => (int) $mutationInId,
                        'mutation_out_id'     => null,
                        'created_by'          => (int) Auth::id(),
                    ]);
                }
            }
        });

        toast('Adjustment Created!', 'success');
        return redirect()->route('adjustments.index');
    }

    public function show(Adjustment $adjustment)
    {
        abort_if(Gate::denies('show_adjustments'), 403);

        $adjustment->loadMissing([
            'creator',
            'branch',
            'warehouse',
            'adjustedProducts.product',
        ]);

        return view('adjustment::show', compact('adjustment'));
    }

    public function edit(Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to edit an adjustment.");
        }
        $activeBranchId = (int) $active;

        // (optional tapi rapi) pastikan data memang milik branch aktif
        if ((int) $adjustment->branch_id !== $activeBranchId) {
            abort(403, 'You can only edit adjustments from the active branch.');
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', $activeBranchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId = (int) ($adjustment->warehouse_id ?: optional($warehouses->firstWhere('is_main', 1))->id);

        return view('adjustment::edit', compact('adjustment', 'warehouses', 'activeBranchId', 'defaultWarehouseId'));
    }

    public function update(Request $request, Adjustment $adjustment)
    {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $request->validate([
            'date'          => 'required|date',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'note'          => 'nullable|string|max:1000',

            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',

            'rack_ids'      => 'required|array|min:1',
            'rack_ids.*'    => 'required|integer|exists:racks,id',

            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array|min:1',
            'types.*'       => 'required|in:add,sub',

            // ✅ NEW: condition
            'conditions'    => 'required|array|min:1',
            'conditions.*'  => 'required|in:good,defect,damaged',

            // ✅ NEW: extra inputs
            'defect_types'       => 'nullable|array',
            'defect_types.*'     => 'nullable|string|max:255',
            'damaged_reasons'    => 'nullable|array',
            'damaged_reasons.*'  => 'nullable|string|max:1000',

            'notes'         => 'nullable|array',
        ]);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Please choose a specific branch first (not 'All Branch') to update an adjustment.");
        }
        $branchId = (int) $active;

        if ((int) $adjustment->branch_id !== $branchId) {
            abort(403, 'You can only update adjustments from the active branch.');
        }

        $newWarehouseId = (int) $request->warehouse_id;

        $wh = Warehouse::findOrFail($newWarehouseId);
        if ((int) $wh->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        DB::transaction(function () use ($request, $adjustment, $branchId, $newWarehouseId) {

            // ✅ Guard: kalau defect/damaged dari adjustment ini sudah pernah moved_out, jangan boleh update
            $usedDefect = ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->whereNotNull('moved_out_at')
                ->exists();

            $usedDamaged = ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->whereNotNull('moved_out_at')
                ->exists();

            if ($usedDefect || $usedDamaged) {
                throw new \RuntimeException("Cannot update this adjustment because some DEFECT/DAMAGED items from this adjustment have been moved out (already used in another transaction).");
            }

            $reference = (string) $adjustment->reference;

            // rollback mutation lama
            $this->mutationController->rollbackByReference($reference, 'Adjustment');

            // ✅ hapus defect/damaged rows yang dibuat oleh adjustment ini (karena belum moved_out)
            ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            // hapus detail lama
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // update header
            $adjustment->update([
                'date'         => (string) $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $newWarehouseId,
            ]);

            foreach ($request->product_ids as $key => $productId) {

                $productId  = (int) $productId;
                $rackId     = (int) ($request->rack_ids[$key] ?? 0);
                $qty        = (int) ($request->quantities[$key] ?? 0);
                $type       = (string) ($request->types[$key] ?? '');
                $condition  = strtolower((string) ($request->conditions[$key] ?? 'good'));
                $itemNote   = $request->notes[$key] ?? null;

                $defectType = trim((string) ($request->defect_types[$key] ?? ''));
                $damagedReason = trim((string) ($request->damaged_reasons[$key] ?? ''));

                if ($rackId <= 0) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Rack is required for every item.')
                    );
                }

                $this->assertRackBelongsToWarehouse($rackId, $newWarehouseId);

                if ($qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Invalid item data.')
                    );
                }

                if (!in_array($condition, ['good', 'defect', 'damaged'], true)) {
                    $condition = 'good';
                }

                Product::findOrFail($productId);

                // ✅ validate extra fields for ADD only
                if ($type === 'add' && $condition === 'defect' && $defectType === '') {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Defect Type is required when condition = DEFECT (Add).')
                    );
                }
                if ($type === 'add' && $condition === 'damaged' && $damagedReason === '') {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Damaged Reason is required when condition = DAMAGED (Add).')
                    );
                }

                // ✅ save adjusted_products (log)
                $adjustedNotePieces = [];
                if (!empty($itemNote)) $adjustedNotePieces[] = "Item: {$itemNote}";
                $adjustedNotePieces[] = 'COND=' . strtoupper($condition);
                if ($type === 'add' && $condition === 'defect' && $defectType !== '') $adjustedNotePieces[] = "DEFECT_TYPE={$defectType}";
                if ($type === 'add' && $condition === 'damaged' && $damagedReason !== '') $adjustedNotePieces[] = "REASON={$damagedReason}";
                $adjustedNote = trim(implode(' | ', $adjustedNotePieces));

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $productId,
                    'rack_id'       => $rackId,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'note'          => $adjustedNote ?: null,
                ]);

                $mutationType = $type === 'add' ? 'In' : 'Out';
                $bucketLabel  = strtoupper($condition); // GOOD/DEFECT/DAMAGED

                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | ' . $adjustment->note : '')
                    . ($itemNote ? ' | Item: ' . $itemNote : '')
                    . ' | ' . $bucketLabel
                );

                // =========================================================
                // CASE A: GOOD
                // =========================================================
                if ($condition === 'good') {
                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        $mutationType,
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    continue;
                }

                // =========================================================
                // CASE B: DEFECT / DAMAGED
                // =========================================================

                if ($type === 'sub') {

                    if ($condition === 'defect') {
                        $items = ProductDefectItem::query()
                            ->where('branch_id', $branchId)
                            ->where('warehouse_id', $newWarehouseId)
                            ->where('rack_id', $rackId)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->orderBy('id')
                            ->limit($qty)
                            ->lockForUpdate()
                            ->get();

                        if ($items->count() < $qty) {
                            throw new \RuntimeException("Not enough DEFECT items to SUB. Need {$qty}, have {$items->count()}.");
                        }

                        $this->mutationController->applyInOut(
                            $branchId,
                            $newWarehouseId,
                            $productId,
                            'Out',
                            $qty,
                            $reference,
                            $mutationNote,
                            (string) $request->date,
                            $rackId
                        );

                        foreach ($items as $it) {
                            $it->update([
                                'moved_out_at'             => now(),
                                'moved_out_by'             => (int) Auth::id(),
                                'moved_out_reference_type' => Adjustment::class,
                                'moved_out_reference_id'   => (int) $adjustment->id,
                            ]);
                        }

                        continue;
                    }

                    $items = ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('warehouse_id', $newWarehouseId)
                        ->where('rack_id', $rackId)
                        ->where('product_id', $productId)
                        ->whereNull('moved_out_at')
                        ->orderBy('id')
                        ->limit($qty)
                        ->lockForUpdate()
                        ->get();

                    if ($items->count() < $qty) {
                        throw new \RuntimeException("Not enough DAMAGED items to SUB. Need {$qty}, have {$items->count()}.");
                    }

                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'Out',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    foreach ($items as $it) {
                        $it->update([
                            'moved_out_at'             => now(),
                            'moved_out_by'             => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id'   => (int) $adjustment->id,
                            'mutation_out_id'          => (int) $mutationOutId,
                        ]);
                    }

                    continue;
                }

                // ADD defect
                if ($condition === 'defect') {

                    $this->mutationController->applyInOut(
                        $branchId,
                        $newWarehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $mutationNote,
                        (string) $request->date,
                        $rackId
                    );

                    for ($i = 0; $i < $qty; $i++) {
                        ProductDefectItem::create([
                            'branch_id'       => $branchId,
                            'warehouse_id'    => $newWarehouseId,
                            'rack_id'         => $rackId,
                            'product_id'      => $productId,
                            'reference_id'    => (int) $adjustment->id,
                            'reference_type'  => Adjustment::class,
                            'quantity'        => 1,
                            'defect_type'     => $defectType,
                            'description'     => $itemNote,
                            'photo_path'      => null,
                            'created_by'      => (int) Auth::id(),
                        ]);
                    }

                    continue;
                }

                // ADD damaged
                $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                    $branchId,
                    $newWarehouseId,
                    $productId,
                    'In',
                    $qty,
                    $reference,
                    $mutationNote,
                    (string) $request->date,
                    $rackId
                );

                for ($i = 0; $i < $qty; $i++) {
                    ProductDamagedItem::create([
                        'branch_id'        => $branchId,
                        'warehouse_id'     => $newWarehouseId,
                        'rack_id'          => $rackId,
                        'product_id'       => $productId,
                        'reference_id'     => (int) $adjustment->id,
                        'reference_type'   => Adjustment::class,
                        'quantity'         => 1,
                        'damage_type'      => 'damaged',
                        'reason'           => $damagedReason,
                        'photo_path'       => null,
                        'cause'            => null,
                        'responsible_user_id' => null,
                        'resolution_status'   => 'pending',
                        'resolution_note'     => null,
                        'mutation_in_id'      => (int) $mutationInId,
                        'mutation_out_id'     => null,
                        'created_by'          => (int) Auth::id(),
                    ]);
                }
            }
        });

        toast('Adjustment Updated!', 'info');
        return redirect()->route('adjustments.index');
    }

    public function destroy(Adjustment $adjustment)
    {
        abort_if(Gate::denies('delete_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('adjustments.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to delete an adjustment.");
        }
        $branchId = (int) $active;

        // (optional tapi aman) pastikan milik branch aktif
        if ((int) $adjustment->branch_id !== $branchId) {
            abort(403, 'You can only delete adjustments from the active branch.');
        }

        // ✅ Guard: kalau ada unit defect/damaged dari adjustment ini yang sudah moved out → BLOCK DELETE
        $usedDefect = ProductDefectItem::query()
            ->where('reference_type', Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->whereNotNull('moved_out_at')
            ->exists();

        $usedDamaged = ProductDamagedItem::query()
            ->where('reference_type', Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->whereNotNull('moved_out_at')
            ->exists();

        if ($usedDefect || $usedDamaged) {
            toast("Cannot delete: some DEFECT/DAMAGED units from this adjustment have been moved out (already used in another transaction).", 'error');
            return redirect()->back();
        }

        DB::transaction(function () use ($adjustment) {

            // rollback mutation untuk kembalikan stock rack bucket
            $this->mutationController->rollbackByReference((string) $adjustment->reference, 'Adjustment');

            // ✅ hapus per-unit rows defect/damaged yang dibuat oleh adjustment ini (karena belum moved_out)
            ProductDefectItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            ProductDamagedItem::query()
                ->where('reference_type', Adjustment::class)
                ->where('reference_id', (int) $adjustment->id)
                ->delete();

            // hapus detail log
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // hapus header
            $adjustment->delete();
        });

        toast('Adjustment Deleted!', 'warning');
        return redirect()->route('adjustments.index');
    }

    /**
     * ✅ QUALITY RECLASS + MUTATION LOG (NET ZERO) + SAVE TO ADJUSTMENT INDEX
     * - bikin Adjustment (reference = QRC-xxx) supaya muncul di list Adjustments
     * - bikin AdjustedProduct 1 row (log tampilan)
     * - bikin ProductDefectItem / ProductDamagedItem per unit
     * - upload image per unit
     * - bikin mutation log NET-ZERO (IN lalu OUT qty sama)
     *
     * NOTE: method name jangan diganti.
     */
    public function storeQuality(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            if (!$request->ajax() && !$request->wantsJson() && !$request->expectsJson()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Please choose a specific branch first (not 'All Branch') to submit quality reclass.");
            }

            return response()->json([
                'success' => false,
                'message' => "Please choose a specific branch first (not 'All Branch') to submit quality reclass."
            ], 422);
        }
        $branchId = (int) $active;

        $request->validate([
            'date'         => 'required|date',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'rack_id'      => 'required|integer|exists:racks,id',
            'type'         => 'required|in:defect,damaged,defect_to_good,damaged_to_good',
            'qty'          => 'required|integer|min:1|max:500',
            'product_id'   => 'required|integer|exists:products,id',
            'units'        => 'required|array|min:1',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $rackId      = (int) $request->rack_id;
        $productId   = (int) $request->product_id;
        $type        = (string) $request->type;
        $qty         = (int) $request->qty;
        $date        = (string) $request->date;

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        // ✅ rack belongs to warehouse
        $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

        if (!is_array($request->units) || count($request->units) !== $qty) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Per-unit detail must match Qty. Qty={$qty}, Units=" . (is_array($request->units) ? count($request->units) : 0));
        }

        $isToGood = in_array($type, ['defect_to_good', 'damaged_to_good'], true);

        // validate per unit + image
        for ($i = 0; $i < $qty; $i++) {
            $unit = (array) $request->units[$i];

            if (!$isToGood) {
                if ($type === 'defect') {
                    $defType = trim((string) ($unit['defect_type'] ?? ''));
                    if ($defType === '') {
                        return redirect()->back()->withInput()->with('error', "Defect Type is required for each unit (row #" . ($i + 1) . ").");
                    }
                } else {
                    $reason = trim((string) ($unit['reason'] ?? ''));
                    if ($reason === '') {
                        return redirect()->back()->withInput()->with('error', "Damaged Reason is required for each unit (row #" . ($i + 1) . ").");
                    }
                }
            } else {
                $resReason = trim((string) ($unit['resolution_reason'] ?? ''));
                if ($resReason === '') {
                    return redirect()->back()->withInput()->with('error', "Resolution Reason is required for each unit (row #" . ($i + 1) . ").");
                }
            }

            $file = $request->file("units.$i.photo");
            if ($file) {
                $mime = strtolower((string) $file->getClientMimeType());
                if (!str_starts_with($mime, 'image/')) {
                    return redirect()->back()->withInput()->with('error', "Photo must be an image (row #" . ($i + 1) . ").");
                }
                if ($file->getSize() > (5 * 1024 * 1024)) {
                    return redirect()->back()->withInput()->with('error', "Photo max size is 5MB (row #" . ($i + 1) . ").");
                }
            }
        }

        DB::transaction(function () use ($branchId, $warehouseId, $rackId, $productId, $type, $qty, $date, $request) {

            Product::findOrFail($productId);

            $noteHuman = match ($type) {
                'defect' => 'Quality Reclass (GOOD → DEFECT)',
                'damaged' => 'Quality Reclass (GOOD → DAMAGED)',
                'defect_to_good' => 'Quality Reclass (DEFECT → GOOD) [DELETE]',
                'damaged_to_good' => 'Quality Reclass (DAMAGED → GOOD) [DELETE]',
                default => "Quality Reclass ({$type})",
            };

            // simpan alasan per-unit ke note adjustment supaya histori tetap ada walau row defect/damaged dihapus
            $reasons = [];
            for ($i = 0; $i < $qty; $i++) {
                $unit = (array) $request->units[$i];
                if ($type === 'defect') {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['defect_type'] ?? ''));
                } elseif ($type === 'damaged') {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['reason'] ?? ''));
                } else {
                    $reasons[] = ($i + 1) . '. ' . trim((string) ($unit['resolution_reason'] ?? ''));
                }
            }

            $reasonText = trim(implode(' | ', $reasons));
            if (strlen($reasonText) > 900) {
                $reasonText = substr($reasonText, 0, 900) . '...';
            }

            $ref = $this->generateQualityReclassReference($type);

            $adjustment = Adjustment::create([
                'reference'    => $ref,
                'date'         => $date,
                'note'         => $noteHuman . ($reasonText ? " | {$reasonText}" : ''),
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
                'created_by'   => (int) Auth::id(),
            ]);

            AdjustedProduct::create([
                'adjustment_id' => $adjustment->id,
                'product_id'    => $productId,
                'rack_id'       => $rackId,
                'quantity'      => $qty,
                'type'          => 'sub',
                'note'          => $noteHuman . ' | NET-ZERO bucket log',
            ]);

            // ✅ NET-ZERO but bucket-aware mutations
            // mapping:
            // defect: OUT GOOD, IN DEFECT
            // damaged: OUT GOOD, IN DAMAGED
            // defect_to_good: OUT DEFECT, IN GOOD
            // damaged_to_good: OUT DAMAGED, IN GOOD
            $noteBase = $noteHuman . " | PID {$productId} | WH {$warehouseId} | RACK {$rackId} | By UID " . (int) Auth::id();

            $outBucket = 'GOOD';
            $inBucket  = 'DEFECT';

            if ($type === 'damaged') {
                $outBucket = 'GOOD';
                $inBucket  = 'DAMAGED';
            }

            if ($type === 'defect_to_good') {
                $outBucket = 'DEFECT';
                $inBucket  = 'GOOD';
            }

            if ($type === 'damaged_to_good') {
                $outBucket = 'DAMAGED';
                $inBucket  = 'GOOD';
            }

            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'Out',
                $qty,
                $ref,
                $noteBase . " | OUT | {$outBucket}",
                $date,
                $rackId
            );

            $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $qty,
                $ref,
                $noteBase . " | IN | {$inBucket}",
                $date,
                $rackId
            );

            // ==========================================================
            // A) GOOD -> DEFECT / DAMAGED : create per-unit rows with rack_id
            // ==========================================================
            if ($type === 'defect' || $type === 'damaged') {

                for ($i = 0; $i < $qty; $i++) {
                    $unit = (array) $request->units[$i];

                    $photoPath = null;
                    $photoFile = $request->file("units.$i.photo");
                    if ($photoFile) {
                        $photoPath = $this->storeQualityImage($photoFile, $type);
                    }

                    if ($type === 'defect') {
                        ProductDefectItem::create([
                            'branch_id'      => $branchId,
                            'warehouse_id'   => $warehouseId,
                            'rack_id'        => $rackId,
                            'product_id'     => $productId,
                            'reference_id'   => $adjustment->id,
                            'reference_type' => Adjustment::class,
                            'quantity'       => 1,
                            'defect_type'    => trim((string) ($unit['defect_type'] ?? '')),
                            'description'    => trim((string) ($unit['description'] ?? '')) ?: null,
                            'photo_path'     => $photoPath,
                            'created_by'     => (int) Auth::id(),
                        ]);
                    } else {
                        ProductDamagedItem::create([
                            'branch_id'       => $branchId,
                            'warehouse_id'    => $warehouseId,
                            'rack_id'         => $rackId,
                            'product_id'      => $productId,
                            'reference_id'    => $adjustment->id,
                            'reference_type'  => Adjustment::class,
                            'quantity'        => 1,
                            'reason'          => trim((string) ($unit['reason'] ?? '')),
                            'photo_path'      => $photoPath,
                            'created_by'      => (int) Auth::id(),
                            'mutation_in_id'  => (int) $mutationInId,   // ✅ IN DAMAGED
                            'mutation_out_id' => null,                  // out nanti saat moved_out
                        ]);
                    }
                }

                return;
            }

            // ==========================================================
            // B) DEFECT -> GOOD (DELETE FIFO) + rack filter
            // ==========================================================
            if ($type === 'defect_to_good') {

                $ids = ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->limit($qty)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->toArray();

                if (count($ids) < $qty) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', "Not enough DEFECT units in this rack to reclass to GOOD. Available=" . count($ids) . ", Needed={$qty}.")
                    );
                }

                ProductDefectItem::query()
                    ->whereIn('id', $ids)
                    ->delete();

                return;
            }

            // ==========================================================
            // C) DAMAGED -> GOOD (DELETE FIFO) + rack filter
            // ==========================================================
            if ($type === 'damaged_to_good') {

                $ids = ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('rack_id', $rackId)
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->limit($qty)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->toArray();

                if (count($ids) < $qty) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', "Not enough DAMAGED units in this rack to reclass to GOOD. Available=" . count($ids) . ", Needed={$qty}.")
                    );
                }

                ProductDamagedItem::query()
                    ->whereIn('id', $ids)
                    ->delete();

                return;
            }
        });

        toast('Quality reclass saved successfully.', 'success');

        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Quality reclass saved successfully.',
            ]);
        }

        return redirect()->route('adjustments.index');
    }

    private function generateQualityReclassReference(string $type): string
    {
        $t = strtoupper(substr($type, 0, 3)); // DEF / DAM
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return 'QRC-' . $t . '-' . now()->format('Ymd-His') . '-' . $rand;
    }

    private function storeQualityImage($file, string $type): string
    {
        // mapping type -> folder
        $folder = 'quality/misc';

        if (in_array($type, ['defect', 'defect_to_good'], true)) {
            $folder = 'quality/defects';
        } elseif (in_array($type, ['damaged', 'damaged_to_good'], true)) {
            $folder = 'quality/damaged';
        }

        return $file->store($folder, 'public');
    }

    public function qualityProducts(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please select a specific branch (not All Branch)."
            ], 422);
        }

        $branchId    = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');
        $rackId      = (int) $request->query('rack_id', 0); // optional

        // purpose: good | defect | damaged | total
        $purpose = strtolower((string) $request->query('purpose', 'good'));
        if (!in_array($purpose, ['good', 'defect', 'damaged', 'total'], true)) {
            $purpose = 'good';
        }

        if ($warehouseId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'warehouse_id is required.'
            ], 422);
        }

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        $q = DB::table('stock_racks')
            ->leftJoin('products', 'products.id', '=', 'stock_racks.product_id')
            ->where('stock_racks.branch_id', $branchId)
            ->where('stock_racks.warehouse_id', $warehouseId);

        if ($rackId > 0) {
            $q->where('stock_racks.rack_id', $rackId);
        }

        $rows = $q->selectRaw('
                stock_racks.product_id,
                COALESCE(SUM(stock_racks.qty_available), 0) as qty_available,
                COALESCE(SUM(stock_racks.qty_good), 0) as good_qty,
                COALESCE(SUM(stock_racks.qty_defect), 0) as defect_qty,
                COALESCE(SUM(stock_racks.qty_damaged), 0) as damaged_qty,
                MAX(products.product_code) as product_code,
                MAX(products.product_name) as product_name
            ')
            ->groupBy('stock_racks.product_id')
            ->orderByRaw('MAX(products.product_name)')
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $goodQty    = (int) ($r->good_qty ?? 0);
            $defectQty  = (int) ($r->defect_qty ?? 0);
            $damagedQty = (int) ($r->damaged_qty ?? 0);

            // ✅ total available = good + defect + damaged (atau pakai qty_available jika sudah benar)
            $totalAvailable = $goodQty + $defectQty + $damagedQty;
            $qtyAvailableCol = (int) ($r->qty_available ?? 0);
            if ($qtyAvailableCol > 0) {
                $totalAvailable = $qtyAvailableCol;
            }

            $availableQty = 0;
            $badgeText = '';

            if ($purpose === 'good') {
                $availableQty = $goodQty;
                $badgeText = 'GOOD';
            } elseif ($purpose === 'defect') {
                $availableQty = $defectQty;
                $badgeText = 'DEFECT';
            } elseif ($purpose === 'damaged') {
                $availableQty = $damagedQty;
                $badgeText = 'DAMAGED';
            } else { // total
                $availableQty = $totalAvailable;
                $badgeText = 'TOTAL';
            }

            if ($availableQty <= 0) {
                continue;
            }

            $label =
                trim(($r->product_code ?? '') . ' ' . ($r->product_name ?? '')) .
                ' (' . $badgeText . ': ' . number_format($availableQty) . ')';

            $data[] = [
                'id'            => (int) $r->product_id,
                'text'          => $label,
                'available_qty' => $availableQty,

                // extra info
                'total_available' => $totalAvailable,
                'good_qty'      => $goodQty,
                'defect_qty'    => $defectQty,
                'damaged_qty'   => $damagedQty,
            ];
        }

        return response()->json([
            'success'      => true,
            'purpose'      => $purpose,
            'warehouse_id' => $warehouseId,
            'rack_id'      => $rackId > 0 ? $rackId : null,
            'data'         => $data,
        ]);
    }

    public function racks(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return response()->json([
                'success' => false,
                'message' => "Please select a specific branch (not All Branch)."
            ], 422);
        }

        $branchId = (int) $active;
        $warehouseId = (int) $request->query('warehouse_id');

        if ($warehouseId <= 0) {
            return response()->json([
                'success' => false,
                'message' => "warehouse_id is required."
            ], 422);
        }

        $wh = Warehouse::findOrFail($warehouseId);
        if ((int)$wh->branch_id !== $branchId) {
            return response()->json([
                'success' => false,
                'message' => "Warehouse must belong to active branch."
            ], 403);
        }

        $rows = DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $data = [];
        foreach ($rows as $r) {
            $label = trim((string)($r->code ?? '')) . ' - ' . trim((string)($r->name ?? ''));
            $data[] = [
                'id' => (int)$r->id,
                'label' => trim($label, ' -'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function assertRackBelongsToWarehouse(int $rackId, int $warehouseId): void
    {
        $ok = DB::table('racks')
            ->where('id', $rackId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (!$ok) {
            abort(422, "Invalid rack: rack_id={$rackId} does not belong to warehouse_id={$warehouseId}.");
        }
    }
}
