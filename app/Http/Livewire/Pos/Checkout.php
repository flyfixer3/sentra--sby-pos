<?php

namespace App\Http\Livewire\Pos;

use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;

class Checkout extends Component
{

    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $customers;
    public $global_discount;
    public $global_tax;
    public $shipping;
    public $quantity;
    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $data;
    public $customer_id;
    public $total_amount;

    public function mount($cartInstance, $customers) {
        $this->cart_instance = $cartInstance;
        $this->customers = $customers;
        $this->global_discount = 0;
        $this->global_tax = 0;
        $this->shipping = 0.00;
        $this->check_quantity = [];
        $this->quantity = [];
        $this->discount_type = [];
        $this->item_discount = [];
        $this->total_amount = 0;
    }

    public function hydrate() {
        $this->total_amount = $this->calculateTotal();
        $this->updatedCustomerId();
    }

    public function render() {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.pos.checkout', [
            'cart_items' => $cart_items
        ]);
    }

    public function proceed() {
        if ($this->customer_id != null) {
            $this->dispatchBrowserEvent('showCheckoutModal');
        } else {
            session()->flash('message', 'Please Select Customer!');
        }
    }

    public function calculateTotal() {
        return Cart::instance($this->cart_instance)->total() + $this->shipping;
    }

    public function resetCart() {
        Cart::instance($this->cart_instance)->destroy();
    }

    public function productSelected($product) {
        $cart = Cart::instance($this->cart_instance);

        $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
            return $cartItem->id == $product['id'];
        });

        if ($exists->isNotEmpty()) {
            session()->flash('message', 'Product exists in the cart!');

            return;
        }

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
                'stock'                 => $product['product_quantity'],
                'unit'                  => $product['product_unit'],
                'product_tax'           => $this->calculate($product)['product_tax'],
                'unit_price'            => $this->calculate($product)['unit_price']
            ]
        ]);

        $this->check_quantity[$product['id']] = $product['product_quantity'];
        $this->quantity[$product['id']] = 1;
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = 0;
        $this->total_amount = $this->calculateTotal();
    }

    public function removeItem($row_id) {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updatedGlobalTax() {
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }

    public function updatedGlobalDiscount() {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id) {
        if ($this->check_quantity[$product_id] < $this->quantity[$product_id]) {
            session()->flash('message', 'The requested quantity is not available in stock.');

            return;
        }

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        if (!$cart_item) {
            return;
        }

        $options = (array) $cart_item->options;

        Cart::instance($this->cart_instance)->update($row_id, $this->quantity[$product_id]);

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        if (!$cart_item) {
            return;
        }

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $options['code'] ?? null,
                'stock'                 => $options['stock'] ?? 0,
                'unit'                  => $options['unit'] ?? null,
                'product_tax'           => $options['product_tax'] ?? 0,
                'unit_price'            => $options['unit_price'] ?? $cart_item->price,
                'product_discount'      => $options['product_discount'] ?? 0,
                'product_discount_type' => $options['product_discount_type'] ?? 'fixed',
            ]
        ]);
    }

    public function updatedDiscountType($value, $name) {
        $this->item_discount[$name] = 0;
    }

    public function discountModalRefresh($product_id, $row_id) {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        if (!$cart_item) {
            return;
        }

        $discountType = $this->discount_type[$product_id] ?? ($cart_item->options->product_discount_type ?? 'fixed');
        $this->discount_type[$product_id] = $discountType === 'percentage' ? 'percentage' : 'fixed';

        if ($this->discount_type[$product_id] === 'fixed') {
            $this->item_discount[$product_id] = max(0, (float) ($cart_item->price ?? 0));
        } else {
            $basePrice = max(0, (float) (($cart_item->price ?? 0) + ($cart_item->options->product_discount ?? 0)));
            $discountAmount = max(0, (float) ($cart_item->options->product_discount ?? 0));
            $this->item_discount[$product_id] = $basePrice > 0
                ? round(($discountAmount / $basePrice) * 100, 2)
                : 0;
        }

        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id) {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        if (!$cart_item) {
            return;
        }

        $discountType = $this->discount_type[$product_id] ?? ($cart_item->options->product_discount_type ?? 'fixed');
        $discountType = $discountType === 'percentage' ? 'percentage' : 'fixed';
        $basePrice = max(0, (float) (($cart_item->price ?? 0) + ($cart_item->options->product_discount ?? 0)));

        if ($discountType == 'fixed') {
            $newRowPrice = max(0, (float) ($this->item_discount[$product_id] ?? 0));
            $discount_amount = max(0, $basePrice - $newRowPrice);

            Cart::instance($this->cart_instance)
                ->update($row_id, [
                    'price' => $newRowPrice
                ]);

            $updatedItem = Cart::instance($this->cart_instance)->get($row_id);
            if (!$updatedItem) {
                return;
            }

            $this->updateCartOptions($row_id, $product_id, $updatedItem, $discount_amount);
            $this->item_discount[$product_id] = $newRowPrice;
        } elseif ($discountType == 'percentage') {
            $percentage = max(0, min(100, (float) ($this->item_discount[$product_id] ?? 0)));
            $discount_amount = round($basePrice * ($percentage / 100), 2);
            $newRowPrice = max(0, round($basePrice - $discount_amount, 2));

            Cart::instance($this->cart_instance)
                ->update($row_id, [
                    'price' => $newRowPrice
                ]);

            $updatedItem = Cart::instance($this->cart_instance)->get($row_id);
            if (!$updatedItem) {
                return;
            }

            $this->updateCartOptions($row_id, $product_id, $updatedItem, $discount_amount);
            $this->item_discount[$product_id] = $percentage;
        }

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function calculate($product) {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;

        if ($product['product_tax_type'] == 1) {
            $price = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
            $unit_price = $product['product_price'];
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
        } elseif ($product['product_tax_type'] == 2) {
            $price = $product['product_price'];
            $unit_price = $product['product_price'] - ($product['product_price'] * ($product['product_order_tax'] / 1));
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'];
        } else {
            $price = $product['product_price'];
            $unit_price = $product['product_price'];
            $product_tax = 0.00;
            $sub_total = $product['product_price'];
        }

        return ['price' => $price, 'unit_price' => $unit_price, 'product_tax' => $product_tax, 'sub_total' => $sub_total];
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount) {
        $freshItem = Cart::instance($this->cart_instance)->get($row_id);
        if (!$freshItem) {
            return;
        }

        Cart::instance($this->cart_instance)->update($row_id, ['options' => [
            'sub_total'             => $freshItem->price * $freshItem->qty,
            'code'                  => $freshItem->options->code,
            'stock'                 => $freshItem->options->stock,
            'unit'                 => $freshItem->options->unit,
            'product_tax'           => $freshItem->options->product_tax,
            'unit_price'            => $freshItem->options->unit_price,
            'product_discount'      => max(0, (float) $discount_amount),
            'product_discount_type' => ($this->discount_type[$product_id] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed',
        ]]);
    }
}
