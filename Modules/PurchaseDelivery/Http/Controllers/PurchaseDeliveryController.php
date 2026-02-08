<?php

namespace Modules\PurchaseDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\Inventory\Entities\Rack;
use Modules\PurchaseDelivery\DataTables\PurchaseDeliveriesDataTable;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Entities\PurchaseOrderDetails;
use Modules\Product\Entities\Warehouse;
use Modules\Inventory\Entities\StockRack;

use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;

use Modules\Mutation\Entities\Mutation;
use Modules\Mutation\Http\Controllers\MutationController;

class PurchaseDeliveryController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    public function index(PurchaseDeliveriesDataTable $dataTable)
    {
        abort_if(Gate::denies('access_purchase_deliveries'), 403);
        return $dataTable->render('purchase-deliveries::index');
    }

    protected function activeBranch(): mixed
    {
        return session('active_branch');
    }

    protected function activeBranchIdOrFail(string $redirectRoute = 'purchase-deliveries.index'): int
    {
        $active = $this->activeBranch();

        if ($active === 'all' || $active === null || $active === '') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()
                    ->route($redirectRoute)
                    ->with('error', "Please choose a specific branch first (not 'All Branch').")
            );
        }

        return (int) $active;
    }

    protected function roleString(): string
    {
        $user = auth()->user();
        if (!$user) return '-';

        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->toArray()
            : [];

        $roles = array_values(array_filter(array_map(fn ($r) => trim((string) $r), $roles)));

        return count($roles) ? implode(', ', $roles) : '-';
    }

    /**
     * =====================
     * CREATE (FORM)
     * =====================
     */
    public function create(PurchaseOrder $purchaseOrder)
    {
        abort_if(Gate::denies('create_purchase_deliveries'), 403);

        $branchId = $this->activeBranchIdOrFail('purchase-orders.index');

        if ((int) $purchaseOrder->branch_id !== $branchId) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This PO belongs to a different branch than the active branch.');
        }

        $remainingItems = $purchaseOrder->purchaseOrderDetails()
            ->whereColumn('quantity', '>', 'fulfilled_quantity')
            ->get();

        if ($remainingItems->isEmpty()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This Purchase Order is already fully fulfilled. No remaining items to deliver.');
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId =
            optional($warehouses->firstWhere('is_main', true))->id
            ?? optional($warehouses->first())->id;

        return view('purchase-deliveries::create', [
            'purchaseOrder'      => $purchaseOrder->load(['supplier', 'purchaseOrderDetails']),
            'remainingItems'     => $remainingItems,
            'warehouses'         => $warehouses,
            'defaultWarehouseId' => $defaultWarehouseId,
        ]);
    }

    /**
     * =====================
     * STORE (EXPECTED ONLY)
     * =====================
     */
    public function store(Request $request)
    {
        abort_if(Gate::denies('create_purchase_deliveries'), 403);

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id'      => 'required|exists:warehouses,id',
            'date'              => 'required|date',
            'ship_via'          => 'nullable|string|max:100',
            'tracking_number'   => 'nullable|string|max:100',
            'note'              => 'nullable|string|max:1000',
            'quantity'          => 'required|array|min:1',
            'quantity.*'        => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {

            $purchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($request->purchase_order_id);

            $wh = Warehouse::findOrFail((int) $request->warehouse_id);
            if ((int) $wh->branch_id !== (int) $purchaseOrder->branch_id) {
                abort(403, 'Warehouse must belong to the same branch as the PO.');
            }

            $note = $request->note ? (string) $request->note : null;

            $delivery = PurchaseDelivery::create([
                'purchase_order_id' => (int) $purchaseOrder->id,
                'branch_id'         => (int) $purchaseOrder->branch_id,
                'warehouse_id'      => (int) $request->warehouse_id,
                'date'              => (string) $request->date,
                'note'              => $note,
                'ship_via'          => $request->ship_via,
                'tracking_number'   => $request->tracking_number,
                'status'            => 'Pending',
                'created_by'        => auth()->id(),

                'note_updated_by'   => $note ? auth()->id() : null,
                'note_updated_role' => $note ? $this->roleString() : null,
                'note_updated_at'   => $note ? now() : null,
            ]);

            $hasItem = false;

            foreach ($request->quantity as $poDetailId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) continue;

                $poDetail = PurchaseOrderDetails::where('purchase_order_id', (int) $purchaseOrder->id)
                    ->where('id', (int) $poDetailId)
                    ->firstOrFail();

                PurchaseDeliveryDetails::create([
                    'purchase_delivery_id' => (int) $delivery->id,
                    'product_id'           => (int) $poDetail->product_id,
                    'product_name'         => (string) $poDetail->product_name,
                    'product_code'         => (string) $poDetail->product_code,
                    'quantity'             => (int) $qty,
                    'qty_received'         => 0,
                    'qty_defect'           => 0,
                    'qty_damaged'          => 0,
                ]);

                $hasItem = true;
            }

            if (!$hasItem) {
                throw new \RuntimeException('Please input at least one item.');
            }
        });

        return redirect()
            ->route('purchase-deliveries.index')
            ->with('success', 'Purchase Delivery created. Please confirm received items.');
    }

    /**
     * =====================
     * EDIT (PENDING ONLY)
     * =====================
     */
    public function edit(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('edit_purchase_deliveries'), 403);

        $purchaseDelivery->loadMissing(['warehouse', 'purchaseOrder', 'branch']);

        if (strtolower((string) $purchaseDelivery->status) !== 'pending') {
            return redirect()
                ->route('purchase-deliveries.show', $purchaseDelivery->id)
                ->with('error', 'Only Pending Purchase Delivery can be edited.');
        }

        $branchId = $this->activeBranchIdOrFail('purchase-deliveries.index');

        if ((int) $purchaseDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Active branch mismatch for this Purchase Delivery.');
        }

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        return view('purchase-deliveries::edit', [
            'purchaseDelivery' => $purchaseDelivery,
            'warehouses'       => $warehouses,
        ]);
    }

    public function update(Request $request, PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('edit_purchase_deliveries'), 403);

        $purchaseDelivery->loadMissing(['purchaseOrder', 'warehouse']);

        if (strtolower((string) $purchaseDelivery->status) !== 'pending') {
            return redirect()
                ->route('purchase-deliveries.show', $purchaseDelivery->id)
                ->with('error', 'Only Pending Purchase Delivery can be edited.');
        }

        $branchId = $this->activeBranchIdOrFail('purchase-deliveries.index');

        if ((int) $purchaseDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Active branch mismatch for this Purchase Delivery.');
        }

        $request->validate([
            'warehouse_id'    => 'required|exists:warehouses,id',
            'date'            => 'required|date',
            'ship_via'        => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'note'            => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $purchaseDelivery) {

            $purchaseDelivery = PurchaseDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($purchaseDelivery->id);

            $wh = Warehouse::findOrFail((int) $request->warehouse_id);
            if ((int) $wh->branch_id !== (int) $purchaseDelivery->branch_id) {
                abort(403, 'Warehouse must belong to the same branch as this Purchase Delivery.');
            }

            $note = $request->note ? (string) $request->note : null;

            $purchaseDelivery->update([
                'warehouse_id'    => (int) $request->warehouse_id,
                'date'            => (string) $request->date,
                'ship_via'        => $request->ship_via,
                'tracking_number' => $request->tracking_number,
                'note'            => $note,

                'note_updated_by'   => $note ? auth()->id() : null,
                'note_updated_role' => $note ? $this->roleString() : null,
                'note_updated_at'   => $note ? now() : null,
            ]);
        });

        return redirect()
            ->route('purchase-deliveries.show', $purchaseDelivery->id)
            ->with('success', 'Purchase Delivery updated successfully.');
    }

    /**
     * =====================
     * CONFIRM FORM
     * =====================
     * ✅ allow confirm multiple batches: Pending + Partial
     */
    public function confirm(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('confirm_purchase_deliveries'), 403);

        $status = $this->normalizeStatus((string) ($purchaseDelivery->status ?? 'pending'));

        // ✅ guard (UI page) — sekarang boleh confirm lagi selama masih ada remaining
        if (!in_array($status, ['pending', 'partial'], true)) {
            return redirect()
                ->route('purchase-deliveries.show', $purchaseDelivery->id)
                ->with('error', 'This delivery is already completed and can no longer be confirmed.');
        }

        $purchaseDelivery->load([
            'purchaseOrder',
            'purchase',
            'purchaseDeliveryDetails',
            'warehouse',
        ]);

        $racks = Rack::query()
            ->where('warehouse_id', (int) $purchaseDelivery->warehouse_id)
            ->orderByRaw("CASE WHEN code IS NULL OR code = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('code')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('purchase-deliveries::confirm', compact('purchaseDelivery', 'racks'));
    }

    private function upsertStockRack(
        int $branchId,
        int $warehouseId,
        int $rackId,
        int $productId,
        int $qtyAll,
        int $qtyGood,
        int $qtyDefect,
        int $qtyDamaged
    ): void {
        $row = StockRack::withoutGlobalScopes()->firstOrNew([
            'branch_id'    => $branchId,
            'warehouse_id' => $warehouseId,
            'rack_id'      => $rackId,
            'product_id'   => $productId,
        ]);

        $row->qty_available = (int) ($row->qty_available ?? 0);
        $row->qty_good      = (int) ($row->qty_good ?? 0);
        $row->qty_defect    = (int) ($row->qty_defect ?? 0);
        $row->qty_damaged   = (int) ($row->qty_damaged ?? 0);

        $row->qty_available += $qtyAll;
        $row->qty_good      += $qtyGood;
        $row->qty_defect    += $qtyDefect;
        $row->qty_damaged   += $qtyDamaged;

        if ($row->qty_available < 0) $row->qty_available = 0;
        if ($row->qty_good < 0)      $row->qty_good = 0;
        if ($row->qty_defect < 0)    $row->qty_defect = 0;
        if ($row->qty_damaged < 0)   $row->qty_damaged = 0;

        $row->save();
    }

    private function assertRackBelongsToWarehouse(int $rackId, int $warehouseId): void
    {
        $ok = Rack::query()
            ->where('id', $rackId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (!$ok) {
            throw new \RuntimeException("Invalid rack: rack_id={$rackId} does not belong to this warehouse.");
        }
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim((string) $status));
    }

    /**
     * =====================
     * CONFIRM STORE
     * =====================
     * ✅ Good allocation (split rack)
     * ✅ Anti double-confirm (race condition safe)
     */
   public function confirmStore(Request $request, PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('confirm_purchase_deliveries'), 403);

        $request->validate([
            'confirm_note' => 'nullable|string|max:1000',

            'items' => 'required|array|min:1',
            'items.*.detail_id'     => 'required|integer',
            'items.*.product_id'    => 'required|integer',
            'items.*.expected'      => 'required|integer|min:0',
            'items.*.already_confirmed' => 'required|integer|min:0',
            'items.*.remaining'     => 'required|integer|min:0',

            'items.*.add_good'      => 'required|integer|min:0',
            'items.*.add_defect'    => 'required|integer|min:0',
            'items.*.add_damaged'   => 'required|integer|min:0',

            'items.*.good_allocations' => 'nullable|array',
            'items.*.good_allocations.*.rack_id' => 'required_with:items.*.good_allocations|integer|min:1',
            'items.*.good_allocations.*.qty'     => 'required_with:items.*.good_allocations|integer|min:1',
            'items.*.good_allocations.*.note'    => 'nullable|string|max:255',

            'items.*.defects'        => 'nullable|array',
            'items.*.damaged_items'  => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $purchaseDelivery) {

            $purchaseDelivery = PurchaseDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($purchaseDelivery->id);

            $statusNow = $this->normalizeStatus((string) $purchaseDelivery->status);
            if (!in_array($statusNow, ['pending', 'partial'], true)) {
                abort(422, 'This delivery can no longer be confirmed.');
            }

            $purchaseDelivery->loadMissing([
                'purchaseOrder.purchaseOrderDetails',
                'purchaseDeliveryDetails',
                'warehouse',
            ]);

            $activeBranchId = $this->activeBranchIdOrFail('purchase-deliveries.index');
            if ((int) $purchaseDelivery->branch_id !== (int) $activeBranchId) {
                abort(403, 'Active branch mismatch for this Purchase Delivery.');
            }

            $wh = Warehouse::findOrFail((int) $purchaseDelivery->warehouse_id);
            if ((int) $wh->branch_id !== (int) $purchaseDelivery->branch_id) {
                abort(403, 'Warehouse must belong to the same branch as this Purchase Delivery.');
            }

            // ✅ batch ref
            $baseRef = 'PD-' . (int) $purchaseDelivery->id;
            $batchNo = (int) Mutation::withoutGlobalScopes()
                ->where('reference', 'like', $baseRef . '-B%')
                ->count() + 1;

            $reference = $baseRef . '-B' . $batchNo;

            $anyConfirmedInThisBatch = false;

            foreach ($request->items as $idx => $row) {

                $detailId  = (int) $row['detail_id'];
                $productId = (int) $row['product_id'];

                $expected  = (int) $row['expected'];
                $already   = (int) $row['already_confirmed'];
                $remainingClient = (int) $row['remaining'];

                $addGood   = (int) $row['add_good'];
                $addDefect = (int) $row['add_defect'];
                $addDamaged= (int) $row['add_damaged'];

                $batchTotal = $addGood + $addDefect + $addDamaged;

                if ($batchTotal <= 0) {
                    continue;
                }

                $anyConfirmedInThisBatch = true;

                $pdDetail = $purchaseDelivery->purchaseDeliveryDetails->firstWhere('id', $detailId);
                if (!$pdDetail) {
                    throw new \RuntimeException("PD Detail not found: {$detailId}");
                }

                $dbConfirmed = (int) ($pdDetail->qty_received ?? 0)
                            + (int) ($pdDetail->qty_defect ?? 0)
                            + (int) ($pdDetail->qty_damaged ?? 0);

                $dbRemaining = (int) $expected - (int) $dbConfirmed;
                if ($dbRemaining < 0) $dbRemaining = 0;

                if ($dbConfirmed !== $already) {
                    throw new \RuntimeException("Data changed, please reload page. (detail_id={$detailId})");
                }
                if ($dbRemaining !== $remainingClient) {
                    throw new \RuntimeException("Remaining changed, please reload page. (detail_id={$detailId})");
                }

                if ($batchTotal > $dbRemaining) {
                    throw new \RuntimeException("Invalid qty: batch_total > remaining (detail_id={$detailId})");
                }

                // ✅ GOOD allocations check
                $goodAlloc = $row['good_allocations'] ?? [];
                $sumAlloc = 0;
                foreach ($goodAlloc as $a) {
                    $sumAlloc += (int) ($a['qty'] ?? 0);
                }

                if ($addGood > 0 && $sumAlloc !== $addGood) {
                    throw new \RuntimeException("Good allocation mismatch: Good={$addGood}, alloc_sum={$sumAlloc} (detail_id={$detailId})");
                }

                $defRows = $row['defects'] ?? [];
                $damRows = $row['damaged_items'] ?? [];

                if (count($defRows) !== $addDefect) {
                    throw new \RuntimeException("Defect detail mismatch: expected {$addDefect} rows, got " . count($defRows) . " (detail_id={$detailId})");
                }
                if (count($damRows) !== $addDamaged) {
                    throw new \RuntimeException("Damaged detail mismatch: expected {$addDamaged} rows, got " . count($damRows) . " (detail_id={$detailId})");
                }

                // ✅ rackAgg untuk update StockRack + bikin mutation per rack
                $rackAgg = []; // rackId => ['good'=>x,'defect'=>y,'damaged'=>z,'total'=>t]
                $pickFirstRackId = null;

                foreach ($goodAlloc as $k => $a) {
                    $rackId = (int) ($a['rack_id'] ?? 0);
                    $qty    = (int) ($a['qty'] ?? 0);
                    if ($qty <= 0) continue;

                    if ($rackId <= 0) {
                        throw new \RuntimeException("Good allocation rack required (detail_id={$detailId}, row={$k}).");
                    }
                    $this->assertRackBelongsToWarehouse($rackId, (int) $purchaseDelivery->warehouse_id);
                    $pickFirstRackId ??= $rackId;

                    if (!isset($rackAgg[$rackId])) $rackAgg[$rackId] = ['good'=>0,'defect'=>0,'damaged'=>0,'total'=>0];
                    $rackAgg[$rackId]['good']  += $qty;
                    $rackAgg[$rackId]['total'] += $qty;
                }

                foreach ($defRows as $k => $d) {
                    $rackId = (int) ($d['rack_id'] ?? 0);
                    if ($rackId <= 0) throw new \RuntimeException("Defect rack is required (detail_id={$detailId}, row={$k}).");
                    $this->assertRackBelongsToWarehouse($rackId, (int) $purchaseDelivery->warehouse_id);
                    $pickFirstRackId ??= $rackId;

                    if (!isset($rackAgg[$rackId])) $rackAgg[$rackId] = ['good'=>0,'defect'=>0,'damaged'=>0,'total'=>0];
                    $rackAgg[$rackId]['defect']++;
                    $rackAgg[$rackId]['total']++;

                    if (empty($d['defect_type']) || !trim((string)$d['defect_type'])) {
                        throw new \RuntimeException("Defect type is required (detail_id={$detailId}, row={$k}).");
                    }
                }

                foreach ($damRows as $k => $d) {
                    $rackId = (int) ($d['rack_id'] ?? 0);
                    if ($rackId <= 0) throw new \RuntimeException("Damaged rack is required (detail_id={$detailId}, row={$k}).");
                    $this->assertRackBelongsToWarehouse($rackId, (int) $purchaseDelivery->warehouse_id);
                    $pickFirstRackId ??= $rackId;

                    if (!isset($rackAgg[$rackId])) $rackAgg[$rackId] = ['good'=>0,'defect'=>0,'damaged'=>0,'total'=>0];
                    $rackAgg[$rackId]['damaged']++;
                    $rackAgg[$rackId]['total']++;

                    if (empty($d['damaged_reason']) || !trim((string)$d['damaged_reason'])) {
                        throw new \RuntimeException("Damaged reason is required (detail_id={$detailId}, row={$k}).");
                    }
                }

                // ✅ update PD Detail (cumulative)
                $newGood    = (int) ($pdDetail->qty_received ?? 0) + $addGood;
                $newDefect  = (int) ($pdDetail->qty_defect ?? 0) + $addDefect;
                $newDamaged = (int) ($pdDetail->qty_damaged ?? 0) + $addDamaged;

                $pdDetail->update([
                    'rack_id'      => $pickFirstRackId ?? $pdDetail->rack_id,
                    'qty_received' => $newGood,
                    'qty_defect'   => $newDefect,
                    'qty_damaged'  => $newDamaged,
                ]);

                // ✅ Update fulfilled qty PO detail
                if (!empty($purchaseDelivery->purchase_order_id) && $purchaseDelivery->purchaseOrder) {
                    $po = $purchaseDelivery->purchaseOrder;
                    $poDetail = $po->purchaseOrderDetails->firstWhere('product_id', $productId);

                    if ($poDetail) {
                        $newFulfilled = (int) $poDetail->fulfilled_quantity + $batchTotal;
                        if ($newFulfilled > (int) $poDetail->quantity) {
                            $newFulfilled = (int) $poDetail->quantity;
                        }
                        $poDetail->update(['fulfilled_quantity' => $newFulfilled]);
                    }
                }

                // =========================================================
                // ✅ MUTATION IN: sekarang bikin PER RACK (rack_id terisi)
                // NOTE: tidak menyimpan rack di note (biar selaras sama Transfer/SaleDelivery)
                // =========================================================
                $mutationInByRack = []; // rackId => mutation_id
                foreach ($rackAgg as $rackId => $agg) {
                    $qtyRackTotal = (int) ($agg['total'] ?? 0);
                    if ($qtyRackTotal <= 0) continue;

                    $noteIn = "Purchase Delivery IN #{$reference} | WH {$purchaseDelivery->warehouse_id}";

                    $mid = $this->mutationController->applyInOutAndGetMutationId(
                        (int) $purchaseDelivery->branch_id,
                        (int) $purchaseDelivery->warehouse_id,
                        $productId,
                        'In',
                        $qtyRackTotal,
                        $reference,
                        $noteIn,
                        (string) $purchaseDelivery->getRawOriginal('date'),
                        (int) $rackId // ✅ penting: ini yang isi kolom Rack di Mutations index
                    );

                    $mutationInByRack[(int) $rackId] = (int) $mid;
                }

                // Defect Items (per unit)
                if ($addDefect > 0) {
                    foreach ($defRows as $k => $d) {
                        $photoPath = null;
                        if (request()->hasFile("items.$idx.defects.$k.photo")) {
                            $photoPath = request()->file("items.$idx.defects.$k.photo")->store('defects', 'public');
                        }

                        $rackId = (int) ($d['rack_id'] ?? 0);

                        ProductDefectItem::create([
                            'branch_id'      => (int) $purchaseDelivery->branch_id,
                            'warehouse_id'   => (int) $purchaseDelivery->warehouse_id,
                            'rack_id'        => $rackId > 0 ? $rackId : null, // ✅ simpan rack juga
                            'product_id'     => $productId,
                            'reference_id'   => (int) $purchaseDelivery->id,
                            'reference_type' => PurchaseDelivery::class,
                            'quantity'       => 1,
                            'defect_type'    => $d['defect_type'] ?? null,
                            'description'    => $d['defect_description'] ?? null,
                            'photo_path'     => $photoPath,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                // Damaged Items (per unit)
                if ($addDamaged > 0) {
                    foreach ($damRows as $k => $d) {
                        $photoPath = null;
                        if (request()->hasFile("items.$idx.damaged_items.$k.photo")) {
                            $photoPath = request()->file("items.$idx.damaged_items.$k.photo")->store('damaged', 'public');
                        }

                        $rackId = (int) ($d['rack_id'] ?? 0);
                        $mutationInId = $rackId > 0 ? (int) ($mutationInByRack[$rackId] ?? 0) : 0;

                        ProductDamagedItem::create([
                            'branch_id'       => (int) $purchaseDelivery->branch_id,
                            'warehouse_id'    => (int) $purchaseDelivery->warehouse_id,
                            'rack_id'         => $rackId > 0 ? $rackId : null, // ✅ simpan rack juga
                            'product_id'      => $productId,
                            'reference_id'    => (int) $purchaseDelivery->id,
                            'reference_type'  => PurchaseDelivery::class,
                            'quantity'        => 1,
                            'reason'          => $d['damaged_reason'] ?? null,
                            'photo_path'      => $photoPath,
                            'created_by'      => auth()->id(),
                            'mutation_in_id'  => $mutationInId > 0 ? $mutationInId : null, // ✅ sesuai rack
                            'mutation_out_id' => null,
                        ]);
                    }
                }

                // UPDATE stock_racks per rack
                foreach ($rackAgg as $rackId => $agg) {
                    $this->upsertStockRack(
                        (int) $purchaseDelivery->branch_id,
                        (int) $purchaseDelivery->warehouse_id,
                        (int) $rackId,
                        (int) $productId,
                        (int) $agg['total'],
                        (int) $agg['good'],
                        (int) $agg['defect'],
                        (int) $agg['damaged']
                    );
                }
            }

            if (!$anyConfirmedInThisBatch) {
                throw new \RuntimeException('Batch confirm kosong. Isi minimal 1 qty untuk dikunci.');
            }

            // ✅ cek remaining
            $purchaseDelivery->refresh();
            $purchaseDelivery->loadMissing(['purchaseDeliveryDetails']);

            $stillHasRemaining = false;
            foreach ($purchaseDelivery->purchaseDeliveryDetails as $d) {
                $exp = (int) ($d->quantity ?? 0);
                $conf = (int) ($d->qty_received ?? 0) + (int) ($d->qty_defect ?? 0) + (int) ($d->qty_damaged ?? 0);
                if ($conf < $exp) { $stillHasRemaining = true; break; }
            }

            $confirmNote = $request->confirm_note ? (string) $request->confirm_note : null;

            $purchaseDelivery->update([
                'status' => $stillHasRemaining ? 'partial' : 'received',

                'confirm_note'              => $confirmNote,
                'confirm_note_updated_by'   => $confirmNote ? auth()->id() : null,
                'confirm_note_updated_role' => $confirmNote ? $this->roleString() : null,
                'confirm_note_updated_at'   => $confirmNote ? now() : null,
            ]);

            if (!empty($purchaseDelivery->purchase_order_id)) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail((int) $purchaseDelivery->purchase_order_id);
                $po->calculateFulfilledQuantity();
                $po->markAsCompleted();
            }
        });

        toast('Purchase Delivery confirmed (batch locked) successfully', 'success');
        return redirect()->route('purchase-deliveries.show', $purchaseDelivery->id);
    }

    public function show(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('show_purchase_deliveries'), 403);

        $purchaseDelivery->loadMissing([
            'purchaseOrder.supplier',
            'purchase',
            'warehouse',
            'branch',
            'creator',
            'noteUpdater',
            'confirmNoteUpdater',
            'purchaseDeliveryDetails',
        ]);

        $vendorName = optional(optional($purchaseDelivery->purchaseOrder)->supplier)->supplier_name
            ?? optional($purchaseDelivery->purchase)->supplier_name
            ?? '-';

        $vendorEmail = optional(optional($purchaseDelivery->purchaseOrder)->supplier)->supplier_email
            ?? (optional($purchaseDelivery->purchase)->supplier_id
                ? \Modules\People\Entities\Supplier::where('id', $purchaseDelivery->purchase->supplier_id)->value('supplier_email')
                : null)
            ?? '-';

        $defects = ProductDefectItem::withoutGlobalScopes()
            ->where('branch_id', (int) $purchaseDelivery->branch_id)
            ->where('warehouse_id', (int) $purchaseDelivery->warehouse_id)
            ->where('reference_type', PurchaseDelivery::class)
            ->where('reference_id', (int) $purchaseDelivery->id)
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        $damaged = ProductDamagedItem::withoutGlobalScopes()
            ->where('branch_id', (int) $purchaseDelivery->branch_id)
            ->where('warehouse_id', (int) $purchaseDelivery->warehouse_id)
            ->where('reference_type', PurchaseDelivery::class)
            ->where('reference_id', (int) $purchaseDelivery->id)
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        // =========================================================
        // ✅ Rack Movement Summary (ambil dari mutation log)
        // reference format: PD-{id}-B{n}
        // =========================================================
        $baseRef = 'PD-' . (int) $purchaseDelivery->id;

        $mutations = Mutation::withoutGlobalScopes()
            ->where('branch_id', (int) $purchaseDelivery->branch_id)
            ->where('warehouse_id', (int) $purchaseDelivery->warehouse_id)
            ->where('reference', 'like', $baseRef . '-B%')
            ->where('mutation_type', 'In')
            ->orderBy('id', 'asc')
            ->get(['id','reference','product_id','rack_id','stock_in']);

        $rackIds = $mutations->pluck('rack_id')->filter()->unique()->map(fn($x)=>(int)$x)->values()->toArray();

        $rackMap = [];
        if (!empty($rackIds)) {
            $rackMap = Rack::withoutGlobalScopes()
                ->whereIn('id', $rackIds)
                ->get()
                ->mapWithKeys(function ($r) {
                    $label = trim((string) ($r->code ?? ''));
                    if ($label === '') $label = trim((string) ($r->name ?? ''));
                    if ($label === '') $label = 'Rack#' . (int) $r->id;
                    return [(int) $r->id => $label];
                })
                ->toArray();
        }

        // Group: product_id -> list ringkas rack + qty
        $rackInSummaryByProduct = [];
        foreach ($mutations as $m) {
            $pid = (int) $m->product_id;
            $rid = (int) ($m->rack_id ?? 0);
            $qty = (int) ($m->stock_in ?? 0);
            if ($qty <= 0) continue;

            $label = $rid > 0 ? ($rackMap[$rid] ?? ('Rack#'.$rid)) : '-';

            if (!isset($rackInSummaryByProduct[$pid])) $rackInSummaryByProduct[$pid] = [];
            if (!isset($rackInSummaryByProduct[$pid][$label])) $rackInSummaryByProduct[$pid][$label] = 0;

            $rackInSummaryByProduct[$pid][$label] += $qty;
        }

        // convert assoc -> array (biar gampang di blade)
        foreach ($rackInSummaryByProduct as $pid => $rows) {
            $tmp = [];
            foreach ($rows as $label => $qty) {
                $tmp[] = ['rack' => $label, 'qty' => (int) $qty];
            }
            $rackInSummaryByProduct[$pid] = $tmp;
        }

        return view('purchase-deliveries::show', [
            'purchaseDelivery' => $purchaseDelivery,
            'vendorName'       => $vendorName,
            'vendorEmail'      => $vendorEmail,
            'defects'          => $defects,
            'damaged'          => $damaged,
            'rackInSummaryByProduct' => $rackInSummaryByProduct, // ✅ NEW
        ]);
    }
}
