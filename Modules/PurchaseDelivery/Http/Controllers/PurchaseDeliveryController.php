<?php

namespace Modules\PurchaseDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\PurchaseDelivery\DataTables\PurchaseDeliveriesDataTable;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Entities\PurchaseOrderDetails;
use Modules\Product\Entities\Warehouse;

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

            // Guard: warehouse harus 1 branch dengan PO
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

                // meta note create
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

            // guard warehouse must be same branch
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

                // meta note update
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
     */
    public function confirm(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('confirm_purchase_deliveries'), 403);

        if (strtolower((string) $purchaseDelivery->status) !== 'pending') {
            return redirect()->back()->with('error', 'This delivery has already been confirmed.');
        }

        $purchaseDelivery->load(['purchaseOrder', 'purchase', 'purchaseDeliveryDetails', 'warehouse']);

        return view('purchase-deliveries::confirm', compact('purchaseDelivery'));
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

        return view('purchase-deliveries::show', [
            'purchaseDelivery' => $purchaseDelivery,
            'vendorName'       => $vendorName,
            'vendorEmail'      => $vendorEmail,
        ]);
    }

    /**
     * =====================
     * CONFIRM STORE
     * =====================
     * NOTE: partial allowed (NOT missing)
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
            'items.*.qty_received'  => 'required|integer|min:0',
            'items.*.qty_defect'    => 'required|integer|min:0',
            'items.*.qty_damaged'   => 'required|integer|min:0',
            'items.*.defects'       => 'nullable|array',
            'items.*.damaged_items' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $purchaseDelivery) {

            $purchaseDelivery = PurchaseDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($purchaseDelivery->id);

            if (strtolower((string) $purchaseDelivery->status) !== 'pending') {
                abort(422, 'This delivery has already been confirmed.');
            }

            $purchaseDelivery->loadMissing([
                'purchaseOrder.purchaseOrderDetails',
                'purchaseDeliveryDetails',
                'warehouse',
            ]);

            // Guard active branch
            $activeBranchId = $this->activeBranchIdOrFail('purchase-deliveries.index');
            if ((int) $purchaseDelivery->branch_id !== (int) $activeBranchId) {
                abort(403, 'Active branch mismatch for this Purchase Delivery.');
            }

            // Guard warehouse belongs to PD branch
            $wh = Warehouse::findOrFail((int) $purchaseDelivery->warehouse_id);
            if ((int) $wh->branch_id !== (int) $purchaseDelivery->branch_id) {
                abort(403, 'Warehouse must belong to the same branch as this Purchase Delivery.');
            }

            $reference = 'PD-' . (int) $purchaseDelivery->id;

            // Anti double mutation
            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $reference)
                ->where('note', 'like', 'Purchase Delivery IN%')
                ->exists();

            if ($alreadyIn) {
                abort(422, 'This delivery was already confirmed (stock movement exists).');
            }

            $isPartialDelivery = false;

            foreach ($request->items as $idx => $row) {

                $detailId  = (int) $row['detail_id'];
                $productId = (int) $row['product_id'];
                $expected  = (int) $row['expected'];

                $received = (int) $row['qty_received'];
                $defect   = (int) $row['qty_defect'];
                $damaged  = (int) $row['qty_damaged'];

                $totalConfirmed = $received + $defect + $damaged;

                if ($totalConfirmed > $expected) {
                    throw new \RuntimeException("Invalid qty: total > expected (detail_id={$detailId})");
                }

                // ✅ PD boleh partial, jadi ini hanya flag status (Bukan missing)
                if ($totalConfirmed < $expected) {
                    $isPartialDelivery = true;
                }

                $pdDetail = $purchaseDelivery->purchaseDeliveryDetails->firstWhere('id', $detailId);
                if (!$pdDetail) {
                    throw new \RuntimeException("PD Detail not found: {$detailId}");
                }

                // 1) update PD detail qty
                $pdDetail->update([
                    'qty_received' => $received,
                    'qty_defect'   => $defect,
                    'qty_damaged'  => $damaged,
                ]);

                // 2) update fulfilled qty di PO detail (+ total confirmed) hanya kalau PD berasal dari PO
                if (!empty($purchaseDelivery->purchase_order_id) && $purchaseDelivery->purchaseOrder) {
                    $po = $purchaseDelivery->purchaseOrder;
                    $poDetail = $po->purchaseOrderDetails->firstWhere('product_id', $productId);

                    if ($poDetail) {
                        $newFulfilled = (int) $poDetail->fulfilled_quantity + $totalConfirmed;

                        // clamp biar gak lewat ordered qty
                        if ($newFulfilled > (int) $poDetail->quantity) {
                            $newFulfilled = (int) $poDetail->quantity;
                        }

                        $poDetail->update(['fulfilled_quantity' => $newFulfilled]);
                    }
                }

                if ($totalConfirmed <= 0) continue;

                // 3) MUTATION IN (total)
                $noteIn = "Purchase Delivery IN #{$reference} | WH {$purchaseDelivery->warehouse_id}";
                $inId = $this->mutationController->applyInOutAndGetMutationId(
                    (int) $purchaseDelivery->branch_id,
                    (int) $purchaseDelivery->warehouse_id,
                    $productId,
                    'In',
                    $totalConfirmed,
                    $reference,
                    $noteIn,
                    (string) $purchaseDelivery->getRawOriginal('date')
                );

                // 4) DEFECT rows
                if ($defect > 0) {
                    $defRows = $row['defects'] ?? [];
                    if (count($defRows) === 0) $defRows = array_fill(0, $defect, []);
                    if (count($defRows) !== $defect) {
                        throw new \RuntimeException("Defect detail mismatch: expected {$defect} rows, got " . count($defRows));
                    }

                    foreach ($defRows as $k => $d) {
                        $photoPath = null;

                        if (request()->hasFile("items.$idx.defects.$k.photo")) {
                            $photoPath = request()->file("items.$idx.defects.$k.photo")
                                ->store('defects', 'public');
                        }

                        ProductDefectItem::create([
                            'branch_id'      => (int) $purchaseDelivery->branch_id,
                            'warehouse_id'   => (int) $purchaseDelivery->warehouse_id,
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

                // 5) DAMAGED rows
                if ($damaged > 0) {
                    $damRows = $row['damaged_items'] ?? [];
                    if (count($damRows) === 0) $damRows = array_fill(0, $damaged, []);
                    if (count($damRows) !== $damaged) {
                        throw new \RuntimeException("Damaged detail mismatch: expected {$damaged} rows, got " . count($damRows));
                    }

                    foreach ($damRows as $k => $d) {
                        $photoPath = null;

                        if (request()->hasFile("items.$idx.damaged_items.$k.photo")) {
                            $photoPath = request()->file("items.$idx.damaged_items.$k.photo")
                                ->store('damaged', 'public');
                        }

                        ProductDamagedItem::create([
                            'branch_id'       => (int) $purchaseDelivery->branch_id,
                            'warehouse_id'    => (int) $purchaseDelivery->warehouse_id,
                            'product_id'      => $productId,
                            'reference_id'    => (int) $purchaseDelivery->id,
                            'reference_type'  => PurchaseDelivery::class,
                            'quantity'        => 1,
                            'reason'          => $d['damaged_reason'] ?? null,
                            'photo_path'      => $photoPath,
                            'created_by'      => auth()->id(),
                            'mutation_in_id'  => (int) $inId,
                            'mutation_out_id' => null,
                        ]);
                    }
                }
            }

            // ✅ simpan confirm note + meta
            $confirmNote = $request->confirm_note ? (string) $request->confirm_note : null;

            $purchaseDelivery->update([
                'status' => $isPartialDelivery ? 'partial' : 'received',

                'confirm_note'            => $confirmNote,
                'confirm_note_updated_by' => $confirmNote ? auth()->id() : null,
                'confirm_note_updated_role' => $confirmNote ? $this->roleString() : null,
                'confirm_note_updated_at' => $confirmNote ? now() : null,
            ]);

            // ✅ Update PO status hanya kalau PD berasal dari PO
            if (!empty($purchaseDelivery->purchase_order_id)) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail((int) $purchaseDelivery->purchase_order_id);
                $po->calculateFulfilledQuantity();
                $po->markAsCompleted();
            }
        });

        toast('Purchase Delivery confirmed successfully', 'success');
        return redirect()->route('purchase-deliveries.show', $purchaseDelivery->id);
    }
}
