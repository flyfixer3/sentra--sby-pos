<?php

namespace Modules\Quotation\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\Quotation\Entities\Quotation;
use Modules\Quotation\Http\Requests\StoreQuotationSaleRequest;

class QuotationSalesController extends Controller
{

    public function __invoke(Quotation $quotation) {
        abort_if(Gate::denies('create_quotation_sales'), 403);

        $quotation_details = $quotation->quotationDetails;

        Cart::instance('sale')->destroy();

        $cart = Cart::instance('sale');

        // $cart->add([
        //     'id'      => $product['id'],
        //     'name'    => $product['product_name'],
        //     'qty'     => 1,
        //     'price'   => $this->calculate($product)['price'],
        //     'weight'  => 1,
        //     'options' => [
        //         'product_discount'      => 0.00,
        //         'product_discount_type' => 'fixed',
        //         'sub_total'             => $this->calculate($product)['sub_total'],
        //         'code'                  => $product['product_code'],
        //         'stock'                 => $stock_last,
        //         'unit'                  => $product['product_unit'],
        //         'warehouse_id'          => $warehouse_id,
        //         'product_tax'           => $this->calculate($product)['product_tax'],
        //         'product_cost'          => $warehouse->warehouse_code == 'KS' ? 0 :
        //         $this->calculate($product)['product_cost'],
        //         'unit_price'            => $this->calculate($product)['unit_price']
        //     ]
        // ]);
        foreach ($quotation_details as $quotation_detail) {
            $product = Product::findOrFail($quotation_detail->product_id);
            $cart->add([
                'id'      => $quotation_detail->product_id,
                'name'    => $quotation_detail->product_name,
                'qty'     => $quotation_detail->quantity,
                'price'   => $quotation_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $quotation_detail->product_discount_amount,
                    'product_discount_type' => $quotation_detail->product_discount_type,
                    'sub_total'   => $quotation_detail->sub_total,
                    'code'        => $quotation_detail->product_code,
                    'unit'        => $product->product_unit,
                    'stock'       =>$product->product_quantity,
                    'warehouse_id'=> 99,
                    'product_cost' => $product->product_cost,
                    'product_tax' => $quotation_detail->product_tax_amount,
                    'unit_price'  => $quotation_detail->unit_price
                ]
            ]);
        }

        return view('quotation::quotation-sales.create', [
            'quotation_id' => $quotation->id,
            'sale' => $quotation
        ]);
    }
}
