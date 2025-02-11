<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Product;
use Modules\PurchaseOrder\DataTables\PurchaseOrdersDataTable;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Entities\PurchaseOrderDetails;
use Modules\PurchaseOrder\Http\Requests\StorePurchaseOrderRequest;
use Modules\PurchaseOrder\Http\Requests\UpdatePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{

    public function index(PurchaseOrdersDataTable $dataTable) {
        abort_if(Gate::denies('access_purchase_orders'), 403);

        return $dataTable->render('purchase-orders::index');
    }


    public function create() {
        abort_if(Gate::denies('create_purchase_orders'), 403);

        Cart::instance('purchase_order')->destroy();

        return view('purchase-orders::create');
    }


    public function store(StorePurchaseOrderRequest $request) {
        DB::transaction(function () use ($request) {
            $purchase_order = PurchaseOrder::create([
                'date' => $request->date,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'status' => $request->status,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase_order')->tax() * 1,
                'discount_amount' => Cart::instance('purchase_order')->discount() * 1,
            ]);

            foreach (Cart::instance('purchase_order')->content() as $cart_item) {
                PurchaseOrderDetails::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'unit_price' => $cart_item->options->unit_price * 1,
                    'sub_total' => $cart_item->options->sub_total * 1,
                    'product_discount_amount' => $cart_item->options->product_discount * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax * 1,
                ]);
            }

            Cart::instance('purchase_order')->destroy();
        });

        toast('Purchase Order Created!', 'success');

        return redirect()->route('purchase-orders.index');
    }


    public function show(PurchaseOrder $purchase_order) {
        abort_if(Gate::denies('show_purchase_orders'), 403);
    
        $supplier = Supplier::findOrFail($purchase_order->supplier_id);
    
        // ✅ Load `purchaseOrderDetails()` to track fulfilled quantities
        $purchase_order->load('purchaseOrderDetails', 'purchases.purchaseDetails');
        $deliveries = $purchase_order->purchaseDeliveries; // Get all deliveries related to PO
    
        return view('purchase-orders::show', compact('purchase_order', 'supplier', 'deliveries'));
    }
    


    public function edit(PurchaseOrder $purchase_order) {
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
                    'product_discount' => $purchase_order_detail->product_discount_amount,
                    'product_discount_type' => $purchase_order_detail->product_discount_type,
                    'sub_total'   => $purchase_order_detail->sub_total,
                    'code'        => $purchase_order_detail->product_code,
                    'stock'       => Product::findOrFail($purchase_order_detail->product_id)->product_quantity,
                    'product_tax' => $purchase_order_detail->product_tax_amount,
                    'unit_price'  => $purchase_order_detail->unit_price
                ]
            ]);
        }

        return view('purchase-orders::edit', compact('purchase_order'));
    }


    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order) {
        DB::transaction(function () use ($request, $purchase_order) {
            foreach ($purchase_order->purchaseOrderDetails as $purchase_order_detail) {
                $purchase_order_detail->delete();
            }
    
            $purchase_order->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'status' => $request->status,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase_order')->tax() * 1,
                'discount_amount' => Cart::instance('purchase_order')->discount() * 1,
            ]);
    
            foreach (Cart::instance('purchase_order')->content() as $cart_item) {
                // ✅ Fetch old fulfilled quantity if available
                $existing_detail = $purchase_order->purchaseOrderDetails()->where('product_id', $cart_item->id)->first();
                $fulfilled_qty = $existing_detail ? $existing_detail->fulfilled_quantity : 0;
    
                PurchaseOrderDetails::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'fulfilled_quantity' => $fulfilled_qty, // ✅ Preserve fulfilled quantity
                    'price' => $cart_item->price * 1,
                    'unit_price' => $cart_item->options->unit_price * 1,
                    'sub_total' => $cart_item->options->sub_total * 1,
                    'product_discount_amount' => $cart_item->options->product_discount * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax * 1,
                ]);
            }
    
            Cart::instance('purchase_order')->destroy();
        });
    
        toast('Purchase Order Updated!', 'info');
        return redirect()->route('purchase-orders.index');
    }
    


    public function destroy(PurchaseOrder $purchase_order) {
        abort_if(Gate::denies('delete_purchase_orders'), 403);

        $purchase_order->delete();

        toast('PurchaseOrder Deleted!', 'warning');

        return redirect()->route('purchase_orders.index');
    }
}
