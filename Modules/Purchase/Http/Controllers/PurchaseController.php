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
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\Purchase\Entities\PurchasePayment;
use App\Helpers\Helper;
use Carbon\Carbon;
use Modules\Purchase\Http\Requests\StorePurchaseRequest;
use Modules\Purchase\Http\Requests\UpdatePurchaseRequest;
use Illuminate\Support\Facades\Schema;

class PurchaseController extends Controller
{
    public function index(PurchaseDataTable $dataTable) {
        abort_if(Gate::denies('access_purchases'), 403);

        return $dataTable->render('purchase::index');
    }

    public function createFromDelivery(PurchaseDelivery $purchaseDelivery) {
        abort_if(Gate::denies('create_purchases'), 403);

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $purchase_order = $purchaseDelivery->purchaseOrder;

        /**
         * Resolve branch + default warehouse (main)
         * Note: kalau nanti di UI kamu mau selectable warehouse, bisa override.
         */
        $activeBranchId = $this->getActiveBranchId();
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($activeBranchId);

        foreach ($purchaseDelivery->purchaseDeliveryDetails as $item) {
            $po_detail = $purchase_order->purchaseOrderDetails()
                ->where('product_id', $item->product_id)
                ->first();

            $cart->add([
                'id'      => $item->product_id,
                'name'    => $item->product_name,
                'qty'     => $item->quantity,
                'price'   => $po_detail ? $po_detail->price : $item->unit_price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $po_detail ? $po_detail->product_discount_amount : 0,
                    'product_discount_type' => 'fixed',
                    'sub_total'   => $po_detail ? ($item->quantity * $po_detail->price) : ($item->quantity * $item->unit_price),
                    'code'        => $item->product_code,
                    'stock'       => Product::findOrFail($item->product_id)->product_quantity,
                    'product_tax' => $po_detail ? $po_detail->product_tax_amount : 0,
                    'unit_price'  => $po_detail ? $po_detail->unit_price : $item->unit_price,
                    // ✅ penting: set warehouse_id ke cart item
                    'warehouse_id' => $defaultWarehouseId,
                    // optional simpan branch juga biar gampang debug
                    'branch_id' => $activeBranchId,
                ]
            ]);
        }

        return view('purchase-orders::purchase-order-purchases.create', [
            'purchase_delivery_id' => $purchaseDelivery->id,
            'purchaseDelivery' => $purchaseDelivery,
            'purchase_order_id' => $purchase_order->id,
            // untuk UI kalau mau ditampilkan
            'activeBranchId' => $activeBranchId,
            'defaultWarehouseId' => $defaultWarehouseId,
        ]);
    }

    public function create() {
        abort_if(Gate::denies('create_purchases'), 403);

        Cart::instance('purchase')->destroy();

        $activeBranchId = $this->getActiveBranchId();
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($activeBranchId);

        return view('purchase::create', [
            'activeBranchId' => $activeBranchId,
            'defaultWarehouseId' => $defaultWarehouseId,
        ]);
    }

    public function store(StorePurchaseRequest $request) {
        DB::transaction(function () use ($request) {

            /**
             * ✅ KUNCI BRANCH + WAREHOUSE
             * - branch_id: ambil dari active branch (session)
             * - warehouse_id: dari request kalau ada, kalau tidak -> main warehouse cabang tersebut
             */
            $branchId = $this->getActiveBranchId();
            $warehouseId = $request->warehouse_id ? (int)$request->warehouse_id : $this->resolveDefaultWarehouseId($branchId);

            // Validasi: warehouse harus belong ke branch aktif
            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);

            // Pastikan semua item cart punya warehouse_id
            $this->ensureCartItemsHaveWarehouse($warehouseId);

            $due_amount = $request->total_amount - $request->paid_amount;
            $payment_status = $due_amount == $request->total_amount ? 'Unpaid' : ($due_amount > 0 ? 'Partial' : 'Paid');

            $purchase_order = null;
            if ($request->has('purchase_order_id')) {
                $purchase_order = PurchaseOrder::findOrFail($request->purchase_order_id);
                $purchase_order->update(['status' => 'Partially Sent']);
            }

            // ✅ Simpan branch_id dan warehouse_id ke purchases
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

                // ✅ tambahin ini
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
            ]);

            foreach (Cart::instance('purchase')->content() as $cart_item) {

                $itemWarehouseId = isset($cart_item->options->warehouse_id) ? (int)$cart_item->options->warehouse_id : $warehouseId;
                $this->assertWarehouseBelongsToBranch($itemWarehouseId, $branchId);

                // ✅ PurchaseDetail wajib simpan warehouse_id
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

                    // ✅ ini penting
                    'warehouse_id' => $itemWarehouseId,
                ]);

                if ($purchase_order) {
                    $purchase_order_detail = $purchase_order->purchaseOrderDetails()->where('product_id', $cart_item->id)->first();

                    if ($purchase_order_detail) {
                        $new_fulfilled_quantity = $purchase_order_detail->fulfilled_quantity + $cart_item->qty;
                        if ($new_fulfilled_quantity > $purchase_order_detail->quantity) {
                            throw new \Exception("Cannot fulfill more than ordered quantity!");
                        }
                        $purchase_order_detail->update(['fulfilled_quantity' => $new_fulfilled_quantity]);
                    }
                }

                // Update stock if purchase is "Completed"
                if ($request->status == 'Completed') {

                    $mutation = Mutation::with('product')
                        ->where('product_id', $cart_item->id)
                        ->where('warehouse_id', $itemWarehouseId)
                        ->latest()
                        ->first();

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

                    $mutationData = [
                        'reference' => $purchase->reference,
                        'date' => $request->date,
                        'mutation_type' => "In",
                        'note' => "Mutation for Purchase: " . $purchase->reference,
                        'warehouse_id' => $itemWarehouseId,
                        'product_id' => $cart_item->id,
                        'stock_early' => $mutation ? $mutation->stock_last : 0,
                        'stock_in' => $cart_item->qty,
                        'stock_out' => 0,
                        'stock_last' => ($mutation ? $mutation->stock_last : 0) + $cart_item->qty,
                    ];

                    // ✅ kalau mutations table punya branch_id, isi
                    if (Schema::hasColumn('mutations', 'branch_id')) {
                        $mutationData['branch_id'] = $branchId;
                    }

                    Mutation::create($mutationData);
                }
            }

            if ($purchase_order) {
                $total_remaining = $purchase_order->purchaseOrderDetails()->sum('quantity') - $purchase_order->purchaseOrderDetails()->sum('fulfilled_quantity');
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

        $activeBranchId = $this->getActiveBranchId();
        $defaultWarehouseId = $purchase->warehouse_id ? (int)$purchase->warehouse_id : $this->resolveDefaultWarehouseId($activeBranchId);

        foreach ($purchase_details as $purchase_detail) {

            $total_stock = Mutation::with('warehouse')
                ->where('product_id', $purchase_detail->product_id)
                ->latest()
                ->get()
                ->unique('warehouse_id')
                ->sortByDesc('stock_last')
                ->sum('stock_last');

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

                    // ✅ jangan hardcode 99
                    'warehouse_id'=> $purchase_detail->warehouse_id ? (int)$purchase_detail->warehouse_id : $defaultWarehouseId,

                    'stock'       => $total_stock,
                    'product_tax' => $purchase_detail->product_tax_amount,
                    'unit_price'  => $purchase_detail->unit_price,
                    'branch_id'   => $activeBranchId,
                ]
            ]);
        }

        return view('purchase::edit', compact('purchase'));
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase) {
        DB::transaction(function () use ($request, $purchase) {

            $branchId = $this->getActiveBranchId();
            $warehouseId = $request->warehouse_id ? (int)$request->warehouse_id : ($purchase->warehouse_id ? (int)$purchase->warehouse_id : $this->resolveDefaultWarehouseId($branchId));
            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);
            $this->ensureCartItemsHaveWarehouse($warehouseId);

            $due_amount = $request->total_amount - $request->paid_amount;
            $payment_status = $due_amount == $request->total_amount ? 'Unpaid' : ($due_amount > 0 ? 'Partial' : 'Paid');

            foreach ($purchase->purchaseDetails as $purchase_detail) {
                if ($purchase->status == 'Completed') {
                    if($purchase_detail->warehouse_id != 2){
                        $mutation = Mutation::with('product')
                            ->where('product_id', $purchase_detail->product_id)
                            ->where('warehouse_id', $purchase_detail->warehouse_id)
                            ->latest()
                            ->first();

                        $_stock_early = $mutation ? $mutation->stock_last : 0;
                        $_stock_in = 0;
                        $_stock_out = $purchase_detail->quantity;
                        $_stock_last = $_stock_early - $_stock_out;

                        $mutationData = [
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
                        ];

                        if (Schema::hasColumn('mutations', 'branch_id')) {
                            $mutationData['branch_id'] = $branchId;
                        }

                        Mutation::create($mutationData);

                        $product = Product::findOrFail($purchase_detail->product_id);
                        if($mutation){
                            if(($mutation->stock_last) == 0 || ($mutation->stock_last - $purchase_detail->quantity) <= 0){
                                $product->update(['product_cost' => 0]);
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
                            $product->update(['product_cost' => 0]);
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

                // ✅ lock
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
            ]);

            foreach (Cart::instance('purchase')->content() as $cart_item) {
                $itemWarehouseId = isset($cart_item->options->warehouse_id) ? (int)$cart_item->options->warehouse_id : $warehouseId;
                $this->assertWarehouseBelongsToBranch($itemWarehouseId, $branchId);

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'warehouse_id' => $itemWarehouseId,
                    'unit_price' => $cart_item->options->unit_price * 1,
                    'sub_total' => $cart_item->options->sub_total * 1,
                    'product_discount_amount' => $cart_item->options->product_discount * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => $cart_item->options->product_tax * 1,
                ]);

                if ($request->status == 'Completed') {
                    $product = Product::findOrFail($cart_item->id);
                    $mutation = Mutation::with('product')
                        ->where('product_id', $cart_item->id)
                        ->where('warehouse_id', $itemWarehouseId)
                        ->latest()
                        ->first();

                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    $_stock_in = $cart_item->qty;
                    $_stock_out = 0;
                    $_stock_last = $_stock_early + $_stock_in;

                    $mutationData = [
                        'reference' => $purchase->reference,
                        'date' => $request->date,
                        'mutation_type' => "In",
                        'note' => "Mutation for Purchase: ". $purchase->reference,
                        'warehouse_id' => $itemWarehouseId,
                        'product_id' => $cart_item->id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_last,
                    ];

                    if (Schema::hasColumn('mutations', 'branch_id')) {
                        $mutationData['branch_id'] = $branchId;
                    }

                    Mutation::create($mutationData);

                    if($mutation){
                        if($mutation->stock_last == 0){
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

    /**
     * =========================
     * Helpers khusus kunci multi-branch/warehouse
     * =========================
     */

    private function getActiveBranchId(): int
    {
        // Sesuaikan kalau kamu pakai session('active_branch') bentuk lain
        $active = session('active_branch');

        // Kalau "all", store purchase harus pilih branch dari request,
        // tapi karena di PurchaseController kamu belum kirim branch_id di request,
        // kita default ke user branch kalau ada atau fallback 1.
        if ($active === 'all' || $active === null) {
            // kalau user punya branch_id
            if (auth()->check() && isset(auth()->user()->branch_id) && auth()->user()->branch_id) {
                return (int) auth()->user()->branch_id;
            }
            return 1; // fallback terakhir, ganti sesuai kebutuhan kamu
        }

        return (int) $active;
    }

    private function resolveDefaultWarehouseId(int $branchId): int
    {
        $mainId = DB::table('warehouses')
            ->where('branch_id', $branchId)
            ->where('is_main', 1)
            ->value('id');

        if (!$mainId) {
            throw new \RuntimeException("Main warehouse untuk branch_id={$branchId} belum ada. Set salah satu warehouse jadi main.");
        }

        return (int) $mainId;
    }

    private function assertWarehouseBelongsToBranch(int $warehouseId, int $branchId): void
    {
        $exists = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('branch_id', $branchId)
            ->exists();

        if (!$exists) {
            throw new \RuntimeException("Warehouse (id={$warehouseId}) tidak belong ke branch (id={$branchId}).");
        }
    }

    private function ensureCartItemsHaveWarehouse(int $fallbackWarehouseId): void
    {
        $cart = Cart::instance('purchase');

        foreach ($cart->content() as $row) {
            $has = isset($row->options->warehouse_id) && $row->options->warehouse_id;
            if (!$has) {
                // update options di cart row
                $newOptions = (array) $row->options;
                $newOptions['warehouse_id'] = $fallbackWarehouseId;

                $cart->update($row->rowId, [
                    'options' => $newOptions
                ]);
            }
        }
    }
}
