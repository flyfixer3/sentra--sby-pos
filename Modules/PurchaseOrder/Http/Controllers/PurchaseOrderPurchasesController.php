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

        $purchaseorder->loadMissing(['purchaseOrderDetails']);

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        foreach ($purchaseorder->purchaseOrderDetails as $d) {

            // ✅ QTY INVOICE = TOTAL PO (sesuai request kamu)
            $qty = (int) ($d->quantity ?? 0);
            if ($qty <= 0) continue;

            $unit_price = (float) ($d->unit_price ?? 0);

            /**
             * IMPORTANT:
             * - di cart, "price" dipakai sebagai selling/buying price yang kamu gunakan untuk total
             * - sub_total harus ikut price (bukan unit_price), biar konsisten ke perhitungan di cart
             */
            $price = (float) ($d->price ?? 0);
            $updated_sub_total = $qty * $price;

            // ✅ Stock: TOTAL dari ALL warehouses pada active branch
            // asumsi table stocks punya qty_available dan warehouse_id not null untuk per-warehouse
            $stockAll = (int) DB::table('stocks')
                ->where('branch_id', $branchId)
                ->whereNotNull('warehouse_id')
                ->where('product_id', (int) $d->product_id)
                ->sum(DB::raw('COALESCE(qty_available,0)'));

            // fallback terakhir kalau tabel stocks belum lengkap / belum ada record
            if ($stockAll <= 0) {
                $p = Product::select('id', 'product_quantity')->find((int) $d->product_id);
                $stockAll = (int) ($p?->product_quantity ?? 0);
            }

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

                    'code'                  => (string) ($d->product_code ?? 'UNKNOWN'),

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
