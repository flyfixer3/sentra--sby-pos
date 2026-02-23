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
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;

class PurchaseController extends Controller
{

    public function index(PurchaseDataTable $dataTable) {
        abort_if(Gate::denies('access_purchases'), 403);

        return $dataTable->render('purchase::index');
    }

    public function createFromDelivery(PurchaseDelivery $purchaseDelivery)
    {
        abort_if(Gate::denies('create_purchases'), 403);

        $purchaseDelivery->loadMissing([
            'purchaseOrder',
            'purchaseOrder.supplier',
            'purchaseOrder.purchaseOrderDetails',
            'purchaseDeliveryDetails',
            'warehouse',
        ]);

        $purchaseOrder = $purchaseDelivery->purchaseOrder;

        // ✅ HARD GUARD: 1 PO = 1 INVOICE (boleh partial, tapi invoice cuma 1)
        if ($purchaseDelivery->purchase_order_id) {
            $existingPurchase = Purchase::where('purchase_order_id', (int) $purchaseDelivery->purchase_order_id)
                ->whereNull('deleted_at')
                ->first();

            if ($existingPurchase) {
                return redirect()
                    ->route('purchase-deliveries.show', $purchaseDelivery->id)
                    ->with('error', 'Invoice for this Purchase Order has already been created. Only one invoice is allowed per Purchase Order.');
            }
        }

        // guard lama: PD sudah punya invoice
        if ($purchaseDelivery->purchase) {
            return redirect()->back()
                ->with('error', 'This Purchase Delivery already has an invoice.');
        }

        // =========================
        // PREPARE CART (DEFAULT QTY)
        // ✅ SUMBER QTY: PurchaseDeliveryDetails.quantity (expected PD)
        // =========================
        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $branchId = $this->getActiveBranchId();

        // warehouse untuk kebutuhan stock display (kalau PD sudah pilih warehouse, pakai itu)
        $warehouseId = $this->resolveDefaultWarehouseId($branchId);
        if (!empty($purchaseDelivery->warehouse_id)) {
            $warehouseId = (int) $purchaseDelivery->warehouse_id;
        }

        // map PO detail by product_id (buat ambil price/diskon/tax)
        $poDetailMap = [];
        if ($purchaseOrder) {
            foreach ($purchaseOrder->purchaseOrderDetails as $d) {
                $poDetailMap[(int) $d->product_id] = $d;
            }
        }

        foreach ($purchaseDelivery->purchaseDeliveryDetails as $pdItem) {

            // ✅ qty default = yang diinput pada PD (expected)
            $qty = (int) ($pdItem->quantity ?? 0);
            if ($qty <= 0) continue;

            $product = Product::select('id', 'product_code', 'product_name', 'product_unit')
                ->find((int) $pdItem->product_id);

            $productCode = $pdItem->product_code ?: ($product?->product_code ?? 'UNKNOWN');
            $productName = $pdItem->product_name ?: ($product?->product_name ?? '-');

            // pricing dari PO kalau ada
            $poD = $poDetailMap[(int) $pdItem->product_id] ?? null;

            $price     = (int) ($poD->price ?? 0);
            $unitPrice = (int) ($poD->unit_price ?? 0);

            // stock display: per warehouse PD (kalau ada)
            $stockLast = 0;
            $mutation = Mutation::where('product_id', (int) $pdItem->product_id)
                ->where('warehouse_id', (int) $warehouseId)
                ->latest()
                ->first();

            if ($mutation) $stockLast = (int) $mutation->stock_last;

            $cart->add([
                'id'     => (int) $pdItem->product_id,
                'name'   => (string) $productName,
                'qty'    => $qty,
                'price'  => $price,
                'weight' => 1,
                'options' => [
                    'sub_total'   => $qty * $price,
                    'code'        => (string) $productCode,
                    'unit_price'  => $unitPrice,
                    'warehouse_id'=> (int) $warehouseId,
                    'branch_id'   => (int) $branchId,
                    'stock'       => $stockLast,
                    'unit'        => $product?->product_unit,
                    'product_discount'      => (float) ($poD->product_discount_amount ?? 0),
                    'product_discount_type' => (string) ($poD->product_discount_type ?? 'fixed'),
                    'product_tax'           => (float) ($poD->product_tax_amount ?? 0),

                    // ✅ fallback safety buat store() kalau hidden input gagal terkirim
                    'purchase_delivery_id'  => (int) $purchaseDelivery->id,
                    'purchase_order_id'     => $purchaseOrder ? (int) $purchaseOrder->id : null,
                ]
            ]);
        }

        // =========================
        // PREFILL HEADER FORM
        // =========================
        $prefillSupplierId = 0;
        if ($purchaseOrder && $purchaseOrder->supplier_id) {
            $prefillSupplierId = (int) $purchaseOrder->supplier_id;
        }

        $prefillDate = (string) ($purchaseDelivery->getRawOriginal('date') ?? now()->format('Y-m-d'));

        return view('purchase::create', [
            'activeBranchId'        => $branchId,
            'defaultWarehouseId'    => $warehouseId,

            'prefill' => [
                'purchase_order_id'    => $purchaseOrder ? (int) $purchaseOrder->id : null,
                'purchase_delivery_id' => (int) $purchaseDelivery->id,
                'supplier_id'          => $prefillSupplierId > 0 ? $prefillSupplierId : null,
                'date'                 => $prefillDate,
                'reference_supplier'   => (string) ($purchaseOrder->reference_supplier ?? ''),
            ],
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

            $warehouseId = $request->warehouse_id
                ? (int) $request->warehouse_id
                : $this->resolveDefaultWarehouseId($branchId);

            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);
            $this->ensureCartItemsHaveWarehouse($warehouseId);

            $due_amount = ($request->total_amount * 1) - ($request->paid_amount * 1);
            $payment_status = $due_amount == ($request->total_amount * 1)
                ? 'Unpaid'
                : ($due_amount > 0 ? 'Partial' : 'Paid');

            // ✅ fromDelivery detection (reliable)
            $purchaseDeliveryId = $request->purchase_delivery_id ? (int) $request->purchase_delivery_id : null;

            if (empty($purchaseDeliveryId)) {
                $firstRow = Cart::instance('purchase')->content()->first();
                if ($firstRow && isset($firstRow->options->purchase_delivery_id) && $firstRow->options->purchase_delivery_id) {
                    $purchaseDeliveryId = (int) $firstRow->options->purchase_delivery_id;
                }
            }

            $fromDelivery = !empty($purchaseDeliveryId) && $purchaseDeliveryId > 0;

            // PO optional (link saja)
            $purchase_order = null;
            if ($request->purchase_order_id) {
                $purchase_order = PurchaseOrder::findOrFail((int) $request->purchase_order_id);
            } elseif ($fromDelivery) {
                $firstRow = Cart::instance('purchase')->content()->first();
                $poIdFromCart = $firstRow && isset($firstRow->options->purchase_order_id) ? (int) $firstRow->options->purchase_order_id : 0;
                if ($poIdFromCart > 0) {
                    $purchase_order = PurchaseOrder::findOrFail($poIdFromCart);
                }
            }

            // kalau fromDelivery, validasi delivery & belum ada purchase
            $delivery = null;
            if ($fromDelivery) {
                $delivery = PurchaseDelivery::findOrFail($purchaseDeliveryId);

                if ((int) $delivery->branch_id !== (int) $branchId) {
                    throw new \RuntimeException("Active branch mismatch for this Purchase Delivery.");
                }

                if ($delivery->purchase) {
                    throw new \Exception("This Purchase Delivery already has an invoice.");
                }

                if ($purchase_order && (int) $delivery->purchase_order_id !== (int) $purchase_order->id) {
                    throw new \RuntimeException("Purchase Delivery does not belong to the selected Purchase Order.");
                }
            }

            $supplier = Supplier::findOrFail($request->supplier_id);

            // ✅ HARD GUARD: 1 PO = 1 INVOICE
            if ($purchase_order) {
                $exists = Purchase::where('purchase_order_id', (int) $purchase_order->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    throw new \Exception("Invoice for this Purchase Order has already been created. Only one invoice is allowed per Purchase Order.");
                }
            }

            // ✅ Invoice status tetap ikutin request (atau default Pending)
            $finalStatus = $request->status ?: 'Pending';

            $purchase = Purchase::create([
                'purchase_order_id'     => $purchase_order ? (int) $purchase_order->id : ($request->purchase_order_id ?? null),
                'purchase_delivery_id'  => $fromDelivery ? $purchaseDeliveryId : null,

                'date' => $request->date,
                'due_date' => $request->due_date,
                'reference_supplier' => $request->reference_supplier,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $supplier->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 1,
                'paid_amount' => $request->paid_amount * 1,
                'total_amount' => $request->total_amount * 1,
                'due_amount' => $due_amount * 1,

                'status' => $finalStatus,

                'total_quantity' => $request->total_quantity,
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('purchase')->tax() * 1,
                'discount_amount' => Cart::instance('purchase')->discount() * 1,

                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
            ]);

            // ✅ NEW: setelah invoice dibuat, status PO harus jadi Completed
            if ($purchase_order) {
                $purchase_order->refreshStatus();
            }

            // =========================
            // 1) CREATE PURCHASE DETAILS (NO MUTATION / NO FULFILLED)
            // =========================
            foreach (Cart::instance('purchase')->content() as $cart_item) {

                $itemWarehouseId = isset($cart_item->options->warehouse_id)
                    ? (int) $cart_item->options->warehouse_id
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
                    'quantity' => (int) $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'unit_price' => ($cart_item->options->unit_price ?? 0) * 1,
                    'sub_total' => ($cart_item->options->sub_total ?? 0) * 1,
                    'product_discount_amount' => ($cart_item->options->product_discount ?? 0) * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type ?? 'fixed',
                    'product_tax_amount' => ($cart_item->options->product_tax ?? 0) * 1,
                    'warehouse_id' => $itemWarehouseId,
                ]);
            }

            // =========================
            // 2) AUTO CREATE PURCHASE DELIVERY (WALK-IN)
            // =========================
            if (!$fromDelivery && empty($purchase->purchase_delivery_id)) {

                $autoPD = $this->createPendingPurchaseDeliveryForWalkIn($purchase);

                $purchase->purchase_delivery_id = (int) $autoPD->id;
                $purchase->save();

                DB::table('purchases')
                    ->where('id', (int) $purchase->id)
                    ->update(['purchase_delivery_id' => (int) $autoPD->id]);
            }

            Cart::instance('purchase')->destroy();

            // ✅ PAYMENT + JOURNAL (tetap)
            if ($purchase->paid_amount > 0) {
                $created_payment = PurchasePayment::create([
                    'date' => $request->date,
                    'reference' => 'INV/' . $purchase->reference,
                    'amount' => $purchase->paid_amount,
                    'purchase_id' => $purchase->id,
                    'payment_method' => $request->payment_method
                ]);

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
                        'subaccount_number' => '1-10200',
                        'amount' => $created_payment->amount,
                        'type' => 'debit'
                    ],
                    [
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

    public function show(Purchase $purchase)
    {
        abort_if(Gate::denies('show_purchases'), 403);

        $purchase->loadMissing([
            'purchaseDetails',
        ]);

        $supplier = Supplier::findOrFail($purchase->supplier_id);

        // ✅ ambil cabang dari purchase (paling aman, karena invoice itu milik cabang tsb)
        $branch = DB::table('branches')
            ->where('id', (int) $purchase->branch_id)
            ->first();

        // fallback kalau branch record gak ketemu
        $company = [
            'name'    => $branch->name    ?? settings()->company_name,
            'address' => $branch->address ?? settings()->company_address,
            'email'   => $branch->email   ?? settings()->company_email,
            'phone'   => $branch->phone   ?? settings()->company_phone,
        ];

        /**
         * ======================================================
         * ✅ NEW: related deliveries (multi PD)
         * ======================================================
         */
        $poId = (int) ($purchase->purchase_order_id ?? 0);

        // fallback: kalau invoice tidak nyimpen PO tapi nyimpen PD
        if ($poId <= 0 && !empty($purchase->purchase_delivery_id)) {
            $poId = (int) PurchaseDelivery::query()
                ->where('id', (int) $purchase->purchase_delivery_id)
                ->value('purchase_order_id');
        }

        $relatedDeliveries = collect();

        if ($poId > 0) {
            // ambil semua PD untuk PO tsb
            $relatedDeliveries = PurchaseDelivery::query()
                ->where('purchase_order_id', $poId)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();
        } elseif (!empty($purchase->purchase_delivery_id)) {
            // fallback terakhir: tampilkan PD tunggal
            $relatedDeliveries = PurchaseDelivery::query()
                ->where('id', (int) $purchase->purchase_delivery_id)
                ->get();
        }

        $relatedDeliveries = $relatedDeliveries->unique('id')->values();

        return view('purchase::show', compact(
            'purchase',
            'supplier',
            'company',
            'relatedDeliveries'
        ));
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

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        DB::transaction(function () use ($request, $purchase) {

            // =========================================================
            // ✅ HARD GUARD:
            // Kalau invoice sudah punya PD yang sudah di-confirm (partial/received),
            // invoice tidak boleh di-edit karena stok sudah berjalan dari PD confirm.
            // =========================================================
            if (!empty($purchase->purchase_delivery_id)) {
                $pd = PurchaseDelivery::find((int) $purchase->purchase_delivery_id);
                if ($pd) {
                    $st = strtolower(trim((string) $pd->status));
                    if (in_array($st, ['partial', 'received', 'completed'], true)) {
                        throw new \RuntimeException("This Purchase cannot be edited because related Purchase Delivery has been confirmed ({$pd->status}).");
                    }
                }
            }

            $branchId = $this->getActiveBranchId();

            $warehouseId = $request->warehouse_id
                ? (int) $request->warehouse_id
                : ($purchase->warehouse_id ? (int) $purchase->warehouse_id : $this->resolveDefaultWarehouseId($branchId));

            $this->assertWarehouseBelongsToBranch($warehouseId, $branchId);
            $this->ensureCartItemsHaveWarehouse($warehouseId);

            $due_amount = ($request->total_amount * 1) - ($request->paid_amount * 1);
            $payment_status = $due_amount == ($request->total_amount * 1)
                ? 'Unpaid'
                : ($due_amount > 0 ? 'Partial' : 'Paid');

            // ✅ update header dulu
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

            // =========================================================
            // ✅ replace details: delete lalu insert ulang
            // (NO MUTATION / NO PRODUCT COST UPDATE)
            // =========================================================
            foreach ($purchase->purchaseDetails as $purchase_detail) {
                $purchase_detail->delete();
            }

            foreach (Cart::instance('purchase')->content() as $cart_item) {

                $itemWarehouseId = isset($cart_item->options->warehouse_id)
                    ? (int) $cart_item->options->warehouse_id
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
                    'quantity' => (int) $cart_item->qty,
                    'price' => $cart_item->price * 1,
                    'warehouse_id' => $itemWarehouseId,
                    'unit_price' => ($cart_item->options->unit_price ?? 0) * 1,
                    'sub_total' => ($cart_item->options->sub_total ?? 0) * 1,
                    'product_discount_amount' => ($cart_item->options->product_discount ?? 0) * 1,
                    'product_discount_type' => $cart_item->options->product_discount_type ?? 'fixed',
                    'product_tax_amount' => ($cart_item->options->product_tax ?? 0) * 1,
                ]);

                // ❌ DIHAPUS TOTAL:
                // - Mutation In/Out saat edit
                // - update product_cost
                // - update product_quantity
                // Karena stok + biaya barang harus mengikuti confirm PD, bukan invoice edit.
            }

            Cart::instance('purchase')->destroy();
        });

        toast('Purchase Updated!', 'info');
        return redirect()->route('purchases.index');
    }

    public function destroy(Purchase $purchase)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        // ✅ soft delete
        $purchase->delete();

        toast('Purchase Deleted (soft)!', 'warning');
        return redirect()->route('purchases.index');
    }

    public function restore(int $id)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        $purchase = Purchase::withTrashed()->findOrFail($id);

        if ($purchase->trashed()) {
            $purchase->restore();
        }

        toast('Purchase Restored!', 'success');
        return redirect()->route('purchases.index');
    }

    public function forceDestroy(int $id)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        $purchase = Purchase::withTrashed()->findOrFail($id);

        // ✅ beneran hapus permanen
        $purchase->forceDelete();

        toast('Purchase Deleted Permanently!', 'warning');
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

    private function createPendingPurchaseDeliveryForWalkIn(Purchase $purchase): PurchaseDelivery
    {
        $roleString = '-';
        if (auth()->user() && method_exists(auth()->user(), 'getRoleNames')) {
            $roles = auth()->user()->getRoleNames()->toArray();
            $roles = array_values(array_filter(array_map(fn ($r) => trim((string) $r), $roles)));
            $roleString = count($roles) ? implode(', ', $roles) : '-';
        }

        $autoNote = 'Auto-created from Purchase (invoice). Please confirm receipt manually.';

        $autoPD = PurchaseDelivery::create([
            'purchase_order_id' => $purchase->purchase_order_id ?? null,
            'branch_id'         => (int) $purchase->branch_id,
            'warehouse_id'      => (int) $purchase->warehouse_id,
            'date'              => (string) $purchase->date,
            'note'              => $autoNote,
            'ship_via'          => null,
            'tracking_number'   => null,
            'status'            => 'Pending',
            'created_by'        => auth()->id(),

            'note_updated_by'   => auth()->id(),
            'note_updated_role' => $roleString,
            'note_updated_at'   => now(),
        ]);

        // ✅ SUMBER PALING AMAN: purchase_details (SUDAH DIPAKSA product_code TIDAK NULL)
        $purchase->loadMissing(['purchaseDetails']);

        foreach ($purchase->purchaseDetails as $pd) {
            $finalCode = trim((string) ($pd->product_code ?? ''));
            if ($finalCode === '') $finalCode = 'UNKNOWN';

            PurchaseDeliveryDetails::create([
                'purchase_delivery_id' => (int) $autoPD->id,
                'product_id'           => (int) $pd->product_id,
                'product_name'         => (string) ($pd->product_name ?? '-'),
                'product_code'         => (string) $finalCode,
                'quantity'             => (int) ($pd->quantity ?? 0),
                'unit_price'           => (float) ($pd->unit_price ?? 0),
                'sub_total'            => (float) ($pd->sub_total ?? 0),

                'qty_received'         => 0,
                'qty_defect'           => 0,
                'qty_damaged'          => 0,
            ]);
        }

        return $autoPD;
    }

}
