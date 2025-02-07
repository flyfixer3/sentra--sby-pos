<?php

namespace Modules\Purchase\Http\Controllers;

use Modules\Purchase\DataTables\PurchaseDataTable;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use App\Models\AccountingAccount;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\Purchase\Entities\PurchasePayment;
use App\Helpers\Helper;
use Carbon\Carbon;
use Modules\Purchase\Http\Requests\StorePurchaseRequest;
use Modules\Purchase\Http\Requests\UpdatePurchaseRequest;

class PurchaseController extends Controller
{

    public function index(PurchaseDataTable $dataTable) {
        abort_if(Gate::denies('access_purchases'), 403);

        return $dataTable->render('purchase::index');
    }


    public function create() {
        abort_if(Gate::denies('create_purchases'), 403);

        Cart::instance('purchase')->destroy();

        return view('purchase::create');
    }


    public function store(StorePurchaseRequest $request) {
        DB::transaction(function () use ($request) {
            $due_amount = $request->total_amount - $request->paid_amount;
            $payment_status = $due_amount == $request->total_amount ? 'Unpaid' : ($due_amount > 0 ? 'Partial' : 'Paid');
    
            // 🔹 Jika Purchase dibuat dari Purchase Order
            $purchase_order = null;
            if ($request->has('purchase_order_id')) {
                $purchase_order = PurchaseOrder::findOrFail($request->purchase_order_id);
                $purchase_order->update(['status' => 'Partially Sent']); // Update status PO sementara
            }
            // $total_amount = 0;
            // $total_quantity = 0;

            // foreach (Cart::instance('purchase')->content() as $cart_item) {
            //     $total_amount += $cart_item->options->sub_total; // ✅ Ensure total_amount is recalculated
            //     $total_quantity += $cart_item->qty;
            // }
    
            // 🔹 Buat Purchase Invoice
            $purchase = Purchase::create([
                'purchase_order_id' => $request->purchase_order_id ?? null,
                'date' => $request->date,
                'due_date' => $request->due_date,
                'reference_supplier' => $request->reference_supplier,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'due_amount' => $due_amount * 1,
                'status' => $request->status,
                'total_quantity' => $request->total_quantity,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase')->tax() * 1,
                'discount_amount' => Cart::instance('purchase')->discount() * 1,
            ]);
    
            foreach (Cart::instance('purchase')->content() as $cart_item) {
                // 🔹 Simpan Detail Purchase
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
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
    
                // 🔹 Jika Purchase berasal dari PO, update jumlah barang di PO
                if ($purchase_order) {
                    $purchase_order_detail = $purchase_order->purchaseOrderDetails()->where('product_id', $cart_item->id)->first();
    
                    if ($purchase_order_detail) {
                        $new_quantity = $purchase_order_detail->quantity - $cart_item->qty;
                        if ($new_quantity < 0) {
                            throw new \Exception("Jumlah barang dalam Purchase Order tidak mencukupi!");
                        }
    
                        $purchase_order_detail->update(['quantity' => $new_quantity]);
                    }
                }
    
                // 🔹 Jika status Purchase "Completed", update stok produk
                if ($request->status == 'Completed') {
                    $mutation = Mutation::with('product')->where('product_id', $cart_item->id)
                        ->where('warehouse_id', $cart_item->options->warehouse_id)
                        ->latest()->first();
                    $product = Product::findOrFail($cart_item->id);
    
                    if ($mutation) {
                        if ($mutation->stock_last == 0) {
                            $product->update([
                                'product_cost' => (($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage)) / 
                                    ($cart_item->qty) + ($request->shipping_amount / $request->total_quantity)),
                                'product_quantity' => $product->product_quantity + $cart_item->qty
                            ]);
                        } else {
                            $product->update([
                                'product_cost' => ((($mutation['product']->product_cost * $mutation->stock_last)  +
                                    (($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage)) + 
                                    (($request->shipping_amount / $request->total_quantity) * $cart_item->qty)))
                                    / ($mutation->stock_last + $cart_item->qty)),
                                'product_quantity' => $product->product_quantity + $cart_item->qty
                            ]);
                        }
                    }
    
                    Mutation::create([
                        'reference' => $purchase->reference,
                        'date' => $request->date,
                        'mutation_type' => "In",
                        'note' => "Mutation for Purchase: " . $purchase->reference,
                        'warehouse_id' => $cart_item->options->warehouse_id,
                        'product_id' => $cart_item->id,
                        'stock_early' => $mutation ? $mutation->stock_last : 0,
                        'stock_in' => $cart_item->qty,
                        'stock_out' => 0,
                        'stock_last' => ($mutation ? $mutation->stock_last : 0) + $cart_item->qty,
                    ]);
                }
            }
    
            // 🔹 Jika Purchase berasal dari PO, update status PO
            if ($purchase_order) {
                $total_remaining = $purchase_order->purchaseOrderDetails()->sum('quantity');
                $purchase_order->update(['status' => $total_remaining > 0 ? 'Partially Sent' : 'Completed']);
            }
    
            Cart::instance('purchase')->destroy();
    
            if ($purchase->paid_amount > 0) {
                $created_payment = PurchasePayment::create([
                    'date' => $request->date,
                    'reference' => 'INV/' . $purchase->reference,
                    'amount' => $purchase->paid_amount,
                    'purchase_id' => $purchase->id,
                    'payment_method' => $request->payment_method
                ]);
                
                Helper::addNewTransaction([
                    'date' => Carbon::now(),
                    'label' => "Payment for Purchase Order #" . $purchase->reference,
                    'description' => "Purchase ID: " . $purchase->reference,
                    'purchase_id' => null,
                    'purchase_payment_id' => $created_payment->id,
                    'purchase_return_id' => null,
                    'purchase_return_payment_id' => null,
                    'sale_id' => null,
                    'sale_payment_id' => null,
                    'sale_return_id' => null,
                    'sale_return_payment_id' => null,

                ], [
                    [
                        'subaccount_number' => '1-10200',
                        'amount' => $created_payment->amount,
                        'type' => 'debit'
                    ],
                    [
                        'subaccount_number' => $created_payment->payment_method,
                        'amount' => $created_payment->amount,
                        'type' => 'credit'
                    ]
                ]);
            }
        });
    
        toast('Purchase Created!', 'success');
        return redirect()->route('purchases.index');
    }
    

    public function show(Purchase $purchase) {
        abort_if(Gate::denies('show_purchases'), 403);

        $supplier = Supplier::findOrFail($purchase->supplier_id);

        return view('purchase::show', compact('purchase', 'supplier'));
    }


    public function edit(Purchase $purchase) {
        abort_if(Gate::denies('edit_purchases'), 403);

        $purchase_details = $purchase->purchaseDetails;

        Cart::instance('purchase')->destroy();

        $cart = Cart::instance('purchase');

        foreach ($purchase_details as $purchase_detail) {
            $total_stock = Mutation::with('warehouse')->where('product_id',$purchase_detail->product_id)
            ->latest()
            ->get()
            ->unique('warehouse_id')
            ->sortByDesc('stock_last')
            ->sum('stock_last');
            // dd($purchase_detail);
            $cart->add([
                'id'      => $purchase_detail->product_id,
                'name'    => $purchase_detail->product_name,
                'qty'     => $purchase_detail->quantity,
                'price'   => $purchase_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $purchase_detail->product_discount_amount,
                    'product_discount_type' => $purchase_detail->product_discount_type,
                    'sub_total'   => $purchase_detail->sub_total,
                    'code'        => $purchase_detail->product_code,
                    'warehouse_id'=> 99,
                    'stock'       => $total_stock,
                    'product_tax' => $purchase_detail->product_tax_amount,
                    'unit_price'  => $purchase_detail->unit_price
                ]
            ]);
        }

        return view('purchase::edit', compact('purchase'));
    }


    public function update(UpdatePurchaseRequest $request, Purchase $purchase) {
        DB::transaction(function () use ($request, $purchase) {
            $due_amount = $request->total_amount - $request->paid_amount;
            if ($due_amount == $request->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }
            // dd($purchase->purchaseDetails);
            foreach ($purchase->purchaseDetails as $purchase_detail) {
                if ($purchase->status == 'Completed') {
                    if($purchase_detail->warehouse_id != 2){
                        $mutation = Mutation::with('product')->where('product_id', $purchase_detail->product_id)
                        ->where('warehouse_id',$purchase_detail->warehouse_id)
                        ->latest()->first();
                        $_stock_early = $mutation ? $mutation->stock_last : 0;
                        // dd($mutation);
                        $_stock_in = 0;
                        $_stock_out = $purchase_detail->quantity;
                        $_stock_last = $_stock_early - $_stock_out;
                        
                        Mutation::create([
                            'reference' => $purchase->reference,
                            'date' => $request->date,
                            'mutation_type' => "Out",
                            'note' => "Mutation for Delete/Edit Purchase: ". $purchase->reference,
                            'warehouse_id' => $purchase_detail->warehouse_id,
                            'product_id' => $purchase_detail->product_id,
                            'stock_early' => $_stock_early,
                            'stock_in' => $_stock_in,
                            'stock_out'=> $_stock_out,
                            'stock_last'=> $_stock_last,
                        ]);
                        
                        $product = Product::findOrFail($purchase_detail->product_id);
                        if($mutation){
                            if(($mutation->stock_last) == 0 || ($mutation->stock_last - $purchase_detail->quantity) <= 0){
                                $product->update([
                                    'product_cost' => 0,
                                ]);
                            }else{
                                if($purchase->total_quantity != 0 && $purchase->shipping_amount != 0){
                                    $product->update([
                                        'product_cost' => (($product->product_cost * $mutation->stock_last)  -
                                        (($purchase_detail->sub_total - $purchase->discount_amount) + ($purchase->shipping_amount
                                        / $purchase->total_quantity * $purchase_detail->quantity))) / 
                                        ($mutation->stock_last - $purchase_detail->quantity),
                                    ]);
                                }else{
                                    $product->update([
                                        'product_cost' => (($mutation['product']->product_cost * $mutation->stock_last)  -
                                        (($purchase_detail->sub_total - $purchase->discount_amount))) / 
                                        ($mutation->stock_last - $purchase_detail->quantity),
                                    ]);
                                }
                            }
                            
                        }else{
                            $product->update([
                                'product_cost' => 0,
                            ]);
                        }
                    }
                }
                $purchase_detail->delete();
            }
            
            $purchase->update([
                'date' => $request->date,
                'due_date' => $request->due_date,
                'reference' => $request->reference,
                'reference_supplier'=> $request->reference_supplier,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'total_quantity' => $request->total_quantity,
                'due_amount' => $due_amount * 1,
                'status' => $request->status,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase')->tax() * 1,
                'discount_amount' => Cart::instance('purchase')->discount() * 1,
            ]);
            
            // dd(Cart::instance('purchase')->content());
            foreach (Cart::instance('purchase')->content() as $cart_item) {
                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'warehouse_id' => $cart_item->options->warehouse_id,
                    'unit_price' => $cart_item->options->unit_price * 1,
                    'sub_total' => $cart_item->options->sub_total * 1,
                    'product_discount_amount' => $cart_item->options->product_discount * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax * 1,
                ]);
                
                if ($request->status == 'Completed') {
                    $product = Product::findOrFail($cart_item->id);
                    $mutation = Mutation::with('product')->where('product_id', $cart_item->id)
                    ->where('warehouse_id',$cart_item->options->warehouse_id)
                    ->latest()->first();
                    
                    
                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    // dd($mutation);
                    $_stock_in = $cart_item->qty;
                    $_stock_out = 0;
                    $_stock_last = $_stock_early + $_stock_in;
                    
                    Mutation::create([
                        'reference' => $purchase->reference,
                        'date' => $request->date,
                        'mutation_type' => "In",
                        'note' => "Mutation for Purchase: ". $purchase->reference,
                        'warehouse_id' => $cart_item->options->warehouse_id,
                        'product_id' => $cart_item->id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_last,
                    ]);
                    
                    if($mutation){
                        if($mutation->stock_last == 0){
                            // dd($mutation);
                            $product->update([
                                'product_cost' => ($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage) + ($request->shipping_amount
                                / $request->total_quantity * $cart_item->qty)) / $cart_item->qty,
                                'product_quantity' => Mutation::where('product_id', $cart_item->id)
                                ->latest()->get()->unique('warehouse_id')
                                ->sum('stock_last'),
                            ]);
                        }else{
                            $product->update([
                                'product_cost' => (($product->product_cost * $mutation->stock_last)  +
                                    ($cart_item->options->sub_total - ($cart_item->options->sub_total
                                     * $request->discount_percentage) + ($request->shipping_amount
                                    / $request->total_quantity * $cart_item->qty))) / 
                                    ($mutation->stock_last + $cart_item->qty),
                                'product_quantity' => Mutation::where('product_id', $cart_item->id)
                                ->latest()->get()->unique('warehouse_id')
                                ->sum('stock_last')
                            ]);
                        }
                    }else{
                        $product->update([
                            'product_cost' => ($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage) + ($request->shipping_amount
                            / $request->total_quantity * $cart_item->qty)) / $cart_item->qty,
                            'product_quantity' => Mutation::where('product_id', $cart_item->id)
                            ->latest()->get()->unique('warehouse_id')
                            ->sum('stock_last'),
                        ]);
                    }
                }
            }

            Cart::instance('purchase')->destroy();
        });

        toast('Purchase Updated!', 'info');

        return redirect()->route('purchases.index');
    }


    public function destroy(Purchase $purchase) {
        abort_if(Gate::denies('delete_purchases'), 403);

        $purchase->delete();

        toast('Purchase Deleted!', 'warning');

        return redirect()->route('purchases.index');
    }
}
