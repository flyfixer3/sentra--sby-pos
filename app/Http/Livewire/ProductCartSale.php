<?php

namespace App\Http\Livewire;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Request;
use Livewire\Component;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Entities\Warehouse;

class ProductCartSale extends Component
{

    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $global_discount;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee = 0;
    public $quantity;
    public $warehouse_id;
    public $warehouses;
    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $item_cost_konsyinasi;
    public $data;

    public function mount($cartInstance, $data = null, $warehouses) {
        $this->cart_instance = $cartInstance;
        $this->warehouses = $warehouses;
        if ($data) {
            $this->data = $data;

            $this->global_discount = $data->discount_percentage;
            $this->global_tax = $data->tax_percentage;
            $this->global_qty = Cart::instance($this->cart_instance)->count();
            $this->shipping = $data->shipping_amount;
            $this->platform_fee = $data->fee_amount ?? 0;
            
            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();
            // $this->updatedGlobalQuantity();
            
            $cart_items = Cart::instance($this->cart_instance)->content();
            
            foreach ($cart_items as $cart_item) {

                // dd($cart_item->options);
                $this->check_quantity[$cart_item->id] = [$cart_item->options->stock];
                $this->quantity[$cart_item->id] = $cart_item->qty;
                $this->warehouse_id[$cart_item->id] = $cart_item->options->warehouse_id;
                $this->discount_type[$cart_item->id] = $cart_item->options->product_discount_type;
                $this->item_cost_konsyinasi[$cart_item->id] = $cart_item->options->product_cost;
                if ($cart_item->options->product_discount_type == 'fixed') {
                    $this->item_discount[$cart_item->id] = $cart_item->options->product_discount;
                } elseif ($cart_item->options->product_discount_type == 'percentage') {
                    $this->item_discount[$cart_item->id] = round(100 * ($cart_item->options->product_discount / $cart_item->price));
                }
            }
        } else {
            $this->global_discount = 0;
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0.00;
            $this->platform_fee = 0;
            $this->check_quantity = [];
            $this->quantity = [];
            $this->warehouse_id = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->item_cost_konsyinasi = [];

        }
    }

    public function render() {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart-sale', [
            'cart_items' => $cart_items
        ]);
    }

    public function productSelected($result) {
        $cart = Cart::instance($this->cart_instance);
        $product = $result;
        // if($this->cart_instance == 'sale'){
        //     // dd($product['product']);
        //     $product = $result['product'];
        // }

        $warehouse = Mutation::with('warehouse')->where('product_id', $product['id'])
                ->latest()
                ->get()
                ->unique('warehouse_id')
                ->sortByDesc('stock_last')
                ->first();
        // dd($warehouse);
        $stock_last = 0;
        $warehouse_id;
        if(!$warehouse){
            if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
                session()->flash('message', 'The requested quantity is not available in stock.');
            }
            $warehouse = Warehouse::first();
            $warehouse_id = $warehouse->id;
        }else{
            $stock_last = $warehouse->stock_last;
            $warehouse_id = $warehouse->warehouse_id;
        }

        // $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
        //     return $cartItem->id == $product['id'];
        // });

        // if ($exists->isNotEmpty()) {
        //     session()->flash('message', 'Product exists in the cart!');

        //     return;
        // }
        $cart->add([
            'id'      => $product['id'],
            'name'    => $product['product_name'],
            'qty'     => 1,
            'price'   => $this->calculate($product)['price'],
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => $this->calculate($product)['sub_total'],
                'code'                  => $product['product_code'],
                'stock'                 => $stock_last,
                'unit'                  => $product['product_unit'],
                'warehouse_id'          => $warehouse_id,
                'product_tax'           => $this->calculate($product)['product_tax'],
                'product_cost'          => $warehouse->warehouse_code == 'KS' ? 0 :
                $this->calculate($product)['product_cost'],
                'unit_price'            => $this->calculate($product)['unit_price']
            ]
        ]);
        // dd($cart);
        $this->global_qty = $cart->count();
        $this->check_quantity[$product['id']] = $stock_last;
        $this->quantity[$product['id']] = 1;
        $this->warehouse_id[$product['id']] = $warehouse_id;
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = null;
        $this->item_cost_konsyinasi[$product['id']] = 0;

        // $this->updatedGlobalQuantity();
    }

    public function removeItem($row_id) {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updatedGlobalQuantity() {
        Cart::instance($this->cart_instance)->setGlobalQuantity((integer)$this->global_qty);
    }
    public function updatedGlobalTax() {
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }
 
    public function updatedGlobalDiscount() {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateWarehouse($row_id, $product_id, $value_id) {

        $warehouse = Mutation::with('warehouse')
        ->where('warehouse_id', $value_id)
        ->where('product_id', $product_id)
        ->latest()
        ->get()
        ->first();
        $stock_last = 0;
        $warehouse_id = $value_id;
        if(!$warehouse){
            if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
                session()->flash('message', 'The requested quantity is not available in stock.');
            }
        }elseif($warehouse->stock_last < $this->quantity[$product_id]){
            if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
                session()->flash('message', 'The requested quantity is not available in stock.');
            }
            $stock_last = $warehouse->stock_last;
        }else{
            $stock_last = $warehouse->stock_last;
        }

        $this->check_quantity[$product_id] = $stock_last;
        $this->warehouse_id[$product_id] = $warehouse_id;
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => $stock_last,
                'unit'                  => $cart_item->options->unit,
                'warehouse_id'          => $warehouse_id,
                'product_tax'           => $cart_item->options->product_tax,
                'product_cost'          => $cart_item->options->product_cost,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ]
        ]);
        // if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
        //     if ($this->check_quantity[$product_id] < $this->quantity[$product_id]) {
        //         session()->flash('message', 'The requested quantity is not available in stock.');
        //         return;
        //     }
        // }

    
        
    }

    public function updateQuantity($row_id, $product_id) {
        if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            if ($this->check_quantity[$product_id] < $this->quantity[$product_id]) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, $this->quantity[$product_id]);

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();
        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => $cart_item->options->stock,
                'unit'                  => $cart_item->options->unit,
                'warehouse_id'          => $cart_item->options->warehouse_id,
                'product_tax'           => $cart_item->options->product_tax,
                'product_cost'          => $cart_item->options->product_cost,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ]
        ]);
    }

    public function updatedDiscountType($value, $name) {
        $this->item_discount[$name] = 0;
    }

    public function discountModalRefresh($product_id, $row_id) {
        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id) {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        if ($this->discount_type[$product_id] == 'fixed') {
            if($this->item_discount[$product_id]){
                $discount_amount = ($cart_item->price + $cart_item->options->product_discount) -
                $this->item_discount[$product_id];
                Cart::instance($this->cart_instance)
                    ->update($row_id, [
                        'price' => $this->item_discount[$product_id],
                    ]);
            }else{
                $discount_amount = 0;
            }

            $this->updateCartOptions($row_id, $product_id, $cart_item, $discount_amount,
                $this->item_cost_konsyinasi[$product_id],$this->warehouse_id[$product_id]);
        } elseif ($this->discount_type[$product_id] == 'percentage') {
            $discount_amount = ($cart_item->price + $cart_item->options->product_discount) *
            ($this->item_discount[$product_id] / 100);

            Cart::instance($this->cart_instance)
                ->update($row_id, [
                    'price' => ($cart_item->price + $cart_item->options->product_discount) - $discount_amount,
                ]);

            $this->updateCartOptions($row_id, $product_id, $cart_item, $discount_amount,
                $this->item_cost_konsyinasi[$product_id],$this->warehouse_id[$product_id]);
        }

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function calculate($product) {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;
        $product_cost = 0;

        if ($product['product_tax_type'] == 1) {
            $price = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
            $unit_price = $product['product_price'];
            $product_cost = $product['product_cost'];
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
        } elseif ($product['product_tax_type'] == 2) {
            $price = $product['product_price'];
            $unit_price = $product['product_price'] - ($product['product_price'] * ($product['product_order_tax'] / 1));
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'];
            $product_cost = $product['product_cost'];
        } else {
            $price = $product['product_price'];
            $unit_price = $product['product_price'];
            $product_tax = 0.00;
            $sub_total = $product['product_price'];
            $product_cost = $product['product_cost'];
        }

        return ['price' => $price, 'unit_price' => $unit_price, 'product_tax' => $product_tax,'product_cost' => $product_cost, 'sub_total' => $sub_total];
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount, $item_cost_konsyinasi, $warehouse_id) {
        Cart::instance($this->cart_instance)->update($row_id, ['options' => [
            'sub_total'             => $cart_item->price * $cart_item->qty,
            'code'                  => $cart_item->options->code,
            'stock'                 => $cart_item->options->stock,
            'unit'                  => $cart_item->options->unit,
            'product_tax'           => $cart_item->options->product_tax,
            'warehouse_id'          => $warehouse_id,
            'product_cost'          => $item_cost_konsyinasi,
            'unit_price'            => $cart_item->options->unit_price,
            'product_discount'      => $discount_amount,
            'product_discount_type' => $this->discount_type[$product_id],
        ]]);
    }
}
