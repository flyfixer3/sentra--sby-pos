<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Product;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;
use Modules\PurchaseOrder\DataTables\PurchaseOrdersDataTable;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Entities\PurchaseOrderDetails;
use Modules\PurchaseOrder\Http\Requests\StorePurchaseOrderRequest;
use Modules\PurchaseOrder\Http\Requests\UpdatePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function index(PurchaseOrdersDataTable $dataTable)
    {
        abort_if(Gate::denies('access_purchase_orders'), 403);

        return $dataTable->render('purchase-orders::index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_purchase_orders'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('purchase-orders.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create a Purchase Order.");
        }

        return view('purchase-orders::create');
    }


    public function store(Request $request)
    {
        abort_if(Gate::denies('create_purchase_orders'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('purchase-orders.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create a Purchase Order.");
        }
        $branchId = (int) $active;

        $request->validate([
            'reference'            => 'required|string|max:50',
            'supplier_id'          => 'required|integer|exists:suppliers,id',
            'date'                 => 'required|date',
            'note'                 => 'nullable|string|max:1000',

            'tax_percentage'       => 'required|numeric|min:0|max:100',
            'discount_percentage'  => 'required|numeric|min:0|max:100',
            'shipping_amount'      => 'required|numeric|min:0',
            'total_amount'         => 'required|numeric|min:0',
            'total_quantity'       => 'required|integer|min:0',
        ]);

        $cart = Cart::instance('purchase_order');

        if ($cart->count() <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Cart is empty. Please add products first.');
        }

        DB::transaction(function () use ($request, $branchId, $cart) {

            $supplier = Supplier::findOrFail((int) $request->supplier_id);

            // ==========================
            // ✅ AGGREGATE QTY PER PRODUCT FROM CART
            // ==========================
            $qtyByProduct = [];
            foreach ($cart->content() as $item) {
                $pid = (int) ($item->id ?? 0);
                $qty = (int) ($item->qty ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;

                if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                $qtyByProduct[$pid] += $qty;
            }

            if (empty($qtyByProduct)) {
                throw new \RuntimeException('Cart items invalid.');
            }

            // ==========================
            // ✅ CREATE PO (header)
            // ==========================
            $purchaseOrder = PurchaseOrder::create([
                'branch_id'            => $branchId,
                'date'                 => $request->date,
                'reference'            => $request->reference,
                'supplier_id'          => (int) $request->supplier_id,
                'supplier_name'        => $supplier->supplier_name,

                'tax_percentage'       => (float) $request->tax_percentage,
                'discount_percentage'  => (float) $request->discount_percentage,
                'shipping_amount'      => (float) $request->shipping_amount,
                'total_amount'         => (float) $request->total_amount,

                'status'               => 'Pending',
                'note'                 => $request->note,
                'tax_amount'           => (float) $cart->tax(),
                'discount_amount'      => (float) $cart->discount(),
                'created_by'           => auth()->id(),
            ]);

            // ==========================
            // ✅ CREATE PO DETAILS
            // ==========================
            foreach ($cart->content() as $item) {
                PurchaseOrderDetails::create([
                    'purchase_order_id'        => (int) $purchaseOrder->id,
                    'product_id'               => (int) $item->id,
                    'product_name'             => (string) $item->name,
                    'product_code'             => (string) $item->options->code,
                    'quantity'                 => (int) $item->qty,
                    'fulfilled_quantity'       => 0,
                    'price'                    => (float) $item->price,
                    'unit_price'               => (float) $item->options->unit_price,
                    'sub_total'                => (float) $item->options->sub_total,
                    'product_discount_amount'  => (float) $item->options->product_discount,
                    'product_discount_type'    => (string) $item->options->product_discount_type,
                    'product_tax_amount'       => (float) $item->options->product_tax,
                ]);
            }

            // ==========================
            // ✅ STEP 2: INCREASE qty_incoming (stocks)
            // - incoming at branch-level (warehouse_id NULL)
            // ==========================
            foreach ($qtyByProduct as $pid => $qty) {

                $existing = DB::table('stocks')
                    ->where('branch_id', (int) $branchId)
                    ->whereNull('warehouse_id')
                    ->where('product_id', (int) $pid)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    DB::table('stocks')
                        ->where('id', (int) $existing->id)
                        ->update([
                            'qty_incoming' => (int) ($existing->qty_incoming ?? 0) + (int) $qty,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('stocks')->insert([
                        'branch_id'    => (int) $branchId,
                        'warehouse_id' => null,
                        'product_id'   => (int) $pid,

                        'qty_available' => 0,
                        'qty_reserved'  => 0,
                        'qty_incoming'  => (int) $qty,
                        'min_stock'     => 0,

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $cart->destroy();
        });

        toast('Purchase Order Created!', 'success');
        return redirect()->route('purchase-orders.index');
    }

    public function show($id)
    {
        abort_if(\Illuminate\Support\Facades\Gate::denies('show_purchase_orders'), 403);

        $purchase_order = \Modules\PurchaseOrder\Entities\PurchaseOrder::with([
            'purchaseOrderDetails',
            'purchases',
            'purchaseDeliveries',
            'supplier',
            'creator',
            'branch',
        ])->findOrFail($id);

        $supplier = $purchase_order->supplier;

        // ✅ SUMMARY: fulfilled/total/remaining (rule: remaining per item max(0, qty - fulfilled))
        $totalOrderedQty = (int) $purchase_order->purchaseOrderDetails->sum('quantity');
        $totalFulfilledQty = (int) $purchase_order->purchaseOrderDetails->sum('fulfilled_quantity');

        $totalRemainingQty = (int) $purchase_order->purchaseOrderDetails->sum(function ($d) {
            return max(0, (int) $d->quantity - (int) $d->fulfilled_quantity);
        });

        return view('purchase-orders::show', compact(
            'purchase_order',
            'supplier',
            'totalOrderedQty',
            'totalFulfilledQty',
            'totalRemainingQty'
        ));
    }


    public function edit(PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('edit_purchase_orders'), 403);

        $purchase_order_details = $purchase_order->purchaseOrderDetails;

        Cart::instance('purchase_order')->destroy();
        $cart = Cart::instance('purchase_order');

        foreach ($purchase_order_details as $purchase_order_detail) {
            $cart->add([
                'id'      => $purchase_order_detail->product_id,
                'name'    => $purchase_order_detail->product_name,
                'qty'     => $purchase_order_detail->quantity,
                'price'   => $purchase_order_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount'      => $purchase_order_detail->product_discount_amount,
                    'product_discount_type' => $purchase_order_detail->product_discount_type,
                    'sub_total'             => $purchase_order_detail->sub_total,
                    'code'                  => $purchase_order_detail->product_code,
                    'stock'                 => Product::findOrFail($purchase_order_detail->product_id)->product_quantity,
                    'product_tax'           => $purchase_order_detail->product_tax_amount,
                    'unit_price'            => $purchase_order_detail->unit_price
                ]
            ]);
        }

        return view('purchase-orders::edit', compact('purchase_order'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('edit_purchase_orders'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Please select a specific branch first (not 'All Branch') to update a Purchase Order.");
        }
        $branchId = (int) $active;

        DB::transaction(function () use ($request, $purchase_order, $branchId) {

            // pastikan PO ini milik branch aktif
            if ((int) $purchase_order->branch_id !== $branchId) {
                abort(403, 'You can only edit Purchase Orders from the active branch.');
            }

            // ambil fulfilled lama per product supaya tidak hilang kalau item masih sama
            $oldFulfilledMap = $purchase_order->purchaseOrderDetails()
                ->get()
                ->mapWithKeys(fn ($d) => [(int) $d->product_id => (int) $d->fulfilled_quantity])
                ->toArray();

            // hapus detail lama
            $purchase_order->purchaseOrderDetails()->delete();

            // update header
            $purchase_order->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase_order')->tax() * 1,
                'discount_amount' => Cart::instance('purchase_order')->discount() * 1,
            ]);

            // re-create details dari cart + preserve fulfilled
            foreach (Cart::instance('purchase_order')->content() as $cart_item) {

                $fulfilled_qty = (int) ($oldFulfilledMap[(int) $cart_item->id] ?? 0);

                // clamp: jangan sampai fulfilled lebih besar dari qty baru
                $newQty = (int) $cart_item->qty;
                if ($fulfilled_qty > $newQty) {
                    $fulfilled_qty = $newQty;
                }

                PurchaseOrderDetails::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id' => (int) $cart_item->id,
                    'product_name' => (string) $cart_item->name,
                    'product_code' => (string) $cart_item->options->code,
                    'quantity' => $newQty,
                    'fulfilled_quantity' => $fulfilled_qty,

                    'price' => $cart_item->price * 1,
                    'unit_price' => ($cart_item->options->unit_price ?? 0) * 1,
                    'sub_total' => ($cart_item->options->sub_total ?? 0) * 1,
                    'product_discount_amount' => ($cart_item->options->product_discount ?? 0) * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type ?? 'fixed',
                    'product_tax_amount' => ($cart_item->options->product_tax ?? 0) * 1,
                ]);
            }

            Cart::instance('purchase_order')->destroy();

            // ✅ pakai rule dari model (Pending/Partial/Completed)
            $purchase_order->refresh();
            $purchase_order->calculateFulfilledQuantity(); // kalau header fulfilled_quantity ada kolomnya, ikut ke-update
            $purchase_order->markAsCompleted();
        });

        toast('Purchase Order Updated!', 'info');
        return redirect()->route('purchase-orders.index');
    }


    public function destroy(PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('delete_purchase_orders'), 403);

        $purchase_order->delete();

        toast('Purchase Order Deleted!', 'warning');

        return redirect()->route('purchase-orders.index');
    }
}
