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
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryItem;

class SaleDeliveryController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('access_sale_deliveries'), 403);

        $deliveries = SaleDelivery::query()
            ->withCount('items')
            ->latest('id')
            ->paginate(20);

        return view('saledelivery::index', compact('deliveries'));
    }

    public function show(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        $saleDelivery->load(['items']);

        return view('saledelivery::show', compact('saleDelivery'));
    }

    public function create()
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        $branchId = BranchContext::id();

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        $customers = Customer::query()
            ->forActiveBranch($branchId)
            ->orderBy('customer_name')
            ->get();

        // Product list optional untuk MVP (boleh taruh di UI pakai livewire search-product)
        $products = Product::query()->orderBy('product_name')->limit(200)->get();

        return view('saledelivery::create', compact('warehouses', 'customers', 'products'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

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

            // customer harus global atau branch aktif
            $customer = Customer::query()
                ->where('id', $request->customer_id)
                ->forActiveBranch($branchId)
                ->firstOrFail();

            // warehouse harus milik branch aktif
            $warehouse = Warehouse::query()
                ->where('id', $request->warehouse_id)
                ->where('branch_id', $branchId)
                ->firstOrFail();

            $delivery = SaleDelivery::create([
                'branch_id' => $branchId,
                'quotation_id' => $request->quotation_id ?: null,
                'customer_id' => $customer->id,
                'date' => $request->date,
                'warehouse_id' => $warehouse->id,
                'status' => 'pending',
                'note' => $request->note,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $row) {
                SaleDeliveryItem::create([
                    'sale_delivery_id' => $delivery->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity' => (int) $row['quantity'],
                    'price' => isset($row['price']) ? (int) $row['price'] : null,
                ]);
            }
        });

        toast('Sale Delivery Created!', 'success');
        return redirect()->route('sale-deliveries.index');
    }

    public function confirmForm(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        if (strtolower($saleDelivery->status) !== 'pending') {
            abort(422, 'Sale Delivery is not pending.');
        }

        $saleDelivery->load('items');

        return view('saledelivery::confirm', compact('saleDelivery'));
    }

    public function confirmStore(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('confirm_sale_deliveries'), 403);

        if (strtolower($saleDelivery->status) !== 'pending') {
            abort(422, 'Sale Delivery is not pending.');
        }

        $branchId = BranchContext::id();

        // pastikan user confirm sesuai branch aktif
        if ((int)$saleDelivery->branch_id !== (int)$branchId) {
            abort(403, 'Wrong branch context.');
        }

        DB::transaction(function () use ($saleDelivery, $branchId) {

            $saleDelivery->load('items');

            // MUTATION OUT untuk tiap item
            foreach ($saleDelivery->items as $item) {

                $last = Mutation::query()
                    ->where('warehouse_id', $saleDelivery->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->latest('id')
                    ->first();

                $stockEarly = $last ? (int)$last->stock_last : 0;
                $stockOut   = (int)$item->quantity;
                $stockLast  = $stockEarly - $stockOut;

                // MVP: kita belum bikin “issue” kalau minus.
                // Kalau kamu mau strict: kalau $stockLast < 0 -> abort 422.
                if ($stockLast < 0) {
                    abort(422, "Stock not enough for product_id={$item->product_id}. Available={$stockEarly}, need={$stockOut}");
                }

                $mutationData = [
                    'reference' => $saleDelivery->reference,
                    'date' => $saleDelivery->date,
                    'mutation_type' => 'Out',
                    'note' => "Sales Delivery OUT #{$saleDelivery->reference}",
                    'warehouse_id' => $saleDelivery->warehouse_id,
                    'product_id' => $item->product_id,
                    'stock_early' => $stockEarly,
                    'stock_in' => 0,
                    'stock_out' => $stockOut,
                    'stock_last' => $stockLast,
                ];

                // branch aware kalau kolom ada
                if (\Illuminate\Support\Facades\Schema::hasColumn('mutations', 'branch_id')) {
                    $mutationData['branch_id'] = $branchId;
                }

                Mutation::create($mutationData);
            }

            $saleDelivery->update([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);
        });

        toast('Sale Delivery Confirmed!', 'success');
        return redirect()->route('sale-deliveries.show', $saleDelivery->id);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('saledelivery::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
