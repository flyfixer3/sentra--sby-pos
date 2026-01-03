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

        // IMPORTANT: reference TIDAK divalidasi, karena auto-generate di model Adjustment::boot()
        $request->validate([
            'date'          => 'required|date',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'note'          => 'nullable|string|max:1000',

            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',

            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array|min:1',
            'types.*'       => 'required|in:add,sub',

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

            // setelah create, reference sudah ter-generate otomatis
            $reference = (string) $adjustment->reference;

            foreach ($request->product_ids as $key => $productId) {

                $productId = (int) $productId;
                $qty       = (int) ($request->quantities[$key] ?? 0);
                $type      = (string) ($request->types[$key] ?? '');
                $itemNote  = $request->notes[$key] ?? null;

                if ($qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    // validasi item buruk -> redirect back (biar konsisten)
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Invalid item data.')
                    );
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

                // note diawali "Adjustment" supaya rollbackByReference(..., 'Adjustment') aman
                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | ' . $adjustment->note : '')
                    . ($itemNote ? ' | Item: ' . $itemNote : '')
                );

                $this->mutationController->applyInOut(
                    $branchId,
                    $warehouseId,
                    $productId,
                    $mutationType,
                    $qty,
                    $reference,
                    $mutationNote,
                    (string) $request->date
                );
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

        // IMPORTANT: reference jangan divalidasi & jangan diupdate (biar rollback by reference aman)
        $request->validate([
            'date'          => 'required|date',
            'warehouse_id'  => 'required|integer|exists:warehouses,id',
            'note'          => 'nullable|string|max:1000',

            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',

            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'required|integer|min:1',

            'types'         => 'required|array|min:1',
            'types.*'       => 'required|in:add,sub',

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

        // (optional tapi bagus) pastikan adjustment ini milik branch aktif
        if ((int) $adjustment->branch_id !== $branchId) {
            abort(403, 'You can only update adjustments from the active branch.');
        }

        $newWarehouseId = (int) $request->warehouse_id;

        $wh = Warehouse::findOrFail($newWarehouseId);
        if ((int) $wh->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        DB::transaction(function () use ($request, $adjustment, $branchId, $newWarehouseId) {

            $reference = (string) $adjustment->reference;

            // 1) rollback mutation lama
            $this->mutationController->rollbackByReference($reference, 'Adjustment');

            // 2) hapus detail lama
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();

            // 3) update header (reference tetap)
            $adjustment->update([
                'date'         => (string) $request->date,
                'note'         => $request->note,
                'branch_id'    => $branchId,
                'warehouse_id' => $newWarehouseId,
            ]);

            // 4) insert detail baru + mutation baru
            foreach ($request->product_ids as $key => $productId) {

                $productId = (int) $productId;
                $qty       = (int) ($request->quantities[$key] ?? 0);
                $type      = (string) ($request->types[$key] ?? '');
                $itemNote  = $request->notes[$key] ?? null;

                if ($qty <= 0 || !in_array($type, ['add', 'sub'], true)) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        redirect()->back()->withInput()->with('error', 'Invalid item data.')
                    );
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

                $mutationNote = trim(
                    'Adjustment #' . $adjustment->id
                    . ($adjustment->note ? ' | ' . $adjustment->note : '')
                    . ($itemNote ? ' | Item: ' . $itemNote : '')
                );

                $this->mutationController->applyInOut(
                    $branchId,
                    $newWarehouseId,
                    $productId,
                    $mutationType,
                    $qty,
                    $reference,
                    $mutationNote,
                    (string) $request->date
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
            $this->mutationController->rollbackByReference((string) $adjustment->reference, 'Adjustment');
            AdjustedProduct::where('adjustment_id', $adjustment->id)->delete();
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
            // kalau form normal -> redirect back
            if (!$request->ajax() && !$request->wantsJson() && !$request->expectsJson()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Please choose a specific branch first (not 'All Branch') to submit quality reclass.");
            }

            // kalau ajax/json -> tetap json
            return response()->json([
                'success' => false,
                'message' => "Please choose a specific branch first (not 'All Branch') to submit quality reclass."
            ], 422);
        }
        $branchId = (int) $active;

        $request->validate([
            'date'         => 'required|date',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'type'         => 'required|in:defect,damaged',
            'qty'          => 'required|integer|min:1|max:500',
            'product_id'   => 'required|integer|exists:products,id',
            'units'        => 'required|array|min:1',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $productId   = (int) $request->product_id;
        $type        = (string) $request->type;
        $qty         = (int) $request->qty;
        $date        = (string) $request->date;

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        if (!is_array($request->units) || count($request->units) !== $qty) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Per-unit detail must match Qty. Qty={$qty}, Units=" . (is_array($request->units) ? count($request->units) : 0));
        }

        // validate per unit + image
        for ($i = 0; $i < $qty; $i++) {
            if ($type === 'defect') {
                $defType = trim((string) data_get($request->units[$i], 'defect_type', ''));
                if ($defType === '') {
                    return redirect()->back()->withInput()->with('error', "Defect Type is required for each unit (row #" . ($i + 1) . ").");
                }
            } else {
                $reason = trim((string) data_get($request->units[$i], 'reason', ''));
                if ($reason === '') {
                    return redirect()->back()->withInput()->with('error', "Damaged Reason is required for each unit (row #" . ($i + 1) . ").");
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

        DB::transaction(function () use ($branchId, $warehouseId, $productId, $type, $qty, $date, $request) {

            Product::findOrFail($productId);

            $ref = $this->generateQualityReclassReference($type);

            $adjustment = Adjustment::create([
                'reference'    => $ref,
                'date'         => $date,
                'note'         => "Quality Reclass ({$type})",
                'branch_id'    => $branchId,
                'warehouse_id' => $warehouseId,
                'created_by'   => (int) Auth::id(),
            ]);

            AdjustedProduct::create([
                'adjustment_id' => $adjustment->id,
                'product_id'    => $productId,
                'quantity'      => $qty,
                'type'          => 'sub',
                'note'          => "Quality Reclass ({$type}) | NET-ZERO log",
            ]);

            $noteBase = "Quality Reclass ({$type}) | PID {$productId} | WH {$warehouseId} | By UID " . (int) Auth::id();

            $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $qty,
                $ref,
                $noteBase . " | LOG-IN (virtual)",
                $date
            );

            $mutationOutId = $this->mutationController->applyInOutAndGetMutationId(
                $branchId,
                $warehouseId,
                $productId,
                'Out',
                $qty,
                $ref,
                $noteBase . " | LOG-OUT (virtual)",
                $date
            );

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
                        'product_id'      => $productId,
                        'reference_id'    => $adjustment->id,
                        'reference_type'  => Adjustment::class,
                        'quantity'        => 1,
                        'reason'          => trim((string) ($unit['reason'] ?? '')),
                        'photo_path'      => $photoPath,
                        'created_by'      => (int) Auth::id(),
                        'mutation_in_id'  => (int) $mutationInId,
                        'mutation_out_id' => (int) $mutationOutId,
                    ]);
                }
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
        $folder = $type === 'defect' ? 'quality/defects' : 'quality/damaged';
        return $file->store($folder, 'public');
    }

    public function qualityProducts(Request $request)
    {
        abort_if(Gate::denies('create_adjustments'), 403);

        $active = session('active_branch');
        if ($active === 'all' || !$active) {
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
                'message' => 'warehouse_id is required.'
            ], 422);
        }

        $warehouse = Warehouse::findOrFail($warehouseId);
        if ((int) $warehouse->branch_id !== $branchId) {
            abort(403, 'Warehouse must belong to active branch.');
        }

        // defect sub
        $defectSub = DB::table('product_defect_items')
            ->selectRaw('product_id, SUM(quantity) as defect_qty')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->groupBy('product_id');

        // damaged sub
        $damagedSub = DB::table('product_damaged_items')
            ->selectRaw('product_id, SUM(quantity) as damaged_qty')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->groupBy('product_id');

        $rows = DB::table('stocks')
            ->selectRaw('
                stocks.product_id,
                SUM(stocks.qty_available) as total_qty,
                COALESCE(defect.defect_qty,0) as defect_qty,
                COALESCE(damaged.damaged_qty,0) as damaged_qty,
                GREATEST(
                    SUM(stocks.qty_available)
                    - COALESCE(defect.defect_qty,0)
                    - COALESCE(damaged.damaged_qty,0),
                0) as good_qty,
                MAX(products.product_code) as product_code,
                MAX(products.product_name) as product_name
            ')
            ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoinSub($defectSub, 'defect', fn($j) => $j->on('defect.product_id','=','stocks.product_id'))
            ->leftJoinSub($damagedSub,'damaged',fn($j)=> $j->on('damaged.product_id','=','stocks.product_id'))
            ->where('stocks.branch_id', $branchId)
            ->where('stocks.warehouse_id', $warehouseId)
            ->groupBy('stocks.product_id')
            ->orderByRaw('MAX(products.product_name)')
            ->get();

        $data = [];
        foreach ($rows as $r) {
            if ((int)$r->good_qty <= 0) continue;

            $label =
                trim(($r->product_code ?? '').' '.($r->product_name ?? '')) .
                ' (GOOD: '.number_format($r->good_qty).')';

            $data[] = [
                'id'       => (int) $r->product_id,
                'text'     => $label,
                'good_qty' => (int) $r->good_qty,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
