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

    public function createFromDelivery(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('create_purchases'), 403);

        if ($purchaseDelivery->purchase) {
            return redirect()->back()
                ->with('error', 'This Purchase Delivery already has an invoice.');
        }

        $purchaseDelivery->loadMissing([
            'purchaseOrder.purchaseOrderDetails',
            'purchaseDeliveryDetails'
        ]);

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $purchaseOrder = $purchaseDelivery->purchaseOrder;

        $branchId = $this->getActiveBranchId();
        $warehouseId = $this->resolveDefaultWarehouseId($branchId);

        foreach ($purchaseDelivery->purchaseDeliveryDetails as $item) {

            $confirmedQty =
                (int)$item->qty_received +
                (int)$item->qty_defect +
                (int)$item->qty_damaged;

            if ($confirmedQty <= 0) continue;

            $poDetail = $purchaseOrder->purchaseOrderDetails()
                ->where('product_id', $item->product_id)
                ->first();

            $price = $poDetail ? (int)$poDetail->price : 0;

            $product = Product::select('id', 'product_code', 'product_name', 'product_unit')
                ->where('id', $item->product_id)
                ->first();

            // ✅ pastikan product_code tidak null (kolom DB purchase_details NOT NULL)
            $productCode = null;
            if ($product && !empty($product->product_code)) {
                $productCode = $product->product_code;
            } elseif (!empty($item->product_code)) {
                $productCode = $item->product_code;
            } else {
                $productCode = 'UNKNOWN';
            }

            $productName = $item->product_name;
            if (empty($productName) && $product) {
                $productName = $product->product_name;
            }

            // optional stock display
            $stockLast = 0;
            $mutation = Mutation::where('product_id', $item->product_id)
                ->where('warehouse_id', $warehouseId)
                ->latest()
                ->first();
            if ($mutation) $stockLast = (int)$mutation->stock_last;

            $cart->add([
                'id'    => $item->product_id,
                'name'  => $productName,
                'qty'   => $confirmedQty,
                'price' => $price,
                'weight'=> 1,
                'options' => [
                    'sub_total'   => $confirmedQty * $price,
                    'code'        => $productCode,     // ✅ FIX product_code
                    'unit_price'  => $price,
                    'warehouse_id'=> $warehouseId,
                    'branch_id'   => $branchId,
                    'stock'       => $stockLast,
                    'unit'        => $product ? $product->product_unit : null,
                    'product_discount' => 0,
                    'product_discount_type' => 'fixed',
                    'product_tax' => 0,
                ]
            ]);
        }

        return view('purchase-orders::purchase-order-purchases.create', [
            'purchaseOrder'        => $purchaseOrder,
            'purchaseDelivery'     => $purchaseDelivery,
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

    public function store(StorePurchaseRequest $request)
    {
        DB::transaction(function () use ($request) {

            $branchId = $this->getActiveBranchId();

            // NOTE: kalau request->warehouse_id gak ada, pakai main warehouse branch aktif
            $warehouseId = $request->warehouse_id
                ? (int)$request->warehouse_id
                : $this->resolveDefaultWarehouseId($branchId);

            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);
            $this->ensureCartItemsHaveWarehouse($warehouseId);

            $due_amount = ($request->total_amount * 1) - ($request->paid_amount * 1);
            $payment_status = $due_amount == ($request->total_amount * 1)
                ? 'Unpaid'
                : ($due_amount > 0 ? 'Partial' : 'Paid');

            // ✅ DETEKSI: invoice per delivery atau bukan
            $purchaseDeliveryId = $request->purchase_delivery_id ? (int)$request->purchase_delivery_id : null;
            $fromDelivery = !empty($purchaseDeliveryId) && $purchaseDeliveryId > 0;

            // PO optional (buat link saja)
            $purchase_order = null;
            if ($request->purchase_order_id) {
                $purchase_order = PurchaseOrder::findOrFail((int)$request->purchase_order_id);
            }

            // kalau fromDelivery, validasi delivery & belum ada purchase
            if ($fromDelivery) {
                $delivery = PurchaseDelivery::findOrFail($purchaseDeliveryId);
                if ($delivery->purchase) {
                    throw new \Exception("This Purchase Delivery already has an invoice.");
                }
            }

            $purchase = Purchase::create([
                'purchase_order_id'     => $request->purchase_order_id ?? null,
                'purchase_delivery_id'  => $purchaseDeliveryId,

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
                'status' => $fromDelivery ? 'Completed' : $request->status,
                'total_quantity' => $request->total_quantity,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase')->tax() * 1,
                'discount_amount' => Cart::instance('purchase')->discount() * 1,

                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
            ]);

            foreach (Cart::instance('purchase')->content() as $cart_item) {

                $itemWarehouseId = isset($cart_item->options->warehouse_id)
                    ? (int)$cart_item->options->warehouse_id
                    : $warehouseId;

                $this->assertWarehouseBelongsToBranch($itemWarehouseId, $branchId);

                // ✅ product_code jangan null
                $productCode = $cart_item->options->code ?? null;
                if (empty($productCode)) {
                    $p = Product::select('product_code')->find($cart_item->id);
                    $productCode = ($p && $p->product_code) ? $p->product_code : 'UNKNOWN';
                }

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $productCode,
                    'quantity' => $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'unit_price' => ($cart_item->options->unit_price ?? 0) * 1,
                    'sub_total' => ($cart_item->options->sub_total ?? 0) * 1,
                    'product_discount_amount' => ($cart_item->options->product_discount ?? 0) * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type ?? 'fixed',
                    'product_tax_amount' => ($cart_item->options->product_tax ?? 0) * 1,
                    'warehouse_id' => $itemWarehouseId,
                ]);

                /**
                 * ✅ FIX:
                 * - Invoice per Delivery => JANGAN update fulfilled PO & JANGAN stock mutation
                 * - Legacy/manual only => boleh update fulfilled + mutation saat Completed
                 */
                if (!$fromDelivery) {

                    // update fulfilled PO (legacy/manual)
                    if ($purchase_order) {
                        $purchase_order_detail = $purchase_order->purchaseOrderDetails()
                            ->where('product_id', $cart_item->id)
                            ->first();

                        if ($purchase_order_detail) {
                            $new_fulfilled_quantity = (int)$purchase_order_detail->fulfilled_quantity + (int)$cart_item->qty;

                            if ($new_fulfilled_quantity > (int)$purchase_order_detail->quantity) {
                                throw new \Exception("Cannot fulfill more than ordered quantity!");
                            }

                            $purchase_order_detail->update(['fulfilled_quantity' => $new_fulfilled_quantity]);
                        }
                    }

                    // stock mutation hanya legacy/manual
                    if ($request->status == 'Completed') {
                        $mutation = Mutation::with('product')
                            ->where('product_id', $cart_item->id)
                            ->where('warehouse_id', $itemWarehouseId)
                            ->latest()
                            ->first();

                        $stockEarly = $mutation ? (int)$mutation->stock_last : 0;

                        $mutationData = [
                            'reference' => $purchase->reference,
                            'date' => $request->date,
                            'mutation_type' => "In",
                            'note' => "Mutation for Purchase: " . $purchase->reference,
                            'warehouse_id' => $itemWarehouseId,
                            'product_id' => $cart_item->id,
                            'stock_early' => $stockEarly,
                            'stock_in' => (int)$cart_item->qty,
                            'stock_out' => 0,
                            'stock_last' => $stockEarly + (int)$cart_item->qty,
                        ];

                        if (Schema::hasColumn('mutations', 'branch_id')) {
                            $mutationData['branch_id'] = $branchId;
                        }

                        Mutation::create($mutationData);
                    }
                }
            }

            // status PO hanya legacy/manual
            if ($purchase_order && !$fromDelivery) {
                $total_remaining = $purchase_order->purchaseOrderDetails()->sum('quantity')
                    - $purchase_order->purchaseOrderDetails()->sum('fulfilled_quantity');

                $purchase_order->update([
                    'status' => $total_remaining > 0 ? 'Partially Sent' : 'Completed'
                ]);
            }

            Cart::instance('purchase')->destroy();

            /**
             * ✅ PAYMENT + JOURNAL
             * ERROR kamu kemarin: subaccount_number = "Cash"
             * Seharusnya subaccount_number = "1-xxxxx"
             */
            if ($purchase->paid_amount > 0) {
                $created_payment = PurchasePayment::create([
                    'date' => $request->date,
                    'reference' => 'INV/' . $purchase->reference,
                    'amount' => $purchase->paid_amount,
                    'purchase_id' => $purchase->id,
                    'payment_method' => $request->payment_method
                ]);

                // ✅ mapping payment_method => subaccount_number (ISI sesuai COA kamu)
                $paymentSubaccountNumber = $this->resolvePaymentSubaccountNumber($created_payment->payment_method);

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
                        // ⚠️ Pastikan ini memang akun debit yang kamu mau (contoh: Kas/Bank/Inventory/AP sesuai sistemmu)
                        'subaccount_number' => '1-10200',
                        'amount' => $created_payment->amount,
                        'type' => 'debit'
                    ],
                    [
                        // ✅ ini FIX utamanya (bukan "Cash" lagi)
                        'subaccount_number' => $paymentSubaccountNumber,
                        'amount' => $created_payment->amount,
                        'type' => 'credit'
                    ]
                ]);
            }
        });

        toast('Purchase Created!', 'success');
        return redirect()->route('purchases.index');
    }

    /**
     * ✅ helper mapping Payment Method => Subaccount Number
     * Isi nomor COA sesuai data kamu di table accounting_subaccounts.
     */
    private function resolvePaymentSubaccountNumber(string $paymentMethod): string
    {
        $pm = strtolower(trim($paymentMethod));

        // ✅ GANTI nomor ini sesuai COA kamu
        // Contoh umum:
        // - Cash / Kas => 1-10100
        // - Bank Transfer / Bank => 1-10200
        return match ($pm) {
            'cash' => '1-10100',
            'transfer', 'bank', 'bank transfer' => '1-10200',
            default => '1-10100',
        };
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
