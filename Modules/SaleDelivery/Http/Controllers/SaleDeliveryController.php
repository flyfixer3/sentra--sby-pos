<?php

namespace Modules\SaleDelivery\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\SaleDelivery\DataTables\SaleDeliveriesDataTable;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryItem;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;

use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

use Modules\Setting\Entities\Setting;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;

class SaleDeliveryController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    public function index(SaleDeliveriesDataTable $dataTable)
    {
        abort_if(Gate::denies('access_sale_deliveries'), 403);
        return $dataTable->render('saledelivery::index');
    }

    public function show(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        $saleDelivery->load([
            'items.product',
            'warehouse',
            'customer',
            'creator',
            'confirmer',
            'saleOrder',
        ]);

        return view('saledelivery::show', compact('saleDelivery'));
    }

    public function confirmForm(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        if (strtolower((string) $saleDelivery->status) !== 'pending') {
            abort(422, 'Sale Delivery is not pending.');
        }

        $branchId = BranchContext::id();
        if ((int) $saleDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleDelivery->load(['items.product', 'warehouse', 'customer']);

        return view('saledelivery::confirm', compact('saleDelivery'));
    }

    public function confirmStore(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        $branchId = BranchContext::id();

        $request->validate([
            'confirm_note' => 'nullable|string|max:5000',

            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.good' => 'required|integer|min:0',
            'items.*.defect' => 'required|integer|min:0',
            'items.*.damaged' => 'required|integer|min:0',

            // ✅ OPTIONAL: selected IDs (kalau user pilih)
            'items.*.selected_defect_ids' => 'nullable|array',
            'items.*.selected_defect_ids.*' => 'integer',

            'items.*.selected_damaged_ids' => 'nullable|array',
            'items.*.selected_damaged_ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($request, $saleDelivery, $branchId) {

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['items'])
                ->findOrFail($saleDelivery->id);

            $status = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if (!in_array($status, ['pending'], true)) {
                abort(422, 'Sale Delivery is not pending.');
            }

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                abort(403, 'Wrong branch context.');
            }

            if (empty($saleDelivery->reference)) {
                $saleDelivery->update([
                    'reference' => make_reference_id('SDO', (int) $saleDelivery->id),
                ]);
            }

            $reference = (string) $saleDelivery->reference;

            // anti double confirm
            $exists = Mutation::withoutGlobalScopes()
                ->where('reference', $reference)
                ->where('note', 'like', 'Sales Delivery OUT%')
                ->exists();

            if ($exists) {
                abort(422, 'This sale delivery was already confirmed (stock movement exists).');
            }

            // map input by item_id
            $inputById = [];
            foreach ($request->items as $row) {
                $itemId = (int) $row['id'];

                $selectedDefectIds = [];
                if (isset($row['selected_defect_ids']) && is_array($row['selected_defect_ids'])) {
                    $selectedDefectIds = array_values(array_unique(array_map('intval', $row['selected_defect_ids'])));
                }

                $selectedDamagedIds = [];
                if (isset($row['selected_damaged_ids']) && is_array($row['selected_damaged_ids'])) {
                    $selectedDamagedIds = array_values(array_unique(array_map('intval', $row['selected_damaged_ids'])));
                }

                $inputById[$itemId] = [
                    'good' => (int) $row['good'],
                    'defect' => (int) $row['defect'],
                    'damaged' => (int) $row['damaged'],
                    'selected_defect_ids' => $selectedDefectIds,
                    'selected_damaged_ids' => $selectedDamagedIds,
                ];
            }

            $totalConfirmedAll = 0;
            $isPartial = false;

            foreach ($saleDelivery->items as $it) {
                $itemId = (int) $it->id;
                $expected = (int) ($it->quantity ?? 0);

                $good = (int) ($inputById[$itemId]['good'] ?? 0);
                $defect = (int) ($inputById[$itemId]['defect'] ?? 0);
                $damaged = (int) ($inputById[$itemId]['damaged'] ?? 0);

                $confirmed = $good + $defect + $damaged;

                if ($confirmed > $expected) {
                    abort(422, "Confirmed qty cannot exceed expected qty for item ID {$itemId}.");
                }

                if ($confirmed < $expected) {
                    $isPartial = true;
                }

                // ✅ Validasi selection count harus match qty yang diinput (kalau qty > 0)
                $selDef = $inputById[$itemId]['selected_defect_ids'] ?? [];
                $selDam = $inputById[$itemId]['selected_damaged_ids'] ?? [];

                // kalau user isi defect > 0, maka harus pilih ids (atau kalau mau backward compatible: boleh kosong -> auto pick)
                // Aku bikin: kalau user sudah memilih (selDef tidak kosong), maka jumlahnya WAJIB sama.
                // Kalau tidak memilih sama sekali, kita fallback auto pick.
                if ($defect > 0 && !empty($selDef) && count($selDef) !== $defect) {
                    abort(422, "Selected DEFECT IDs count must equal defect qty for item ID {$itemId}.");
                }
                if ($damaged > 0 && !empty($selDam) && count($selDam) !== $damaged) {
                    abort(422, "Selected DAMAGED IDs count must equal damaged qty for item ID {$itemId}.");
                }

                $totalConfirmedAll += $confirmed;

                $it->update([
                    'qty_good' => $good,
                    'qty_defect' => $defect,
                    'qty_damaged' => $damaged,
                ]);
            }

            if ($totalConfirmedAll <= 0) {
                abort(422, 'Nothing to confirm. Please input at least 1 quantity.');
            }

            /**
             * MUTATION OUT:
             * - Out total confirmed per product (good+defect+damaged)
             * - Consume defect/damaged rows based on selected IDs (if provided) or auto-pick as fallback
             */
            foreach ($saleDelivery->items as $it) {
                $productId = (int) $it->product_id;

                $good = (int) ($it->qty_good ?? 0);
                $defect = (int) ($it->qty_defect ?? 0);
                $damaged = (int) ($it->qty_damaged ?? 0);

                $confirmed = $good + $defect + $damaged;
                if ($confirmed <= 0) continue;

                // 1) Mutation OUT total
                $noteOut = "Sales Delivery OUT #{$reference} | WH {$saleDelivery->warehouse_id}";
                $outId = $this->mutationController->applyInOutAndGetMutationId(
                    (int) $saleDelivery->branch_id,
                    (int) $saleDelivery->warehouse_id,
                    $productId,
                    'Out',
                    $confirmed,
                    $reference,
                    $noteOut,
                    (string) $saleDelivery->getRawOriginal('date')
                );

                $input = $inputById[(int) $it->id] ?? [];
                $selectedDefectIds = $input['selected_defect_ids'] ?? [];
                $selectedDamagedIds = $input['selected_damaged_ids'] ?? [];

                // 2) Consume DEFECT items
                if ($defect > 0) {

                    $ids = [];

                    if (!empty($selectedDefectIds)) {
                        $ids = $selectedDefectIds;

                        // ✅ Validasi ownership + availability
                        $countValid = DB::table('product_defect_items')
                            ->whereIn('id', $ids)
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $saleDelivery->warehouse_id)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->count();

                        if ($countValid !== count($ids)) {
                            abort(422, "Some selected DEFECT IDs are invalid/unavailable for product_id {$productId}.");
                        }

                        if (count($ids) !== $defect) {
                            abort(422, "Selected DEFECT IDs count mismatch for product_id {$productId}.");
                        }
                    } else {
                        // fallback auto-pick
                        $ids = DB::table('product_defect_items')
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $saleDelivery->warehouse_id)
                            ->where('product_id', $productId)
                            ->whereNull('moved_out_at')
                            ->orderBy('id', 'asc')
                            ->limit($defect)
                            ->pluck('id')
                            ->all();

                        if (count($ids) !== $defect) {
                            abort(422, "Not enough DEFECT stock for product_id {$productId}. Needed {$defect}, available " . count($ids) . ".");
                        }
                    }

                    DB::table('product_defect_items')
                        ->whereIn('id', $ids)
                        ->update([
                            'moved_out_at' => now(),
                            'moved_out_by' => auth()->id(),
                            'moved_out_reference_type' => SaleDelivery::class,
                            'moved_out_reference_id' => (int) $saleDelivery->id,
                        ]);
                }

                // 3) Consume DAMAGED items
                if ($damaged > 0) {

                    $ids = [];

                    if (!empty($selectedDamagedIds)) {
                        $ids = $selectedDamagedIds;

                        $countValid = DB::table('product_damaged_items')
                            ->whereIn('id', $ids)
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $saleDelivery->warehouse_id)
                            ->where('product_id', $productId)
                            ->where('damage_type', 'damaged')
                            ->where('resolution_status', 'pending')
                            ->whereNull('moved_out_at')
                            ->count();

                        if ($countValid !== count($ids)) {
                            abort(422, "Some selected DAMAGED IDs are invalid/unavailable for product_id {$productId}.");
                        }

                        if (count($ids) !== $damaged) {
                            abort(422, "Selected DAMAGED IDs count mismatch for product_id {$productId}.");
                        }
                    } else {
                        // fallback auto-pick
                        $ids = DB::table('product_damaged_items')
                            ->where('branch_id', (int) $saleDelivery->branch_id)
                            ->where('warehouse_id', (int) $saleDelivery->warehouse_id)
                            ->where('product_id', $productId)
                            ->where('damage_type', 'damaged')
                            ->where('resolution_status', 'pending')
                            ->whereNull('moved_out_at')
                            ->orderBy('id', 'asc')
                            ->limit($damaged)
                            ->pluck('id')
                            ->all();

                        if (count($ids) !== $damaged) {
                            abort(422, "Not enough DAMAGED stock for product_id {$productId}. Needed {$damaged}, available " . count($ids) . ".");
                        }
                    }

                    DB::table('product_damaged_items')
                        ->whereIn('id', $ids)
                        ->update([
                            'moved_out_at' => now(),
                            'moved_out_by' => auth()->id(),
                            'moved_out_reference_type' => SaleDelivery::class,
                            'moved_out_reference_id' => (int) $saleDelivery->id,
                            'mutation_out_id' => (int) $outId,
                        ]);
                }
            }

            // save confirm note meta
            $confirmNote = $request->confirm_note ? (string) $request->confirm_note : null;

            $saleDelivery->update([
                'status' => $isPartial ? 'partial' : 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),

                'confirm_note' => $confirmNote,
                'confirm_note_updated_by' => $confirmNote ? auth()->id() : null,
                'confirm_note_updated_role' => $confirmNote ? $this->roleString() : null,
                'confirm_note_updated_at' => $confirmNote ? now() : null,
            ]);

            // update SO fulfillment status
            $saleOrderId = (int) ($saleDelivery->sale_order_id ?? 0);
            if ($saleOrderId <= 0 && !empty($saleDelivery->sale_id)) {
                $found = SaleOrder::query()
                    ->where('branch_id', (int) $saleDelivery->branch_id)
                    ->where('sale_id', (int) $saleDelivery->sale_id)
                    ->orderByDesc('id')
                    ->first();
                if ($found) $saleOrderId = (int) $found->id;
            }

            if ($saleOrderId > 0) {
                $this->updateSaleOrderFulfillmentStatus((int) $saleOrderId);
            }
        });

        toast('Sale Delivery confirmed successfully', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
    }

    /**
     * ✅ Print Surat Jalan Sale Delivery (optional)
     * - tidak mengubah DB
     * - watermark COPY jika ?copy=2 dst
     */
    public function printPdf(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        $branchId = BranchContext::id();
        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        $saleDelivery = SaleDelivery::withoutGlobalScopes()
            ->with(['items.product', 'warehouse', 'customer', 'saleOrder'])
            ->findOrFail($saleDelivery->id);

        if ((int) $saleDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $setting = Setting::first();

        $copyNumber = (int) ($request->query('copy') ?? 1);
        if ($copyNumber <= 0) $copyNumber = 1;

        $senderBranch = Branch::withoutGlobalScopes()->find((int) $saleDelivery->branch_id);

        // ===============================
        // ✅ Ambil defect/damaged berdasarkan moved_out_reference_* (OUTGOING)
        // ===============================
        $movedDefects = ProductDefectItem::query()
            ->where('moved_out_reference_type', SaleDelivery::class)
            ->where('moved_out_reference_id', (int) $saleDelivery->id)
            ->orderBy('id', 'asc')
            ->get();

        $movedDamaged = ProductDamagedItem::query()
            ->where('moved_out_reference_type', SaleDelivery::class)
            ->where('moved_out_reference_id', (int) $saleDelivery->id)
            ->where('damage_type', 'damaged')
            ->orderBy('id', 'asc')
            ->get();

        $defectsByProduct = $movedDefects->groupBy('product_id');
        $damagedByProduct = $movedDamaged->groupBy('product_id');

        $truncate = function (?string $text, int $max = 45): ?string {
            $text = trim((string) ($text ?? ''));
            if ($text === '') return null;
            if (mb_strlen($text) <= $max) return $text;
            return mb_substr($text, 0, $max) . '...';
        };

        $notesByItemId = [];

        foreach ($saleDelivery->items as $item) {
            $itemId = (int) $item->id;
            $pid = (int) $item->product_id;

            $good = (int) ($item->qty_good ?? 0);
            $defect = (int) ($item->qty_defect ?? 0);
            $damaged = (int) ($item->qty_damaged ?? 0);

            // kalau belum confirm, fallback ke expected qty (anggap GOOD)
            $expected = (int) ($item->quantity ?? 0);
            $sum = $good + $defect + $damaged;

            if ($sum <= 0 && $expected > 0) {
                $notesByItemId[$itemId] = 'GOOD';
                continue;
            }

            // ✅ GOOD: tampilkan keterangannya juga biar jelas
            if ($good > 0 && $defect === 0 && $damaged === 0) {
                $notesByItemId[$itemId] = 'GOOD';
                continue;
            }

            $chunks = [];

            if ($good > 0) {
                $chunks[] = "GOOD {$good}";
            }

            if ($defect > 0) {
                $rows = $defectsByProduct->get($pid, collect());
                $types = $rows->pluck('defect_type')->filter()->unique()->values()->take(3)->toArray();
                $typeText = !empty($types) ? implode(', ', $types) : 'Defect';

                $desc = $rows->pluck('description')->filter()->first();
                $desc = $truncate($desc, 45);

                $txt = "DEFECT {$defect} ({$typeText})";
                if (!empty($desc)) $txt .= " - {$desc}";
                $chunks[] = $txt;
            }

            if ($damaged > 0) {
                $rows = $damagedByProduct->get($pid, collect());
                $reason = $rows->pluck('reason')->filter()->first();
                $reason = $truncate($reason, 45);

                $txt = "DAMAGED {$damaged}";
                if (!empty($reason)) $txt .= " - {$reason}";
                $chunks[] = $txt;
            }

            $notesByItemId[$itemId] = implode(' | ', $chunks);
        }

        $pdf = Pdf::loadView('saledelivery::print', [
            'saleDelivery'   => $saleDelivery,
            'setting'        => $setting,
            'copyNumber'     => $copyNumber,
            'senderBranch'   => $senderBranch,
            'notesByItemId'  => $notesByItemId,
        ])->setPaper('A4', 'portrait');

        $ref = $saleDelivery->reference ?? ('SDO-' . $saleDelivery->id);
        return $pdf->download("Surat_Jalan_SaleDelivery_{$ref}_COPY_{$copyNumber}.pdf");
    }

    private function roleString(): string
    {
        $user = auth()->user();
        if (!$user) return 'unknown';

        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            if (!empty($roles) && count($roles) > 0) return (string) $roles[0];
        }

        return 'user';
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $source = (string) $request->get('source', '');
        if (!in_array($source, ['quotation', 'sale', 'sale_order'], true)) {
            abort(403, 'Sale Delivery can only be created from Quotation, Sale, or Sale Order.');
        }

        if ($source === 'quotation' && !$request->filled('quotation_id')) abort(422, 'quotation_id is required');
        if ($source === 'sale' && !$request->filled('sale_id')) abort(422, 'sale_id is required');
        if ($source === 'sale_order' && !$request->filled('sale_order_id')) abort(422, 'sale_order_id is required');

        $branchId = BranchContext::id();

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->forActiveBranch($branchId)
            ->orderBy('customer_name')
            ->get();

        $products = Product::query()->orderBy('product_name')->limit(200)->get();

        $prefillItems = [];
        $prefillCustomerId = null;
        $prefillSaleOrderRef = null;

        if ($source === 'sale_order') {

            $saleOrderId = (int) $request->sale_order_id;

            $saleOrder = SaleOrder::query()
                ->where('id', $saleOrderId)
                ->where('branch_id', $branchId)
                ->with(['items'])
                ->firstOrFail();

            $prefillSaleOrderRef = $saleOrder->reference ?? ('SO#' . $saleOrder->id);
            $prefillCustomerId = (int) $saleOrder->customer_id;

            $remainingMap = $this->getRemainingQtyBySaleOrder($saleOrderId);

            foreach ($saleOrder->items as $it) {
                $pid = (int) $it->product_id;
                if ($pid <= 0) continue;

                $rem = (int) ($remainingMap[$pid] ?? 0);
                if ($rem <= 0) continue;

                $prefillItems[] = [
                    'product_id' => $pid,
                    'quantity'   => $rem,
                    'price'      => (int) ($it->price ?? 0),
                ];
            }
        }

        if ($source === 'sale') {

            $saleId = (int) $request->sale_id;

            $sale = DB::table('sales')
                ->where('id', $saleId)
                ->where('branch_id', $branchId)
                ->first();

            if (!$sale) abort(404, 'Sale (invoice) not found in this branch.');

            $remainingMap = $this->getRemainingQtyBySale($saleId);

            $details = DB::table('sale_details')
                ->where('sale_id', $saleId)
                ->get();

            foreach ($details as $d) {
                $pid = (int) $d->product_id;
                if ($pid <= 0) continue;

                $rem = (int) ($remainingMap[$pid] ?? 0);
                if ($rem <= 0) continue;

                $prefillItems[] = [
                    'product_id' => $pid,
                    'quantity'   => $rem,
                    'price'      => (int) ($d->price ?? 0),
                ];
            }
        }

        return view('saledelivery::create', compact(
            'warehouses',
            'customers',
            'products',
            'source',
            'prefillItems',
            'prefillCustomerId',
            'prefillSaleOrderRef'
        ));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $source = (string) $request->get('source', '');
        abort_unless(in_array($source, ['quotation', 'sale', 'sale_order'], true), 403);

        $branchId = BranchContext::id();

        $rules = [
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|integer|min:0',
        ];

        if ($source !== 'sale_order') {
            $rules['customer_id'] = 'required|integer';
        }

        if ($source === 'quotation') $rules['quotation_id'] = 'required|integer';
        if ($source === 'sale') $rules['sale_id'] = 'required|integer';
        if ($source === 'sale_order') $rules['sale_order_id'] = 'required|integer';

        $request->validate($rules);

        DB::transaction(function () use ($request, $branchId, $source) {

            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('id', (int) $request->warehouse_id)
                ->firstOrFail();

            $saleId = null;
            $saleOrderId = null;

            $customerId = null;

            if ($source === 'sale_order') {
                $saleOrderId = (int) $request->sale_order_id;

                $saleOrder = SaleOrder::query()
                    ->where('id', $saleOrderId)
                    ->where('branch_id', $branchId)
                    ->with(['items'])
                    ->firstOrFail();

                $customerId = (int) $saleOrder->customer_id;

                $remainingMap = $this->getRemainingQtyBySaleOrder($saleOrderId);

                foreach ($request->items as $row) {
                    $pid = (int) $row['product_id'];
                    $qty = (int) $row['quantity'];
                    $rem = (int) ($remainingMap[$pid] ?? 0);

                    if ($qty > $rem) {
                        abort(422, "Qty exceeds remaining quantity for product_id {$pid}. Remaining: {$rem}.");
                    }
                }
            }

            if ($source !== 'sale_order') {
                $customer = Customer::query()
                    ->forActiveBranch($branchId)
                    ->where('id', (int) $request->customer_id)
                    ->firstOrFail();

                $customerId = (int) $customer->id;
            }

            if ($source === 'sale') {
                $saleId = (int) $request->sale_id;

                $sale = DB::table('sales')
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (!$sale) abort(404, 'Sale (invoice) not found in this branch.');
            }

            $delivery = SaleDelivery::create([
                'branch_id'     => $branchId,
                'quotation_id'  => $source === 'quotation' ? (int) $request->quotation_id : null,
                'sale_id'       => $source === 'sale' ? (int) $saleId : null,
                'sale_order_id' => $source === 'sale_order' ? (int) $saleOrderId : null,
                'customer_id'   => $customerId,
                'date'          => $request->date,
                'warehouse_id'  => $warehouse->id,
                'status'        => 'pending',
                'note'          => $request->note,
                'created_by'    => Auth::id(),
            ]);

            foreach ($request->items as $row) {
                SaleDeliveryItem::create([
                    'sale_delivery_id' => $delivery->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity' => (int) $row['quantity'],
                    'price' => array_key_exists('price', $row) && $row['price'] !== null
                        ? (int) $row['price']
                        : null,
                ]);
            }
        });

        toast('Sale Delivery Created!', 'success');
        return redirect()->route('sale-deliveries.index');
    }

    public function edit(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('edit_sale_deliveries'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        if (strtolower((string) $saleDelivery->status) !== 'pending') {
            abort(422, 'Only pending Sale Delivery can be edited.');
        }

        $branchId = BranchContext::id();
        if ((int) $saleDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleDelivery->load(['items.product', 'warehouse', 'customer', 'saleOrder']);

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->forActiveBranch($branchId)
            ->orderBy('customer_name')
            ->get();

        $products = Product::query()->orderBy('product_name')->limit(200)->get();

        return view('saledelivery::edit', compact('saleDelivery', 'warehouses', 'customers', 'products'));
    }

    public function update(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('edit_sale_deliveries'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        if (strtolower((string) $saleDelivery->status) !== 'pending') {
            abort(422, 'Only pending Sale Delivery can be edited.');
        }

        $branchId = BranchContext::id();
        if ((int) $saleDelivery->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $saleDelivery, $branchId) {

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['items'])
                ->findOrFail($saleDelivery->id);

            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('id', (int) $request->warehouse_id)
                ->firstOrFail();

            $saleDelivery->update([
                'date' => $request->date,
                'warehouse_id' => $warehouse->id,
                'note' => $request->note,
                'updated_by' => auth()->id(),
            ]);

            // replace items
            SaleDeliveryItem::where('sale_delivery_id', (int) $saleDelivery->id)->delete();

            foreach ($request->items as $row) {
                SaleDeliveryItem::create([
                    'sale_delivery_id' => (int) $saleDelivery->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity' => (int) $row['quantity'],
                    'price' => array_key_exists('price', $row) && $row['price'] !== null ? (int) $row['price'] : null,
                ]);
            }
        });

        toast('Sale Delivery Updated!', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
    }

    /**
     * ✅ status SO dihitung dari remaining qty
     */
    private function updateSaleOrderFulfillmentStatus(int $saleOrderId): void
    {
        $so = SaleOrder::query()
            ->lockForUpdate()
            ->with(['items'])
            ->findOrFail($saleOrderId);

        $remaining = $this->getRemainingQtyBySaleOrder((int) $so->id);

        $totalRemaining = 0;
        $totalOrdered = 0;

        foreach ($so->items as $it) {
            $pid = (int) $it->product_id;
            $ordered = (int) ($it->quantity ?? 0);
            $rem = (int) ($remaining[$pid] ?? 0);

            $totalOrdered += $ordered;
            $totalRemaining += $rem;
        }

        // kalau gak ada item, biarin pending
        if ($totalOrdered <= 0) {
            if ((string) $so->status !== 'pending') {
                $so->update(['status' => 'pending', 'updated_by' => auth()->id()]);
            }
            return;
        }

        if ($totalRemaining <= 0) {
            $newStatus = 'delivered';
        } elseif ($totalRemaining < $totalOrdered) {
            $newStatus = 'partial_delivered';
        } else {
            $newStatus = 'pending';
        }

        if ((string) $so->status !== $newStatus) {
            $so->update([
                'status' => $newStatus,
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function getRemainingQtyBySale(int $saleId): array
    {
        $saleDetails = DB::table('sale_details')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_id', $saleId)
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_id', $saleId)
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw('SUM(
                    CASE
                        WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0
                            THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))
                        ELSE COALESCE(sdi.quantity,0)
                    END
                ) as qty')
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($saleDetails as $row) {
            $pid = (int) $row->product_id;
            $invoiceQty = (int) $row->qty;
            $shippedQty = isset($shipped[$pid]) ? (int) $shipped[$pid]->qty : 0;

            $rem = $invoiceQty - $shippedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }

    private function getRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw('SUM(
                    CASE
                        WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0
                            THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))
                        ELSE COALESCE(sdi.quantity,0)
                    END
                ) as qty')
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $shippedQty = isset($shipped[$pid]) ? (int) $shipped[$pid]->qty : 0;

            $rem = $orderedQty - $shippedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }
}
