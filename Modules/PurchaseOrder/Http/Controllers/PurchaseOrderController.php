<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Mutation\Entities\Mutation;
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
    public function pdf(PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('show_purchase_orders'), 403);

        $activeBranch = BranchContext::active();

        $purchase_order = PurchaseOrder::withoutGlobalScopes()
            ->with([
                'purchaseOrderDetails.product',
                'supplier',
                'branch',
            ])
            ->findOrFail((int) $purchase_order->id);

        if ($activeBranch !== 'all' && $activeBranch !== null && $activeBranch !== '') {
            abort_if((int) $purchase_order->branch_id !== (int) $activeBranch, 403);
        }

        $purchase_order->loadMissing([
            'purchaseOrderDetails.product',
            'supplier',
            'branch',
        ]);

        $supplier = $purchase_order->supplier;
        $branch = $purchase_order->branch;

        $pdf = Pdf::loadView('purchase-orders::print', [
            'purchase_order' => $purchase_order,
            'supplier' => $supplier,
            'branch' => $branch,
        ])->setPaper('a4');

        return $pdf->stream('purchase-order-' . $purchase_order->reference . '.pdf');
    }

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

        $totals = $this->calculateOrderTotals($request, $cart);

        DB::transaction(function () use ($request, $branchId, $cart, $totals) {

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
                'shipping_amount'      => (float) $totals['shipping_amount'],
                'total_amount'         => (float) $totals['total_amount'],

                'status'               => 'Pending',
                'note'                 => $request->note,
                'tax_amount'           => (float) $totals['tax_amount'],
                'discount_amount'      => (float) $totals['discount_amount'],
                'created_by'           => auth()->id(),
                'updated_by'           => auth()->id(),
            ]);

            // ==========================
            // ✅ CREATE PO DETAILS
            // ==========================
            foreach ($cart->content() as $item) {
                $product = Product::withoutGlobalScopes()->find((int) $item->id);

                PurchaseOrderDetails::create([
                    'purchase_order_id'        => (int) $purchaseOrder->id,
                    'product_id'               => (int) $item->id,
                    'product_name'             => (string) ($product->product_name ?? $item->name),
                    'product_code'             => (string) ($product->product_code ?? $item->options->code ?? ''),
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

                        'qty_total' => 0,
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
            'purchaseOrderDetails.product',
            'purchases',
            'purchaseDeliveries',
            'supplier',
            'creator',
            'updater',
            'sentToSupplierBy',
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

        if (strtolower(trim((string) ($purchase_order->status ?? ''))) !== 'pending') {
            return redirect()
                ->route('purchase-orders.show', $purchase_order->id)
                ->with('error', 'This Purchase Order can no longer be edited because it is no longer in Pending status.');
        }

        $purchase_order_details = $purchase_order->purchaseOrderDetails;

        Cart::instance('purchase_order')->destroy();
        $cart = Cart::instance('purchase_order');

        foreach ($purchase_order_details as $purchase_order_detail) {
            $product = Product::withoutGlobalScopes()
                ->select('id', 'product_unit', 'product_code', 'product_name')
                ->find((int) $purchase_order_detail->product_id);

            $productName = $purchase_order_detail->product_name ?: ($product?->product_name ?? null);
            $productCode = $purchase_order_detail->product_code ?: ($product?->product_code ?? null);

            $total_stock = Mutation::with('warehouse')
                ->where('product_id', (int) $purchase_order_detail->product_id)
                ->latest()
                ->get()
                ->unique('warehouse_id')
                ->sortByDesc('stock_last')
                ->sum('stock_last');

            $cart->add([
                'id'      => (int) $purchase_order_detail->product_id,
                'name'    => (string) ($productName ?? $purchase_order_detail->product_name),
                'qty'     => (int) $purchase_order_detail->quantity,
                'price'   => (float) $purchase_order_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount'      => (float) $purchase_order_detail->product_discount_amount,
                    'product_discount_type' => (string) $purchase_order_detail->product_discount_type,
                    'sub_total'             => (float) $purchase_order_detail->sub_total,
                    'code'                  => (string) ($productCode ?? $purchase_order_detail->product_code),
                    'stock'                 => (int) $total_stock,
                    'unit'                  => $product?->product_unit,
                    'product_tax'           => (float) $purchase_order_detail->product_tax_amount,
                    'unit_price'            => (float) $purchase_order_detail->unit_price,
                ],
            ]);
        }

        return view('purchase-orders::edit', compact('purchase_order'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('edit_purchase_orders'), 403);

        if (strtolower(trim((string) ($purchase_order->status ?? ''))) !== 'pending') {
            return redirect()
                ->route('purchase-orders.show', $purchase_order->id)
                ->with('error', 'This Purchase Order can no longer be edited because it is no longer in Pending status.');
        }

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Please select a specific branch first (not 'All Branch') to update a Purchase Order.");
        }
        $branchId = (int) $active;

        $cart = Cart::instance('purchase_order');
        $totals = $this->calculateOrderTotals($request, $cart);

        DB::transaction(function () use ($request, $purchase_order, $branchId, $totals) {

            if ((int) $purchase_order->branch_id !== $branchId) {
                abort(403, 'You can only edit Purchase Orders from the active branch.');
            }

            $oldFulfilledMap = $purchase_order->purchaseOrderDetails()
                ->get()
                ->mapWithKeys(fn ($d) => [(int) $d->product_id => (int) $d->fulfilled_quantity])
                ->toArray();

            $purchase_order->purchaseOrderDetails()->delete();

            $purchase_order->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => Supplier::findOrFail($request->supplier_id)->supplier_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $totals['shipping_amount'],
                'total_amount' => $totals['total_amount'],
                'note' => $request->note,
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                    'updated_by' => auth()->id(),
            ]);

            foreach (Cart::instance('purchase_order')->content() as $cart_item) {

                $fulfilled_qty = (int) ($oldFulfilledMap[(int) $cart_item->id] ?? 0);

                $newQty = (int) $cart_item->qty;
                if ($fulfilled_qty > $newQty) {
                    $fulfilled_qty = $newQty;
                }

                $product = Product::withoutGlobalScopes()->find((int) $cart_item->id);

                PurchaseOrderDetails::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id' => (int) $cart_item->id,
                    'product_name' => (string) ($product->product_name ?? $cart_item->name),
                    'product_code' => (string) ($product->product_code ?? $cart_item->options->code ?? ''),
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

            // ✅ status PO final sesuai rule baru
            $purchase_order->refresh();
            $purchase_order->refreshStatus();
        });

        toast('Purchase Order Updated!', 'info');
        return redirect()->route('purchase-orders.index');
    }

    public function markSentToSupplier(Request $request, PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('send_purchase_order_mails'), 403);

        $validated = $request->validate([
            'sent_to_supplier_note' => 'nullable|string|max:1000',
        ]);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->back()
                ->with('error', "Please select a specific branch first (not 'All Branch') to mark a Purchase Order as sent to supplier.");
        }
        $branchId = (int) $active;

        try {
            DB::transaction(function () use ($purchase_order, $validated, $branchId) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail((int) $purchase_order->id);

                if ((int) $po->branch_id !== $branchId) {
                    abort(403, 'You can only mark Purchase Orders from the active branch as sent to supplier.');
                }

                $statusLower = strtolower(trim((string) ($po->status ?? '')));
                if (in_array($statusLower, ['cancelled', 'canceled', 'deleted'], true)) {
                    throw new \RuntimeException('Cancelled or deleted Purchase Orders cannot be marked as sent to supplier.');
                }

                if (!empty($po->sent_to_supplier_at)) {
                    throw new \RuntimeException('This Purchase Order has already been marked as sent to supplier.');
                }

                $po->update([
                    'sent_to_supplier_at' => now(),
                    'sent_to_supplier_by' => Auth::id(),
                    'sent_to_supplier_note' => $validated['sent_to_supplier_note'] ?? null,
                ]);
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        toast('Purchase Order marked as sent to supplier.', 'success');

        return redirect()->back();
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        abort_if(Gate::denies('delete_purchase_orders'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('purchase-orders.index')
                ->with('error', "Please select a specific branch first (not 'All Branch').");
        }
        $branchId = (int) $active;

        try {
            DB::transaction(function () use ($purchase_order, $branchId) {

                // Lock PO row (prevent race delete/confirm/update)
                $po = \Modules\PurchaseOrder\Entities\PurchaseOrder::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail((int) $purchase_order->id);

                // Branch guard
                if ((int) $po->branch_id !== (int) $branchId) {
                    abort(403, 'You can only delete Purchase Orders from the active branch.');
                }

                // Rule 1: kalau sudah ada invoice/purchase, tidak boleh delete
                if (method_exists($po, 'hasInvoice') && $po->hasInvoice()) {
                    throw new \RuntimeException('Cannot delete: this Purchase Order already has an Invoice (Purchase).');
                }

                // Rule 2: kalau sudah ada Purchase Delivery, jangan allow delete
                $hasPD = DB::table('purchase_deliveries')
                    ->where('purchase_order_id', (int) $po->id)
                    ->exists();

                if ($hasPD) {
                    throw new \RuntimeException(
                        'Cannot delete: this Purchase Order already has Purchase Deliveries. Please delete/cancel the deliveries first.'
                    );
                }

                // Rule 3: kalau sudah ada fulfilled, jangan allow delete
                $totalFulfilled = (int) \Modules\PurchaseOrder\Entities\PurchaseOrderDetails::withoutGlobalScopes()
                    ->where('purchase_order_id', (int) $po->id)
                    ->sum('fulfilled_quantity');

                if ($totalFulfilled > 0) {
                    throw new \RuntimeException(
                        'Cannot delete: this Purchase Order already has fulfilled quantity (items already confirmed/received).'
                    );
                }

                // ✅ Lock details (karena soft delete tidak cascade)
                $details = \Modules\PurchaseOrder\Entities\PurchaseOrderDetails::withoutGlobalScopes()
                    ->where('purchase_order_id', (int) $po->id)
                    ->lockForUpdate()
                    ->get(['product_id', 'quantity']);

                // Aggregate qty per product dari PO details
                $qtyByProduct = [];
                foreach ($details as $d) {
                    $pid = (int) ($d->product_id ?? 0);
                    $qty = (int) ($d->quantity ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                    $qtyByProduct[$pid] += $qty;
                }

                // ✅ Rollback incoming pool (branch-level, warehouse_id NULL)
                foreach ($qtyByProduct as $productId => $qty) {

                    $poolRows = DB::table('stocks')
                        ->where('branch_id', (int) $branchId)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $productId)
                        ->lockForUpdate()
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($poolRows->isEmpty()) {
                        // create row biar konsisten, tapi incoming rollback tetap aman
                        DB::table('stocks')->insert([
                            'branch_id'     => (int) $branchId,
                            'warehouse_id'  => null,
                            'product_id'    => (int) $productId,
                            'qty_total' => 0,
                            'qty_reserved'  => 0,
                            'qty_incoming'  => 0,
                            'min_stock'     => 0,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);

                        $poolRows = DB::table('stocks')
                            ->where('branch_id', (int) $branchId)
                            ->whereNull('warehouse_id')
                            ->where('product_id', (int) $productId)
                            ->lockForUpdate()
                            ->orderBy('id', 'asc')
                            ->get();
                    }

                    $remainingToSubtract = (int) $qty;

                    foreach ($poolRows as $row) {
                        if ($remainingToSubtract <= 0) break;

                        $currentIncoming = (int) ($row->qty_incoming ?? 0);
                        if ($currentIncoming <= 0) continue;

                        $take = min($currentIncoming, $remainingToSubtract);
                        $newIncoming = $currentIncoming - $take;

                        DB::table('stocks')
                            ->where('id', (int) $row->id)
                            ->update([
                                'qty_incoming' => (int) $newIncoming,
                                'updated_at'   => now(),
                            ]);

                        $remainingToSubtract -= $take;
                    }
                }

                // ✅ FIX: Soft delete details dulu (biar deleted_at keisi)
                \Modules\PurchaseOrder\Entities\PurchaseOrderDetails::withoutGlobalScopes()
                    ->where('purchase_order_id', (int) $po->id)
                    ->delete();

                // ✅ Soft delete header terakhir
                $po->delete();
            });

            toast('Purchase Order Deleted! Incoming has been reverted.', 'warning');
            return redirect()->route('purchase-orders.index');

        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    private function calculateOrderTotals(Request $request, $cart): array
    {
        $itemsSubtotal = (float) $cart->content()->sum(function ($row) {
            return (float) ($row->price ?? 0) * (int) ($row->qty ?? 0);
        });

        $discountType = $request->input('discount_type', 'percentage');
        $discountType = $discountType === 'fixed' ? 'fixed' : 'percentage';

        $discountAmount = 0.0;
        if ($discountType === 'fixed') {
            $inputAmount = max(0, (float) $request->input('discount_amount', 0));
            $discountAmount = min($inputAmount, $itemsSubtotal);
        } else {
            $discountPercent = max(0, min(100, (float) $request->input('discount_percentage', 0)));
            $discountAmount = $itemsSubtotal * ($discountPercent / 100);
        }
        $discountAmount = round($discountAmount, 2);

        $taxPercent = max(0, min(100, (float) $request->input('tax_percentage', 0)));
        $taxBase = max(0, $itemsSubtotal - $discountAmount);
        $taxAmount = round($taxBase * ($taxPercent / 100), 2);

        $shippingAmount = max(0, (float) $request->input('shipping_amount', 0));
        $totalAmount = round($taxBase + $taxAmount + $shippingAmount, 2);

        return [
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
