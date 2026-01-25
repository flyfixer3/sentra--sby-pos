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

        DB::transaction(function () use ($saleDelivery, $branchId) {

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($saleDelivery->id);

            if (strtolower((string) $saleDelivery->status) !== 'pending') {
                abort(422, 'Sale Delivery is not pending.');
            }

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                abort(403, 'Wrong branch context.');
            }

            $saleDelivery->loadMissing(['items', 'warehouse']);

            if (empty($saleDelivery->reference)) {
                $saleDelivery->update([
                    'reference' => make_reference_id('SDO', (int) $saleDelivery->id),
                ]);
            }

            $exists = Mutation::withoutGlobalScopes()
                ->where('reference', (string) $saleDelivery->reference)
                ->where('note', 'like', 'Sales Delivery OUT%')
                ->exists();

            if ($exists) {
                abort(422, 'This sale delivery was already confirmed (stock movement exists).');
            }

            foreach ($saleDelivery->items as $item) {
                $this->mutationController->applyInOut(
                    (int) $branchId,
                    (int) $saleDelivery->warehouse_id,
                    (int) $item->product_id,
                    'Out',
                    (int) $item->quantity,
                    (string) $saleDelivery->reference,
                    "Sales Delivery OUT #{$saleDelivery->reference} | WH {$saleDelivery->warehouse_id}",
                    (string) $saleDelivery->getRawOriginal('date')
                );
            }

            $saleDelivery->update([
                'status'       => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);
        });

        toast('Sale Delivery Confirmed!', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
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

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->forActiveBranch($branchId)
            ->orderBy('customer_name')
            ->get();

        $products = Product::query()->orderBy('product_name')->limit(200)->get();

        return view('saledelivery::create', compact('warehouses', 'customers', 'products'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $source = (string) $request->get('source', '');
        abort_unless(in_array($source, ['quotation', 'sale'], true), 403);

        $branchId = BranchContext::id();

        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'note' => 'nullable|string|max:2000',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $branchId) {

            $customer = Customer::query()
                ->forActiveBranch($branchId)
                ->where('id', $request->customer_id)
                ->firstOrFail();

            $warehouse = Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('id', $request->warehouse_id)
                ->firstOrFail();

            $delivery = SaleDelivery::create([
                'branch_id'    => $branchId,
                'quotation_id' => $request->quotation_id ?: null,
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
}
