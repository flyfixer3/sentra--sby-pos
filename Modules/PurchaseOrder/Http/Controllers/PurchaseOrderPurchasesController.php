<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\PurchaseOrder\Entities\PurchaseOrder;

class PurchaseOrderPurchasesController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseorder)
    {
        abort_if(Gate::denies('create_purchase_order_purchases'), 403);

        // ✅ branch aktif wajib spesifik (biar stock all warehouse bisa difilter 1 cabang)
        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('purchase-orders.show', $purchaseorder->id)
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create a Purchase from PO.");
        }

        $branchId = (int) $active;

        // (opsional tapi aman) pastikan PO milik cabang aktif
        if ((int) $purchaseorder->branch_id !== $branchId) {
            abort(403, 'You can only create Purchase from Purchase Orders in the active branch.');
        }

        if ($purchaseorder->hasActiveDeliveries()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseorder->id)
                ->with('error', 'This PO already has delivery-based invoice flow. Please create invoice from the related Purchase Delivery instead.');
        }

        if ($purchaseorder->hasInvoice()) {
            return redirect()
                ->route('purchase-orders.show', $purchaseorder->id)
                ->with('error', 'Invoice for this Purchase Order has already been created.');
        }

        $purchaseorder->loadMissing(['purchaseOrderDetails']);

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        $getStockAllWarehousesInBranch = function (int $productId) use ($branchId): int {
            $warehouseIds = DB::table('warehouses')
                ->where('branch_id', (int) $branchId)
                ->pluck('id')
                ->toArray();

            if (empty($warehouseIds)) {
                return 0;
            }

            $sum = 0;
            foreach ($warehouseIds as $warehouseId) {
                $last = DB::table('mutations')
                    ->where('product_id', (int) $productId)
                    ->where('warehouse_id', (int) $warehouseId)
                    ->latest('id')
                    ->value('stock_last');

                $sum += (int) ($last ?? 0);
            }

            return (int) $sum;
        };

        foreach ($purchaseorder->purchaseOrderDetails as $d) {

            // ✅ QTY INVOICE = TOTAL PO (sesuai request kamu)
            $qty = (int) ($d->quantity ?? 0);
            if ($qty <= 0) continue;

            $product = Product::select('id', 'product_code')
                ->find((int) $d->product_id);

            $unit_price = (float) ($d->unit_price ?? 0);

/**
             * IMPORTANT:
             * - di cart, "price" dipakai sebagai selling/buying price yang kamu gunakan untuk total
             * - sub_total harus ikut price (bukan unit_price), biar konsisten ke perhitungan di cart
             */
            $price = (float) ($d->price ?? 0);
            $updated_sub_total = $qty * $price;

            $productCode = trim((string) ($d->product_code ?? ''));
            if ($productCode === '') {
                $productCode = (string) ($product?->product_code ?? 'UNKNOWN');
            }

            $stockAll = (int) $getStockAllWarehousesInBranch((int) $d->product_id);

            $cart->add([
                'id'      => (int) $d->product_id,
                'name'    => (string) ($d->product_name ?? '-'),
                'qty'     => $qty,
                'price'   => $price,
                'weight'  => 1,
                'options' => [
                    'product_discount'      => (float) ($d->product_discount_amount ?? 0),
                    'product_discount_type' => (string) ($d->product_discount_type ?? 'fixed'),

// ✅ subtotal konsisten pakai price
                    'sub_total'             => (float) $updated_sub_total,

                    'code'                  => $productCode,

                    // ✅ stock all wh
                    'stock'                 => (int) $stockAll,

                    'product_tax'           => (float) ($d->product_tax_amount ?? 0),
                    'unit_price'            => (float) $unit_price,

                    // ✅ ini bikin UI kamu keluarin note "ALL warehouses"
                    'stock_scope'           => 'branch',
                ]
            ]);
        }

        // ✅ view path harus match blade kamu: purchase-orders::purchase-order-purchases.create
        return view('purchase-orders::purchase-order-purchases.create', [
            'purchaseOrder'     => $purchaseorder,
            'purchase_order_id' => $purchaseorder->id,
            'purchaseDelivery'  => null,
        ]);
    }
}
