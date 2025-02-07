<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Http\Requests\StorePurchaseOrderPurchaseRequest;

class PurchaseOrderPurchasesController extends Controller
{

    public function __invoke(PurchaseOrder $purchaseorder) {
        abort_if(Gate::denies('create_purchase_order_purchases'), 403);

        $purchase_order_details = $purchaseorder->purchaseOrderDetails;
    
        Cart::instance('purchase')->destroy();
        $cart = Cart::instance('purchase');
    
        foreach ($purchase_order_details as $purchase_order_detail) {
            if ($purchase_order_detail->quantity > 0) { 
                $remaining_quantity = $purchase_order_detail->quantity; // Remaining quantity in PO
                $unit_price = $purchase_order_detail->unit_price;
                $updated_sub_total = $remaining_quantity * $unit_price;
        
                $cart->add([
                    'id'      => $purchase_order_detail->product_id,
                    'name'    => $purchase_order_detail->product_name,
                    'qty'     => $remaining_quantity,
                    'price'   => $purchase_order_detail->price,
                    'weight'  => 1,
                    'options' => [
                        'product_discount' => $purchase_order_detail->product_discount_amount,
                        'product_discount_type' => $purchase_order_detail->product_discount_type,
                        'sub_total'   => $updated_sub_total, // ✅ Update sub total
                        'code'        => $purchase_order_detail->product_code,
                        'stock'       => Product::findOrFail($purchase_order_detail->product_id)->product_quantity,
                        'product_tax' => $purchase_order_detail->product_tax_amount,
                        'unit_price'  => $unit_price
                    ]
                ]);
            }
        }
        
    
        return view('purchase-orders::purchase-order-purchases.create', [
            'purchase_order_id' => $purchaseorder->id,
            'purchase' => $purchaseorder
        ]);
    }
}
