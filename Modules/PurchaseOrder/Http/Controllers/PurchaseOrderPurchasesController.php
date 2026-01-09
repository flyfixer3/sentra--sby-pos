<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\PurchaseOrder\Entities\PurchaseOrder;

class PurchaseOrderPurchasesController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseorder)
    {
        abort_if(Gate::denies('create_purchase_order_purchases'), 403);

        $purchase_order_details = $purchaseorder->purchaseOrderDetails;

        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');

        foreach ($purchase_order_details as $d) {

            // ✅ QTY INVOICE = TOTAL PO
            $qty = (int) ($d->quantity ?? 0);
            if ($qty <= 0) continue;

            $unit_price = (float) ($d->unit_price ?? 0);
            $updated_sub_total = $qty * $unit_price;

            // stock (legacy kamu: ambil dari product_quantity)
            $stock = (int) (Product::find($d->product_id)->product_quantity ?? 0);

            $cart->add([
                'id'      => (int) $d->product_id,
                'name'    => (string) ($d->product_name ?? '-'),
                'qty'     => $qty,
                'price'   => (float) ($d->price ?? 0),
                'weight'  => 1,
                'options' => [
                    'product_discount'      => (float) ($d->product_discount_amount ?? 0),
                    'product_discount_type' => (string) ($d->product_discount_type ?? 'fixed'),
                    'sub_total'             => (float) $updated_sub_total,
                    'code'                  => (string) ($d->product_code ?? 'UNKNOWN'),
                    'stock'                 => $stock,
                    'product_tax'           => (float) ($d->product_tax_amount ?? 0),
                    'unit_price'            => (float) $unit_price,
                ]
            ]);
        }

        return view('purchase-orders::purchase-order-purchases.create', [
            'purchaseOrder'     => $purchaseorder,
            'purchase_order_id' => $purchaseorder->id,
            'purchaseDelivery'  => null,
        ]);
    }
}
