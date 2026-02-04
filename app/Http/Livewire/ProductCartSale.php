<?php

namespace App\Http\Livewire;

use App\Support\BranchContext;
use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;
use Modules\Mutation\Entities\Mutation;

class ProductCartSale extends Component
{
    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $global_discount;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee = 0;

    // [product_id] => total stock pada branch (gabungan semua warehouse)
    public $check_quantity;

    // [product_id] => qty
    public $quantity;

    public $discount_type;
    public $item_discount;
    public $item_cost_konsyinasi;

    public $data;

    public function mount($cartInstance, $data = null)
    {
        $this->cart_instance = $cartInstance;

        if ($data) {
            $this->data = $data;

            $this->global_discount = (int) ($data->discount_percentage ?? 0);
            $this->global_tax      = (int) ($data->tax_percentage ?? 0);
            $this->global_qty      = Cart::instance($this->cart_instance)->count();
            $this->shipping        = (int) ($data->shipping_amount ?? 0);
            $this->platform_fee    = (int) ($data->fee_amount ?? 0);

            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();

            $this->check_quantity = [];
            $this->quantity = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->item_cost_konsyinasi = [];

            $cart_items = Cart::instance($this->cart_instance)->content();
            foreach ($cart_items as $cart_item) {
                $this->check_quantity[$cart_item->id] = (int) ($cart_item->options->stock ?? 0);
                $this->quantity[$cart_item->id] = (int) $cart_item->qty;
                $this->discount_type[$cart_item->id] = (string) ($cart_item->options->product_discount_type ?? 'fixed');
                $this->item_cost_konsyinasi[$cart_item->id] = (int) ($cart_item->options->product_cost ?? 0);

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$cart_item->id] = (float) ($cart_item->options->product_discount ?? 0);
                } else {
                    $price = (float) ($cart_item->price ?? 0);
                    $disc = (float) ($cart_item->options->product_discount ?? 0);
                    $this->item_discount[$cart_item->id] = $price > 0 ? round(100 * ($disc / $price)) : 0;
                }
            }
        } else {
            $this->global_discount = 0;
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0;
            $this->platform_fee = 0;

            $this->check_quantity = [];
            $this->quantity = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->item_cost_konsyinasi = [];
        }
    }

    public function render()
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart-sale', [
            'cart_items' => $cart_items
        ]);
    }

    private function getTotalStockByBranch(int $productId): int
    {
        $branchId = (int) BranchContext::id();

        $q = Mutation::query()->where('product_id', $productId)->orderByDesc('id');

        if ($branchId > 0) {
            $q->where('branch_id', $branchId);
        }

        // ambil mutation terakhir per warehouse, lalu jumlah stock_last
        $rows = $q->get()->unique('warehouse_id');

        return (int) $rows->sum('stock_last');
    }

    public function productSelected($result)
    {
        $cart = Cart::instance($this->cart_instance);
        $product = $result;

        $stockTotal = $this->getTotalStockByBranch((int) $product['id']);

        if ($stockTotal <= 0 && ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return')) {
            session()->flash('message', 'The requested quantity is not available in stock (Branch Total Stock = 0).');
        }

        $calc = $this->calculate($product);

        $cart->add([
            'id'      => (int) $product['id'],
            'name'    => (string) $product['product_name'],
            'qty'     => 1,
            'price'   => (int) $calc['price'],
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => (int) $calc['sub_total'],
                'code'                  => (string) $product['product_code'],
                'stock'                 => (int) $stockTotal,
                'unit'                  => (string) $product['product_unit'],
                'warehouse_id'          => null,
                'product_tax'           => (int) $calc['product_tax'],
                'product_cost'          => (int) $calc['product_cost'],
                'unit_price'            => (int) $calc['unit_price']
            ]
        ]);

        $this->global_qty = $cart->count();
        $this->check_quantity[(int) $product['id']] = (int) $stockTotal;
        $this->quantity[(int) $product['id']] = 1;
        $this->discount_type[(int) $product['id']] = 'fixed';
        $this->item_discount[(int) $product['id']] = null;
        $this->item_cost_konsyinasi[(int) $product['id']] = 0;
    }

    public function removeItem($row_id)
    {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updatedGlobalQuantity()
    {
        Cart::instance($this->cart_instance)->setGlobalQuantity((integer)$this->global_qty);
    }

    public function updatedGlobalTax()
    {
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }

    public function updatedGlobalDiscount()
    {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id)
    {
        $product_id = (int) $product_id;

        $stockTotal = $this->getTotalStockByBranch($product_id);
        $this->check_quantity[$product_id] = $stockTotal;

        if ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return') {
            if ((int) $stockTotal < (int) ($this->quantity[$product_id] ?? 0)) {
                session()->flash('message', 'The requested quantity is not available in stock (Branch Total Stock).');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, (int) $this->quantity[$product_id]);

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => (int) $stockTotal,
                'unit'                  => $cart_item->options->unit,
                'warehouse_id'          => null,
                'product_tax'           => $cart_item->options->product_tax,
                'product_cost'          => $cart_item->options->product_cost,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ]
        ]);
    }

    private function calculate($product): array
    {
        $price = (int) ($product['product_price'] ?? 0);
        $sub_total = $price;
        $product_tax = 0;
        $product_cost = (int) ($product['product_cost'] ?? 0);
        $unit_price = $price;

        return [
            'price' => $price,
            'sub_total' => $sub_total,
            'product_tax' => $product_tax,
            'product_cost' => $product_cost,
            'unit_price' => $unit_price,
        ];
    }
}
