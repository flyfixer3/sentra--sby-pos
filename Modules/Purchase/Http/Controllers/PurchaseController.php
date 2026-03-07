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
use Spatie\Activitylog\Models\Activity;

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

        // ✅ HARD GUARD: 1 PO = 1 INVOICE
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

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $branchId = $this->getActiveBranchId();

        // ✅ Status-based warehouse resolve:
        // - PD Pending => warehouse_id boleh null => stock harus ALL warehouses (branch)
        // - PD Partial/Received/Completed => warehouse_id wajibnya sudah ada => stock per warehouse
        $pdStatus = strtolower(trim((string) ($purchaseDelivery->status ?? 'pending')));
        $isConfirmed = in_array($pdStatus, ['partial', 'received', 'completed'], true);

        $warehouseId = null;
        if ($isConfirmed && !empty($purchaseDelivery->warehouse_id)) {
            $warehouseId = (int) $purchaseDelivery->warehouse_id;
        }

        // ✅ stock mode untuk Livewire
        $stock_mode = $warehouseId ? 'warehouse' : 'branch_all';

        // defaultWarehouseId hanya untuk “loading warehouse” UI / fallback display,
        // tapi TIDAK dipakai untuk paksa scope stock kalau PD belum confirmed.
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($branchId);

        // map PO detail by product_id (ambil price/diskon/tax)
        $poDetailMap = [];
        if ($purchaseOrder) {
            foreach ($purchaseOrder->purchaseOrderDetails as $d) {
                $poDetailMap[(int) $d->product_id] = $d;
            }
        }

        // helper local: sum stock_last last-per-warehouse (active branch)
        $getStockAllWarehousesInBranch = function (int $productId) use ($branchId): int {
            $warehouseIds = DB::table('warehouses')
                ->where('branch_id', (int) $branchId)
                ->pluck('id')
                ->toArray();

            if (empty($warehouseIds)) return 0;

            $sum = 0;
            foreach ($warehouseIds as $wid) {
                $last = Mutation::where('product_id', (int) $productId)
                    ->where('warehouse_id', (int) $wid)
                    ->latest()
                    ->value('stock_last');

                $sum += (int) ($last ?? 0);
            }

            return (int) $sum;
        };

        foreach ($purchaseDelivery->purchaseDeliveryDetails as $pdItem) {

            $qty = (int) ($pdItem->quantity ?? 0);
            if ($qty <= 0) continue;

            $product = Product::select('id', 'product_code', 'product_name', 'product_unit')
                ->find((int) $pdItem->product_id);

            $productCode = $pdItem->product_code ?: ($product?->product_code ?? 'UNKNOWN');
            $productName = $pdItem->product_name ?: ($product?->product_name ?? '-');

            $poD = $poDetailMap[(int) $pdItem->product_id] ?? null;

            $price     = (int) ($poD->price ?? 0);
            $unitPrice = (int) ($poD->unit_price ?? 0);

            // ✅ stock display:
            // - confirmed => per warehouse PD
            // - pending   => ALL warehouses (branch)
            if ($warehouseId) {
                $last = Mutation::where('product_id', (int) $pdItem->product_id)
                    ->where('warehouse_id', (int) $warehouseId)
                    ->latest()
                    ->value('stock_last');
                $stockLast = (int) ($last ?? 0);
                $stockScope = 'warehouse';
            } else {
                $stockLast = (int) $getStockAllWarehousesInBranch((int) $pdItem->product_id);
                $stockScope = 'branch';
            }

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

                    // ✅ warehouse_id hanya ada kalau PD sudah confirmed
                    'warehouse_id'=> $warehouseId ? (int) $warehouseId : null,

                    'branch_id'   => (int) $branchId,
                    'stock'       => $stockLast,
                    'stock_scope' => $stockScope, // branch|warehouse
                    'unit'        => $product?->product_unit,

                    'product_discount'      => (float) ($poD->product_discount_amount ?? 0),
                    'product_discount_type' => (string) ($poD->product_discount_type ?? 'fixed'),
                    'product_tax'           => (float) ($poD->product_tax_amount ?? 0),

                    'purchase_delivery_id'  => (int) $purchaseDelivery->id,
                    'purchase_order_id'     => $purchaseOrder ? (int) $purchaseOrder->id : null,
                ]
            ]);
        }

        $prefillSupplierId = 0;
        if ($purchaseOrder && $purchaseOrder->supplier_id) {
            $prefillSupplierId = (int) $purchaseOrder->supplier_id;
        }

        $prefillDate = (string) ($purchaseDelivery->getRawOriginal('date') ?? now()->format('Y-m-d'));

        return view('purchase::create', [
            'activeBranchId'     => $branchId,
            'defaultWarehouseId' => $defaultWarehouseId,
            'stock_mode'         => $stock_mode,

            'prefill' => [
                'purchase_order_id'    => $purchaseOrder ? (int) $purchaseOrder->id : null,
                'purchase_delivery_id' => (int) $purchaseDelivery->id,
                'supplier_id'          => $prefillSupplierId > 0 ? $prefillSupplierId : null,
                'date'                 => $prefillDate,
                'reference_supplier'   => (string) ($purchaseOrder->reference_supplier ?? ''),
            ],
        ]);
    }

    public function create()
    {
        abort_if(Gate::denies('create_purchases'), 403);

        Cart::instance('purchase')->destroy();

        $activeBranchId = $this->getActiveBranchId();
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($activeBranchId);

        // ✅ Purchase dibuat duluan (belum ada PD) => stock ALL warehouses
        $stock_mode = 'branch_all';

        return view('purchase::create', [
            'activeBranchId'     => $activeBranchId,
            'defaultWarehouseId' => $defaultWarehouseId,
            'stock_mode'         => $stock_mode,
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        DB::transaction(function () use ($request) {

            $branchId = $this->getActiveBranchId();

            // ✅ fromDelivery detection
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

            // ✅ resolve warehouse from PD ONLY if PD already confirmed
            $warehouseId = null;
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

                $pdStatus = strtolower(trim((string) ($delivery->status ?? 'pending')));
                $isConfirmed = in_array($pdStatus, ['partial', 'received', 'completed'], true);

                if ($isConfirmed && !empty($delivery->warehouse_id)) {
                    $warehouseId = (int) $delivery->warehouse_id;
                } else {
                    // PD pending => warehouse tetap null (sesuai requirement kamu)
                    $warehouseId = null;
                }
            } else {
                // ✅ Walk-in purchase (invoice dibuat duluan) => warehouse null
                $warehouseId = null;
            }

            // ✅ kalau warehouseId ada, validasi belong & pastikan cart items punya warehouse_id
            if (!empty($warehouseId)) {
                $this->assertWarehouseBelongsToBranch((int) $warehouseId, (int) $branchId);
                $this->ensureCartItemsHaveWarehouse((int) $warehouseId);
            }
            // kalau warehouseId null => jangan paksa ensureCartItemsHaveWarehouse()

            $due_amount = ($request->total_amount * 1) - ($request->paid_amount * 1);
            $payment_status = $due_amount == ($request->total_amount * 1)
                ? 'Unpaid'
                : ($due_amount > 0 ? 'Partial' : 'Paid');

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

            $finalStatus = $request->status ?: 'Pending';

            $purchase = Purchase::create([
                'purchase_order_id'    => $purchase_order ? (int) $purchase_order->id : ($request->purchase_order_id ?? null),
                'purchase_delivery_id' => $fromDelivery ? $purchaseDeliveryId : null,

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

                // ✅ warehouse boleh null saat invoice dibuat duluan
                'warehouse_id' => $warehouseId ? (int) $warehouseId : null,
            ]);

            if ($purchase_order) {
                $purchase_order->refreshStatus();
            }

            // =========================
            // 1) CREATE PURCHASE DETAILS
            // =========================
            foreach (Cart::instance('purchase')->content() as $cart_item) {

                // ✅ itemWarehouseId ikut warehouseId (bisa null)
                $itemWarehouseId = isset($cart_item->options->warehouse_id)
                    ? (int) ($cart_item->options->warehouse_id ?: 0)
                    : 0;

                // kalau header warehouse null => jangan maksa detail punya warehouse
                if (!empty($warehouseId)) {
                    $itemWarehouseId = (int) $warehouseId;
                } else {
                    $itemWarehouseId = $itemWarehouseId > 0 ? $itemWarehouseId : 0;
                }

                if ($itemWarehouseId > 0) {
                    $this->assertWarehouseBelongsToBranch($itemWarehouseId, $branchId);
                }

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

                    // ✅ warehouse_id boleh null
                    'warehouse_id' => $itemWarehouseId > 0 ? $itemWarehouseId : null,
                ]);
            }

            // =========================================================
            // WALK-IN: INCREASE qty_incoming (stocks pool) (tetap sama)
            // =========================================================
            if (!$purchase_order && !$fromDelivery) {

                $qtyByProduct = [];
                foreach (Cart::instance('purchase')->content() as $it) {
                    $pid = (int) ($it->id ?? 0);
                    $qty = (int) ($it->qty ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                    $qtyByProduct[$pid] += $qty;
                }

                foreach ($qtyByProduct as $pid => $qty) {

                    $poolRow = DB::table('stocks')
                        ->where('branch_id', (int) $branchId)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $pid)
                        ->lockForUpdate()
                        ->first();

                    if ($poolRow) {
                        DB::table('stocks')
                            ->where('id', (int) $poolRow->id)
                            ->update([
                                'qty_incoming' => (int) ($poolRow->qty_incoming ?? 0) + (int) $qty,
                                'updated_at'   => now(),
                            ]);
                    } else {
                        DB::table('stocks')->insert([
                            'branch_id'     => (int) $branchId,
                            'warehouse_id'  => null,
                            'product_id'    => (int) $pid,
                            'qty_available' => 0,
                            'qty_reserved'  => 0,
                            'qty_incoming'  => (int) $qty,
                            'min_stock'     => 0,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }

            // =========================
            // 2) AUTO CREATE PURCHASE DELIVERY (WALK-IN)
            // =========================
            if (!$fromDelivery && empty($purchase->purchase_delivery_id)) {

                // ✅ PD auto akan ikut warehouse_id purchase (null) => sesuai requirement kamu
                $autoPD = $this->createPendingPurchaseDeliveryForWalkIn($purchase);

                $purchase->purchase_delivery_id = (int) $autoPD->id;
                $purchase->save();

                DB::table('purchases')
                    ->where('id', (int) $purchase->id)
                    ->update(['purchase_delivery_id' => (int) $autoPD->id]);
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

    private function canManageHppSensitiveEdit(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->can('view_sale_hpp')) {
            return true;
        }

        return $user->hasAnyRole(['Administrator', 'Super Admin']);
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

        // =========================================================
        // ✅ Activity Logs (Spatie) untuk penanda invoice pernah diedit
        // BaseModel kamu sudah LogsActivity, jadi update log otomatis ada.
        // Plus nanti update() akan log event koreksi harga (manual).
        // =========================================================
        $activities = Activity::query()
            ->where('subject_type', Purchase::class)
            ->where('subject_id', (int) $purchase->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('purchase::show', compact(
            'purchase',
            'supplier',
            'company',
            'relatedDeliveries',
            'activities'
        ));
    }

    public function edit(Purchase $purchase)
    {
        abort_if(Gate::denies('edit_purchases'), 403);

        $purchase->loadMissing([
            'purchaseDetails',
            'purchaseDelivery',
        ]);

        $purchase_details = $purchase->purchaseDetails;

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $branchId = $this->getActiveBranchId();

        // =========================================================
        // Samakan logic dengan create/createFromDelivery:
        // - PD confirmed + warehouse final ada => stock per warehouse
        // - selain itu => stock all warehouses dalam active branch
        // =========================================================
        $purchaseDelivery = $purchase->purchaseDelivery;

        $pdStatus = strtolower(trim((string) ($purchaseDelivery->status ?? 'pending')));
        $isConfirmed = in_array($pdStatus, ['partial', 'received', 'completed'], true);

        $resolvedWarehouseId = null;
        if ($isConfirmed && !empty($purchaseDelivery?->warehouse_id)) {
            $resolvedWarehouseId = (int) $purchaseDelivery->warehouse_id;
        }

        $stock_mode = $resolvedWarehouseId ? 'warehouse' : 'branch_all';

        // loading warehouse hanya untuk fallback UI
        $defaultWarehouseId = $this->resolveDefaultWarehouseId($branchId);
        $loadingWarehouseId = $resolvedWarehouseId ?: $defaultWarehouseId;

        // helper local: total stock seluruh warehouse branch aktif
        $getStockAllWarehousesInBranch = function (int $productId) use ($branchId): int {
            $warehouseIds = DB::table('warehouses')
                ->where('branch_id', (int) $branchId)
                ->pluck('id')
                ->toArray();

            if (empty($warehouseIds)) {
                return 0;
            }

            $sum = 0;
            foreach ($warehouseIds as $wid) {
                $last = Mutation::where('product_id', (int) $productId)
                    ->where('warehouse_id', (int) $wid)
                    ->latest()
                    ->value('stock_last');

                $sum += (int) ($last ?? 0);
            }

            return (int) $sum;
        };

        foreach ($purchase_details as $purchase_detail) {
            $product = Product::select('id', 'product_unit', 'product_cost')
                ->find((int) $purchase_detail->product_id);

            if ($resolvedWarehouseId) {
                $last = Mutation::where('product_id', (int) $purchase_detail->product_id)
                    ->where('warehouse_id', (int) $resolvedWarehouseId)
                    ->latest()
                    ->value('stock_last');

                $stockLast = (int) ($last ?? 0);
                $stockScope = 'warehouse';
                $itemWarehouseId = (int) $resolvedWarehouseId;
            } else {
                $stockLast = (int) $getStockAllWarehousesInBranch((int) $purchase_detail->product_id);
                $stockScope = 'branch';
                $itemWarehouseId = null;
            }

            $unitPrice = (float) ($purchase_detail->unit_price ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) ($purchase_detail->price ?? 0);
            }

            $subTotal = (float) ($purchase_detail->sub_total ?? 0);
            if ($subTotal <= 0) {
                $subTotal = (float) $unitPrice * (int) ($purchase_detail->quantity ?? 0);
            }

            $cart->add([
                'id'      => (int) $purchase_detail->product_id,
                'name'    => (string) $purchase_detail->product_name,
                'qty'     => (int) $purchase_detail->quantity,
                'price'   => (float) $unitPrice,
                'weight'  => 1,
                'options' => [
                    'purchase_detail_id'    => (int) $purchase_detail->id,
                    'product_discount'      => (float) $purchase_detail->product_discount_amount,
                    'product_discount_type' => (string) $purchase_detail->product_discount_type,
                    'sub_total'             => (float) $subTotal,
                    'code'                  => (string) $purchase_detail->product_code,
                    'warehouse_id'          => $itemWarehouseId,
                    'stock'                 => (int) $stockLast,
                    'stock_scope'           => $stockScope,
                    'unit'                  => $product?->product_unit,
                    'product_tax'           => (float) $purchase_detail->product_tax_amount,
                    'product_cost'          => (float) ($product?->product_cost ?? 0),
                    'unit_price'            => (float) $unitPrice,
                    'branch_id'             => (int) $branchId,
                ],
            ]);
        }

        return view('purchase::edit', [
            'purchase' => $purchase,
            'loadingWarehouseId' => $loadingWarehouseId,
            'stock_mode' => $stock_mode,
            'canManageHppSensitiveEdit' => $this->canManageHppSensitiveEdit(),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        abort_if(Gate::denies('edit_purchases'), 403);

        try {
            DB::transaction(function () use ($request, $purchase) {

                $branchId = $this->getActiveBranchId();

                // =========================================================
                // Permission rules
                // =========================================================
                $isAdmin = $this->canManageHppSensitiveEdit();

                $purchase->loadMissing(['purchaseDetails']);

                // =========================================================
                // Map detail lama (DB) untuk detect perubahan HPP-sensitive
                // =========================================================
                $oldDetails = $purchase->purchaseDetails()->get();
                $oldMap = [];
                foreach ($oldDetails as $d) {
                    $pid = (int) $d->product_id;

                    $oldUnit = (float) ($d->unit_price ?? 0);
                    if ($oldUnit <= 0) {
                        $oldUnit = (float) ($d->price ?? 0);
                    }

                    $oldMap[$pid] = [
                        'qty'       => (int) ($d->quantity ?? 0),
                        'unit_cost' => (float) $oldUnit,
                    ];
                }

                // =========================================================
                // Map detail baru (Cart)
                // =========================================================
                $newMap = [];
                foreach (Cart::instance('purchase')->content() as $cart_item) {
                    $pid = (int) $cart_item->id;

                    $newUnit = (float) ($cart_item->options->unit_price ?? 0);
                    if ($newUnit <= 0) {
                        $newUnit = (float) ($cart_item->price ?? 0);
                    }

                    $newMap[$pid] = [
                        'qty'       => (int) ($cart_item->qty ?? 0),
                        'unit_cost' => (float) $newUnit,
                    ];
                }

                // =========================================================
                // Detect HPP-sensitive change
                // =========================================================
                $hppSensitiveChanged = false;
                $changedProducts = [];

                $allProductIds = array_values(array_unique(array_merge(array_keys($oldMap), array_keys($newMap))));
                foreach ($allProductIds as $pid) {
                    $pid = (int) $pid;

                    $old = $oldMap[$pid] ?? null;
                    $new = $newMap[$pid] ?? null;

                    if (!$old || !$new) {
                        $hppSensitiveChanged = true;
                        $changedProducts[$pid] = [
                            'old_unit_cost' => (float) ($old['unit_cost'] ?? 0),
                            'new_unit_cost' => (float) ($new['unit_cost'] ?? 0),
                            'old_qty'       => (int) ($old['qty'] ?? 0),
                            'new_qty'       => (int) ($new['qty'] ?? 0),
                        ];
                        continue;
                    }

                    if (
                        (int) $old['qty'] !== (int) $new['qty'] ||
                        round((float) $old['unit_cost'], 2) !== round((float) $new['unit_cost'], 2)
                    ) {
                        $hppSensitiveChanged = true;
                        $changedProducts[$pid] = [
                            'old_unit_cost' => round((float) $old['unit_cost'], 2),
                            'new_unit_cost' => round((float) $new['unit_cost'], 2),
                            'old_qty'       => (int) $old['qty'],
                            'new_qty'       => (int) $new['qty'],
                        ];
                    }
                }

                // =========================================================
                // Block non-admin jika ada perubahan HPP-sensitive
                // =========================================================
                if ($hppSensitiveChanged && !$isAdmin) {
                    throw new \RuntimeException('You are not allowed to edit item price/qty because it affects HPP. Please contact Administrator.');
                }

                // =========================================================
                // Admin wajib isi reason + disclaimer
                // =========================================================
                if ($hppSensitiveChanged && $isAdmin) {
                    $reason = trim((string) $request->edit_reason);
                    if ($reason === '') {
                        throw new \RuntimeException('Edit reason is required for HPP-sensitive changes.');
                    }

                    $confirmed = (int) ($request->confirm_recalculate_hpp ?? 0);
                    if ($confirmed !== 1) {
                        throw new \RuntimeException('Please confirm the disclaimer checkbox to proceed with HPP correction.');
                    }

                    // TODO:
                    // nanti kalau konsep shift closing sudah ada,
                    // validasi HPP-sensitive edit hanya boleh kalau shift belum closed
                    // atau harus lewat mekanisme reopen day / supervisor approval
                }

                // =========================================================
                // Resolve warehouse FINAL mengikuti PD confirmed saja
                // =========================================================
                $linkedPd = null;
                if (!empty($purchase->purchase_delivery_id)) {
                    $linkedPd = PurchaseDelivery::find((int) $purchase->purchase_delivery_id);
                }

                $warehouseId = null;
                if ($linkedPd) {
                    $pdStatus = strtolower(trim((string) ($linkedPd->status ?? 'pending')));
                    $isConfirmed = in_array($pdStatus, ['partial', 'received', 'completed'], true);

                    if ($isConfirmed && !empty($linkedPd->warehouse_id)) {
                        $warehouseId = (int) $linkedPd->warehouse_id;
                    }
                }

                if (!empty($warehouseId)) {
                    $this->assertWarehouseBelongsToBranch((int) $warehouseId, (int) $branchId);
                    $this->ensureCartItemsHaveWarehouse((int) $warehouseId);
                }

                $due_amount = ($request->total_amount * 1) - ($request->paid_amount * 1);
                $payment_status = $due_amount == ($request->total_amount * 1)
                    ? 'Unpaid'
                    : ($due_amount > 0 ? 'Partial' : 'Paid');

                // =========================================================
                // update header
                // =========================================================
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
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId ? (int) $warehouseId : null,
                ]);

                // =========================================================
                // Update exact purchase detail row, jangan delete-all + recreate
                // =========================================================
                $activeDetailIds = $purchase->purchaseDetails()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $existingDetailsByProduct = [];
                foreach ($purchase->purchaseDetails as $existingDetail) {
                    $existingDetailsByProduct[(int) $existingDetail->product_id][] = $existingDetail;
                }

                $usedDetailIds = [];

                foreach (Cart::instance('purchase')->content() as $cart_item) {
                    $itemWarehouseId = null;

                    if (!empty($warehouseId)) {
                        $itemWarehouseId = (int) $warehouseId;
                        $this->assertWarehouseBelongsToBranch((int) $itemWarehouseId, (int) $branchId);
                    }

                    $productCode = $cart_item->options->code ?? null;
                    if (empty($productCode)) {
                        $p = Product::select('product_code')->find($cart_item->id);
                        $productCode = ($p && $p->product_code) ? $p->product_code : 'UNKNOWN';
                    }

                    $detailUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
                    if ($detailUnitPrice <= 0) {
                        $detailUnitPrice = (float) ($cart_item->price ?? 0);
                    }

                    $detailPrice = (float) ($cart_item->price ?? 0);
                    if ($detailPrice <= 0) {
                        $detailPrice = (float) $detailUnitPrice;
                    }

                    $detailQty = (int) ($cart_item->qty ?? 0);

                    $detailSubTotal = (float) ($cart_item->options->sub_total ?? 0);
                    if ($detailSubTotal <= 0) {
                        $detailSubTotal = round($detailUnitPrice * $detailQty, 2);
                    }

                    $payload = [
                        'purchase_id' => (int) $purchase->id,
                        'product_id' => (int) $cart_item->id,
                        'product_name' => (string) $cart_item->name,
                        'product_code' => (string) $productCode,
                        'quantity' => $detailQty,
                        'price' => $detailPrice * 1,
                        'warehouse_id' => $itemWarehouseId ? (int) $itemWarehouseId : null,
                        'unit_price' => $detailUnitPrice * 1,
                        'sub_total' => $detailSubTotal * 1,
                        'product_discount_amount' => ((float) ($cart_item->options->product_discount ?? 0)) * 1,
                        'product_discount_type' => $cart_item->options->product_discount_type ?? 'fixed',
                        'product_tax_amount' => ((float) ($cart_item->options->product_tax ?? 0)) * 1,
                    ];

                    $purchaseDetailId = (int) ($cart_item->options->purchase_detail_id ?? 0);

                    if ($purchaseDetailId > 0) {
                        $detailRow = PurchaseDetail::where('purchase_id', (int) $purchase->id)
                            ->where('id', $purchaseDetailId)
                            ->first();

                        if ($detailRow) {
                            $detailRow->update($payload);
                            $usedDetailIds[] = (int) $detailRow->id;
                            continue;
                        }
                    }

                    $fallbackDetail = collect($existingDetailsByProduct[(int) $cart_item->id] ?? [])
                        ->first(function ($detail) use ($usedDetailIds) {
                            return !in_array((int) $detail->id, $usedDetailIds, true);
                        });

                    if ($fallbackDetail) {
                        $fallbackDetail->update($payload);
                        $usedDetailIds[] = (int) $fallbackDetail->id;
                        continue;
                    }

                    $newDetail = PurchaseDetail::create($payload);
                    $usedDetailIds[] = (int) $newDetail->id;
                }

                // Soft delete detail yang tidak lagi ada di cart invoice ini
                $detailIdsToDelete = array_values(array_diff($activeDetailIds, $usedDetailIds));
                if (!empty($detailIdsToDelete)) {
                    PurchaseDetail::where('purchase_id', (int) $purchase->id)
                        ->whereIn('id', $detailIdsToDelete)
                        ->delete();
                }

                // =========================================================
                // Jika admin melakukan HPP-sensitive edit
                // =========================================================
                if ($hppSensitiveChanged && $isAdmin) {
                    $reason = trim((string) $request->edit_reason);

                    activity()
                        ->performedOn($purchase)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'type' => 'purchase_hpp_sensitive_edit',
                            'reason' => $reason,
                            'changed_products' => $changedProducts,
                            'purchase_id' => (int) $purchase->id,
                            'purchase_delivery_id' => (int) ($purchase->purchase_delivery_id ?? 0),
                            'purchase_date' => (string) $purchase->date,
                        ])
                        ->log('Purchase price corrected (HPP sensitive edit)');

                    $service = new \Modules\Product\Services\HppCorrectionService();

                    $productIds = array_keys($changedProducts);

                    $summary = $service->applyPurchasePriceCorrection(
                        (int) $branchId,
                        (int) $purchase->id,
                        (string) $purchase->date,
                        $purchase->purchase_delivery_id ? (int) $purchase->purchase_delivery_id : null,
                        array_map(function ($v) {
                            return [
                                'old_unit_cost' => (float) ($v['old_unit_cost'] ?? 0),
                                'new_unit_cost' => (float) ($v['new_unit_cost'] ?? 0),
                            ];
                        }, $changedProducts)
                    );

                    $updatedSaleRows = $service->refreshSaleCostSnapshotSameDay(
                        (int) $branchId,
                        (string) $purchase->date,
                        $productIds
                    );

                    activity()
                        ->performedOn($purchase)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'type' => 'hpp_correction_summary',
                            'summary' => $summary,
                            'updated_sale_detail_rows' => (int) $updatedSaleRows,
                        ])
                        ->log('HPP correction applied & sale cost snapshot refreshed (same day)');
                }

                Cart::instance('purchase')->destroy();
            });

            toast('Purchase Updated!', 'success');
            return redirect()->route('purchases.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(Purchase $purchase)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        $activeBranchId = $this->getActiveBranchId();

        try {
            DB::transaction(function () use ($purchase, $activeBranchId) {

                // =========================
                // ✅ Lock invoice header
                // =========================
                $p = Purchase::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail((int) $purchase->id);

                // branch guard
                if ((int) $p->branch_id !== (int) $activeBranchId) {
                    abort(403, 'Active branch mismatch for this Purchase.');
                }

                // ✅ status rule: pending only
                $st = strtolower(trim((string) ($p->status ?? 'pending')));
                if ($st !== 'pending') {
                    throw new \RuntimeException('Only Pending Purchase (Invoice) can be deleted.');
                }

                // ✅ payment rule: harus unpaid
                $paymentStatus = strtolower(trim((string) ($p->payment_status ?? 'unpaid')));
                if ($paymentStatus !== 'unpaid') {
                    throw new \RuntimeException('Cannot delete: Payment status must be Unpaid.');
                }

                $paidAmount = (float) ($p->paid_amount ?? 0);
                if ($paidAmount > 0) {
                    throw new \RuntimeException('Cannot delete: this Invoice already has payment amount (paid_amount > 0).');
                }

                $hasPayments = PurchasePayment::withoutGlobalScopes()
                    ->where('purchase_id', (int) $p->id)
                    ->exists();

                if ($hasPayments) {
                    throw new \RuntimeException('Cannot delete: this Invoice already has payment records.');
                }

                // =========================
                // ✅ LOCK purchase details (buat rollback incoming WALK-IN)
                // =========================
                $purchaseDetails = PurchaseDetail::withoutGlobalScopes()
                    ->where('purchase_id', (int) $p->id)
                    ->lockForUpdate()
                    ->get(['product_id', 'quantity']);

                // =========================
                // ✅ Identify WALK-IN vs PO-based
                // =========================
                $poId = (int) ($p->purchase_order_id ?? 0);
                $pdId = (int) ($p->purchase_delivery_id ?? 0);

                // WALK-IN = invoice tidak terkait PO
                $isWalkIn = ($poId <= 0);

                // =========================
                // ✅ If linked PD exists: guard PD (race safe)
                // - WALK-IN: nanti PD di-delete bareng
                // - NON WALK-IN: PD tidak dihapus, tapi tetap block delete invoice kalau PD sudah diproses
                // =========================
                $linkedPd = null;

                if ($pdId > 0) {
                    $linkedPd = PurchaseDelivery::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->find((int) $pdId);

                    if ($linkedPd) {

                        if ((int) $linkedPd->branch_id !== (int) $p->branch_id) {
                            throw new \RuntimeException('Cannot delete: related Purchase Delivery belongs to different branch.');
                        }

                        // Kalau PD sudah confirmed/processed, invoice jangan boleh dihapus
                        $pdStatus = strtolower(trim((string) ($linkedPd->status ?? 'pending')));
                        if ($pdStatus !== 'pending') {
                            throw new \RuntimeException(
                                "Cannot delete: related Purchase Delivery already processed (status: {$linkedPd->status})."
                            );
                        }

                        // pastikan belum ada confirmed qty di detail PD
                        $details = PurchaseDeliveryDetails::withoutGlobalScopes()
                            ->where('purchase_delivery_id', (int) $linkedPd->id)
                            ->lockForUpdate()
                            ->get(['qty_received', 'qty_defect', 'qty_damaged']);

                        foreach ($details as $d) {
                            $confirmed = (int) ($d->qty_received ?? 0)
                                + (int) ($d->qty_defect ?? 0)
                                + (int) ($d->qty_damaged ?? 0);

                            if ($confirmed > 0) {
                                throw new \RuntimeException(
                                    'Cannot delete: related Purchase Delivery already has confirmed quantities.'
                                );
                            }
                        }

                        // safety: jangan sampai sudah ada mutation PD
                        $baseRef = 'PD-' . (int) $linkedPd->id;
                        $hasMutation = Mutation::withoutGlobalScopes()
                            ->where('branch_id', (int) $linkedPd->branch_id)
                            ->where('reference', 'like', $baseRef . '-B%')
                            ->exists();

                        if ($hasMutation) {
                            throw new \RuntimeException(
                                'Cannot delete: mutation logs already exist for related Purchase Delivery.'
                            );
                        }
                    }
                }

                // =========================
                // ✅ ROLLBACK incoming pool hanya untuk WALK-IN invoice
                // =========================
                if ($isWalkIn) {
                    $qtyByProduct = [];
                    foreach ($purchaseDetails as $d) {
                        $pid = (int) ($d->product_id ?? 0);
                        $qty = (int) ($d->quantity ?? 0);
                        if ($pid <= 0 || $qty <= 0) continue;

                        if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                        $qtyByProduct[$pid] += $qty;
                    }

                    foreach ($qtyByProduct as $pid => $qty) {
                        $poolRow = DB::table('stocks')
                            ->where('branch_id', (int) $p->branch_id)
                            ->whereNull('warehouse_id')
                            ->where('product_id', (int) $pid)
                            ->lockForUpdate()
                            ->first();

                        if ($poolRow) {
                            DB::table('stocks')
                                ->where('id', (int) $poolRow->id)
                                ->update([
                                    'qty_incoming' => DB::raw("GREATEST(COALESCE(qty_incoming,0) - {$qty}, 0)"),
                                    'updated_at'   => now(),
                                ]);
                        }
                    }
                }

                // =========================
                // ✅ DELETE PD hanya untuk WALK-IN (AUTO PD)
                // =========================
                if ($isWalkIn && $linkedPd) {

                    PurchaseDeliveryDetails::withoutGlobalScopes()
                        ->where('purchase_delivery_id', (int) $linkedPd->id)
                        ->delete();

                    $linkedPd->delete();
                }

                // =========================
                // ✅ Soft delete invoice details + invoice
                // =========================
                PurchaseDetail::withoutGlobalScopes()
                    ->where('purchase_id', (int) $p->id)
                    ->delete();

                $p->delete();
            });

            toast('Purchase Deleted (soft)!', 'warning');
            return redirect()->route('purchases.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('purchases.index');
        }
    }

    public function restore(int $id)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        $activeBranchId = $this->getActiveBranchId();

        try {
            DB::transaction(function () use ($id, $activeBranchId) {

                $p = Purchase::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail((int) $id);

                if ((int) $p->branch_id !== (int) $activeBranchId) {
                    abort(403, 'Active branch mismatch for this Purchase.');
                }

                if (!$p->trashed()) {
                    return;
                }

                // ✅ status/payment guard tetap (biar restore gak bikin aneh)
                $st = strtolower(trim((string) ($p->status ?? 'pending')));
                if ($st !== 'pending') {
                    throw new \RuntimeException('Only Pending Purchase (Invoice) can be restored.');
                }

                $paymentStatus = strtolower(trim((string) ($p->payment_status ?? 'unpaid')));
                if ($paymentStatus !== 'unpaid') {
                    throw new \RuntimeException('Cannot restore: Payment status must be Unpaid.');
                }

                // restore invoice
                $p->restore();

                // restore details
                PurchaseDetail::withTrashed()
                    ->where('purchase_id', (int) $p->id)
                    ->restore();

                // resolve PD IDs yg harus ikut restore (kalau sebelumnya ikut di-delete oleh destroy)
                $poId = (int) ($p->purchase_order_id ?? 0);
                $pdId = (int) ($p->purchase_delivery_id ?? 0);
                $isWalkIn = ($poId <= 0);

                $pdIdsToRestore = [];

                if ($isWalkIn) {
                    if ($pdId > 0) $pdIdsToRestore = [$pdId];
                } else {
                    // restore semua PD pada PO tsb yang terhapus
                    $pdIdsToRestore = PurchaseDelivery::withTrashed()
                        ->where('purchase_order_id', $poId)
                        ->whereNotNull('deleted_at')
                        ->pluck('id')
                        ->map(fn($x) => (int) $x)
                        ->toArray();
                }

                if (!empty($pdIdsToRestore)) {
                    PurchaseDelivery::withTrashed()
                        ->whereIn('id', array_map('intval', $pdIdsToRestore))
                        ->restore();

                    PurchaseDeliveryDetails::withTrashed()
                        ->whereIn('purchase_delivery_id', array_map('intval', $pdIdsToRestore))
                        ->restore();
                }

                // ✅ naikkan incoming pool hanya untuk WALK-IN restore
                if ($isWalkIn) {
                    $details = PurchaseDetail::withoutGlobalScopes()
                        ->where('purchase_id', (int) $p->id)
                        ->lockForUpdate()
                        ->get(['product_id', 'quantity']);

                    $qtyByProduct = [];
                    foreach ($details as $d) {
                        $pid = (int) ($d->product_id ?? 0);
                        $qty = (int) ($d->quantity ?? 0);
                        if ($pid <= 0 || $qty <= 0) continue;

                        if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                        $qtyByProduct[$pid] += $qty;
                    }

                    foreach ($qtyByProduct as $pid => $qty) {
                        $poolRow = DB::table('stocks')
                            ->where('branch_id', (int) $p->branch_id)
                            ->whereNull('warehouse_id')
                            ->where('product_id', (int) $pid)
                            ->lockForUpdate()
                            ->first();

                        if ($poolRow) {
                            DB::table('stocks')
                                ->where('id', (int) $poolRow->id)
                                ->update([
                                    'qty_incoming' => (int) ($poolRow->qty_incoming ?? 0) + (int) $qty,
                                    'updated_at'   => now(),
                                ]);
                        } else {
                            DB::table('stocks')->insert([
                                'branch_id'     => (int) $p->branch_id,
                                'warehouse_id'  => null,
                                'product_id'    => (int) $pid,
                                'qty_available' => 0,
                                'qty_reserved'  => 0,
                                'qty_incoming'  => (int) $qty,
                                'min_stock'     => 0,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ]);
                        }
                    }
                }
            });

            toast('Purchase Restored!', 'success');
            return redirect()->route('purchases.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('purchases.index');
        }
    }

    public function forceDestroy(int $id)
    {
        abort_if(Gate::denies('delete_purchases'), 403);

        $activeBranchId = $this->getActiveBranchId();

        try {
            DB::transaction(function () use ($id, $activeBranchId) {

                $p = Purchase::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail((int) $id);

                if ((int) $p->branch_id !== (int) $activeBranchId) {
                    abort(403, 'Active branch mismatch for this Purchase.');
                }

                // ✅ rekomendasi aman: force delete hanya kalau sudah trashed
                if (!$p->trashed()) {
                    throw new \RuntimeException('Please soft delete the Purchase first before force delete.');
                }

                // resolve PD IDs yg akan ikut force delete (konsisten dengan destroy/restore)
                $poId = (int) ($p->purchase_order_id ?? 0);
                $pdId = (int) ($p->purchase_delivery_id ?? 0);
                $isWalkIn = ($poId <= 0);

                $pdIdsToForce = [];

                if ($isWalkIn) {
                    if ($pdId > 0) $pdIdsToForce = [$pdId];
                } else {
                    $pdIdsToForce = PurchaseDelivery::withTrashed()
                        ->where('purchase_order_id', $poId)
                        ->pluck('id')
                        ->map(fn($x) => (int) $x)
                        ->toArray();
                }

                if (!empty($pdIdsToForce)) {
                    PurchaseDeliveryDetails::withTrashed()
                        ->whereIn('purchase_delivery_id', array_map('intval', $pdIdsToForce))
                        ->forceDelete();

                    PurchaseDelivery::withTrashed()
                        ->whereIn('id', array_map('intval', $pdIdsToForce))
                        ->forceDelete();
                }

                // force delete invoice details
                PurchaseDetail::withTrashed()
                    ->where('purchase_id', (int) $p->id)
                    ->forceDelete();

                // force delete invoice
                $p->forceDelete();
            });

            toast('Purchase Deleted Permanently!', 'warning');
            return redirect()->route('purchases.index');

        } catch (\Throwable $e) {
            report($e);
            toast($e->getMessage(), 'error');
            return redirect()->route('purchases.index');
        }
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

        // ✅ IMPORTANT: jangan cast null menjadi 0
        $warehouseId = !empty($purchase->warehouse_id) ? (int) $purchase->warehouse_id : null;

        $autoPD = PurchaseDelivery::create([
            'purchase_order_id' => $purchase->purchase_order_id ?? null,
            'branch_id'         => (int) $purchase->branch_id,

            // ✅ boleh null untuk Pending PD (walk-in / PD pending)
            'warehouse_id'      => $warehouseId,

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

        // ✅ SUMBER PALING AMAN: purchase_details
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
