<?php

namespace Modules\SaleDelivery\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
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
        ]);

        DB::transaction(function () use ($request, $saleDelivery, $branchId) {

            // lock sale delivery
            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->with(['items'])
                ->findOrFail($saleDelivery->id);

            // status guard
            $status = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if (!in_array($status, ['pending'], true)) {
                abort(422, 'Sale Delivery is not pending.');
            }

            // branch context guard
            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                abort(403, 'Wrong branch context.');
            }

            // safety: reference
            if (empty($saleDelivery->reference)) {
                $saleDelivery->update([
                    'reference' => make_reference_id('SDO', (int) $saleDelivery->id),
                ]);
            }

            $reference = (string) $saleDelivery->reference;

            // anti double confirm (mutation exists)
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
                $inputById[(int) $row['id']] = [
                    'good' => (int) $row['good'],
                    'defect' => (int) $row['defect'],
                    'damaged' => (int) $row['damaged'],
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

                if ($good < 0 || $defect < 0 || $damaged < 0) {
                    abort(422, 'Invalid qty input.');
                }

                $confirmed = $good + $defect + $damaged;

                if ($confirmed > $expected) {
                    abort(422, "Confirmed qty cannot exceed expected qty for item ID {$itemId}.");
                }

                if ($confirmed < $expected) {
                    $isPartial = true;
                }

                $totalConfirmedAll += $confirmed;

                // simpan breakdown qty di item
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
             * - Lalu, kalau defect/damaged > 0:
             *   "habiskan" per-unit rows di product_defect_items / product_damaged_items
             */
            foreach ($saleDelivery->items as $it) {
                $productId = (int) $it->product_id;
                $expected = (int) ($it->quantity ?? 0);

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

                // 2) Consume defect items (moved_out_*)
                if ($defect > 0) {
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

                    DB::table('product_defect_items')
                        ->whereIn('id', $ids)
                        ->update([
                            'moved_out_at' => now(),
                            'moved_out_by' => auth()->id(),
                            'moved_out_reference_type' => SaleDelivery::class,
                            'moved_out_reference_id' => (int) $saleDelivery->id,
                        ]);
                }

                // 3) Consume damaged items (moved_out_* + mutation_out_id)
                if ($damaged > 0) {
                    $ids = DB::table('product_damaged_items')
                        ->where('branch_id', (int) $saleDelivery->branch_id)
                        ->where('warehouse_id', (int) $saleDelivery->warehouse_id)
                        ->where('product_id', $productId)
                        ->where('resolution_status', 'pending')
                        ->whereNull('moved_out_at')
                        ->orderBy('id', 'asc')
                        ->limit($damaged)
                        ->pluck('id')
                        ->all();

                    if (count($ids) !== $damaged) {
                        abort(422, "Not enough DAMAGED stock for product_id {$productId}. Needed {$damaged}, available " . count($ids) . ".");
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

            // simpan confirm note meta
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
        });

        toast('Sale Delivery confirmed successfully', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
    }

    /**
     * Samain gaya PurchaseDeliveryController biar konsisten.
     */
    private function roleString(): string
    {
        $user = auth()->user();
        if (!$user) return 'unknown';

        // kalau kamu pakai spatie permission
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            if (!empty($roles) && count($roles) > 0) return (string) $roles[0];
        }

        // fallback
        return 'user';
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $source = (string) $request->get('source', '');
        if (!in_array($source, ['quotation', 'sale'], true)) {
            abort(403, 'Sale Delivery can only be created from Quotation or Sale.');
        }

        if ($source === 'quotation' && !$request->filled('quotation_id')) abort(422, 'quotation_id is required');
        if ($source === 'sale' && !$request->filled('sale_id')) abort(422, 'sale_id is required');

        $branchId = BranchContext::id();

        // warehouse list
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->forActiveBranch($branchId)
            ->orderBy('customer_name')
            ->get();

        $products = Product::query()->orderBy('product_name')->limit(200)->get();

        // default items yang akan dipush ke form create (untuk source sale)
        $prefillItems = [];

        if ($source === 'sale') {

            $saleId = (int) $request->sale_id;

            // pastikan sale milik branch aktif (kalau sale kamu sudah pakai branch_id)
            $sale = DB::table('sales')
                ->where('id', $saleId)
                ->where('branch_id', $branchId)
                ->first();

            if (!$sale) abort(404, 'Sale (invoice) not found in this branch.');

            // hitung remaining
            $remainingMap = $this->getRemainingQtyBySale($saleId);

            // ambil detail sale untuk prefill
            $details = DB::table('sale_details')
                ->where('sale_id', $saleId)
                ->get();

            foreach ($details as $d) {
                $pid = (int) $d->product_id;
                if ($pid <= 0) continue;

                $rem = $remainingMap[$pid] ?? 0;
                if ($rem <= 0) continue; // kalau sudah habis terkirim, jangan tampil

                $prefillItems[] = [
                    'product_id' => $pid,
                    'quantity'   => $rem,
                    'price'      => (int) ($d->price ?? 0),
                ];
            }

            // kalau remaining kosong -> berarti invoice sudah fully delivered
            if (count($prefillItems) === 0) {
                toast('This invoice is already fully delivered (no remaining qty).', 'warning');
            }
        }

        return view('saledelivery::create', compact(
            'warehouses',
            'customers',
            'products',
            'source',
            'prefillItems'
        ));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $source = (string) $request->get('source', '');
        abort_unless(in_array($source, ['quotation', 'sale'], true), 403);

        $branchId = BranchContext::id();

        $rules = [
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'note' => 'nullable|string|max:2000',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|integer|min:0',
        ];

        if ($source === 'quotation') {
            $rules['quotation_id'] = 'required|integer';
        }

        if ($source === 'sale') {
            $rules['sale_id'] = 'required|integer';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $branchId, $source) {

            $customer = Customer::query()
                ->forActiveBranch($branchId)
                ->where('id', $request->customer_id)
                ->firstOrFail();

            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('id', $request->warehouse_id)
                ->firstOrFail();

            // guard sale (invoice) belongs to branch
            $saleId = null;
            if ($source === 'sale') {
                $saleId = (int) $request->sale_id;

                $sale = DB::table('sales')
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (!$sale) abort(404, 'Sale (invoice) not found in this branch.');
            }

            $delivery = SaleDelivery::create([
                'branch_id'    => $branchId,

                'quotation_id' => $source === 'quotation' ? (int) $request->quotation_id : null,
                'sale_id'      => $source === 'sale' ? (int) $saleId : null,

                'customer_id'  => $customer->id,
                'date'         => $request->date,
                'warehouse_id' => $warehouse->id,

                'status'       => 'pending',
                'note'         => $request->note,
                'created_by'   => Auth::id(),
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

        $saleDelivery->load(['items.product', 'warehouse', 'customer']);

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        return view('saledelivery::edit', compact('saleDelivery', 'warehouses'));
    }

    public function update(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('edit_sale_deliveries'), 403);

        $active = session('active_branch');
        if ($active === 'all' || empty($active)) {
            abort(403, "Please choose a specific branch first (not 'All Branch').");
        }

        $branchId = BranchContext::id();

        DB::transaction(function () use ($request, $saleDelivery, $branchId) {

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($saleDelivery->id);

            if (strtolower((string) $saleDelivery->status) !== 'pending') {
                abort(422, 'Only pending Sale Delivery can be edited.');
            }

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                abort(403, 'Wrong branch context.');
            }

            $request->validate([
                'date' => 'required|date',
                'warehouse_id' => 'required|integer',
                'note' => 'nullable|string|max:2000',
            ]);

            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('id', (int) $request->warehouse_id)
                ->firstOrFail();

            $saleDelivery->update([
                'date'         => $request->date,
                'warehouse_id' => $warehouse->id,
                'note'         => $request->note,
            ]);
        });

        toast('Sale Delivery Updated!', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
    }

    public function destroy($id)
    {
        //
    }

    private function getRemainingQtyBySale(int $saleId): array
    {
        // hasil: [product_id => remaining_qty]
        // sumber qty invoice dari sale_details.quantity
        $saleDetails = DB::table('sale_details')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_id', $saleId)
            ->groupBy('product_id')
            ->get();

        // total already shipped dari sale_delivery_items untuk sale_deliveries sale_id tertentu
        // hanya yang sudah confirmed (confirmed_at != null) ATAU status confirmed/partial
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

}
