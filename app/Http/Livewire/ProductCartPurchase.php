<?php

namespace App\Http\Livewire;

use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Entities\Warehouse;

class ProductCartPurchase extends Component
{
    public $listeners = [
        'productSelected',
        'discountModalRefresh',
        'purchaseWarehouseChanged',
    ];

    public $cart_instance;
    public $global_discount;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee;

    public $quantity;
    public $warehouse_id;
    public $loading_warehouse;

    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $item_cost_konsyinasi;
    public $data;

    public function mount($cartInstance, $data = null, $loading_warehouse = null)
    {
        $this->cart_instance = $cartInstance;

        $this->loading_warehouse = null;
        if (is_numeric($loading_warehouse)) {
            $this->loading_warehouse = Warehouse::find((int)$loading_warehouse);
        } else {
            $this->loading_warehouse = $loading_warehouse;
        }

        if (!$this->loading_warehouse) {
            $branchId = session('active_branch');
            $warehouse = null;

            if (!empty($branchId) && $branchId !== 'all') {
                $warehouse = Warehouse::where('branch_id', (int)$branchId)->where('is_main', 1)->first();
                if (!$warehouse) $warehouse = Warehouse::where('branch_id', (int)$branchId)->first();
            }

            if (!$warehouse) {
                $warehouse = Warehouse::where('is_main', 1)->first() ?? Warehouse::first();
            }

            $this->loading_warehouse = $warehouse;
        }

        if ($data) {
            $this->data = $data;

            $this->global_discount = $data->discount_percentage;
            $this->global_tax = $data->tax_percentage;
            $this->global_qty = Cart::instance($this->cart_instance)->count();
            $this->shipping = $data->shipping_amount;
            $this->platform_fee = $data->fee_amount;

            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();

            $cart_items = Cart::instance($this->cart_instance)->content();

            foreach ($cart_items as $cart_item) {
                $this->check_quantity[$cart_item->id] = (int)($cart_item->options->stock ?? 0);
                $this->quantity[$cart_item->id] = (int)$cart_item->qty;

                $this->warehouse_id[$cart_item->id] = (int)($cart_item->options->warehouse_id ?? $this->loading_warehouse->id);

                $this->discount_type[$cart_item->id] = $cart_item->options->product_discount_type ?? 'fixed';
                $this->item_cost_konsyinasi[$cart_item->id] = $cart_item->options->product_cost ?? 0;

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$cart_item->id] = $cart_item->options->product_discount ?? 0;
                } else {
                    $priceBase = ($cart_item->price > 0) ? $cart_item->price : 1;
                    $this->item_discount[$cart_item->id] = round(100 * (($cart_item->options->product_discount ?? 0) / $priceBase));
                }
            }
        } else {
            $this->global_discount = 0;
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0.00;
            $this->platform_fee = 0.00;

            $this->check_quantity = [];
            $this->quantity = [];
            $this->warehouse_id = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->item_cost_konsyinasi = [];
        }

        // Pastikan cart yang sudah ada (kalau reload) ikut pakai warehouse current
        $this->syncCartToCurrentWarehouse();
    }

    public function render()
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart-purchase', [
            'cart_items' => $cart_items
        ]);
    }

    /**
     * Dipanggil dari JS ketika dropdown warehouse berubah
     */
    public function purchaseWarehouseChanged($warehouseId)
    {
        $warehouseId = (int)$warehouseId;
        if ($warehouseId <= 0) return;

        $activeBranch = session('active_branch');
        if (!empty($activeBranch) && $activeBranch !== 'all') {
            $exists = Warehouse::where('id', $warehouseId)
                ->where('branch_id', (int)$activeBranch)
                ->exists();
            if (!$exists) {
                session()->flash('message', 'Selected warehouse does not belong to the active branch.');
                return;
            }
        }

        $wh = Warehouse::find($warehouseId);
        if (!$wh) return;

        $this->loading_warehouse = $wh;

        // Update seluruh cart items (warehouse_id + stock)
        $this->syncCartToCurrentWarehouse();
    }

    private function syncCartToCurrentWarehouse(): void
    {
        $warehouseId = (int)($this->loading_warehouse ? $this->loading_warehouse->id : 0);
        if ($warehouseId <= 0) return;

        $cart = Cart::instance($this->cart_instance);
        $items = $cart->content();

        foreach ($items as $row) {
            $productId = (int)$row->id;

            $stockLast = $this->getStockLastByWarehouse($productId, $warehouseId);

            $cart->update($row->rowId, [
                'options' => [
                    'sub_total'             => $row->price * $row->qty,
                    'code'                  => $row->options->code,
                    'stock'                 => $stockLast,
                    'unit'                  => $row->options->unit,
                    'warehouse_id'          => $warehouseId,
                    'product_tax'           => $row->options->product_tax,
                    'product_cost'          => $row->options->product_cost,
                    'unit_price'            => $row->options->unit_price,
                    'product_discount'      => $row->options->product_discount,
                    'product_discount_type' => $row->options->product_discount_type,
                ]
            ]);

            $this->check_quantity[$productId] = $stockLast;
            $this->warehouse_id[$productId] = $warehouseId;
        }

        $this->global_qty = $cart->count();
    }

    private function getStockLastByWarehouse(int $productId, int $warehouseId): int
    {
        $mutation = Mutation::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->latest()
            ->first();

        return $mutation ? (int)$mutation->stock_last : 0;
    }

    public function productSelected($product)
    {
        $cart = Cart::instance($this->cart_instance);

        $warehouseId = (int)($this->loading_warehouse ? $this->loading_warehouse->id : 0);
        if ($warehouseId <= 0) {
            session()->flash('message', 'Warehouse is not set. Please select a warehouse first.');
            return;
        }

        $stockLast = $this->getStockLastByWarehouse((int)$product['id'], $warehouseId);

        $calc = $this->calculate($product);

        $cart->add([
            'id'      => $product['id'],
            'name'    => $product['product_name'],
            'qty'     => 1,
            'price'   => $calc['price'],
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => $calc['sub_total'],
                'code'                  => $product['product_code'],
                'stock'                 => $stockLast,
                'unit'                  => $product['product_unit'],
                'warehouse_id'          => $warehouseId,
                'product_tax'           => $calc['product_tax'],
                'product_cost'          => $calc['product_cost'],
                'unit_price'            => $calc['unit_price'],
            ]
        ]);

        $this->global_qty = $cart->count();
        $this->check_quantity[$product['id']] = $stockLast;
        $this->quantity[$product['id']] = 1;
        $this->warehouse_id[$product['id']] = $warehouseId;
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = 0;
        $this->item_cost_konsyinasi[$product['id']] = 0;
    }

    public function removeItem($row_id)
    {
        Cart::instance($this->cart_instance)->remove($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();
    }

    public function updatedGlobalQuantity()
    {
        Cart::instance($this->cart_instance)->setGlobalQuantity((int)$this->global_qty);
    }

    public function updatedGlobalTax()
    {
        Cart::instance($this->cart_instance)->setGlobalTax((int)$this->global_tax);
    }

    public function updatedGlobalDiscount()
    {
        Cart::instance($this->cart_instance)->setGlobalDiscount((int)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id)
    {
        if ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            if ((int)($this->check_quantity[$product_id] ?? 0) < (int)($this->quantity[$product_id] ?? 0)) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, (int)$this->quantity[$product_id]);

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

    public function updatedDiscountType($value, $name)
    {
        $this->item_discount[$name] = 0;
    }

    public function discountModalRefresh($product_id, $row_id)
    {
        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id)
    {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);

        if (($this->discount_type[$product_id] ?? 'fixed') === 'fixed') {
            $discount_amount = 0;
            if (!empty($this->item_discount[$product_id])) {
                $discount_amount = ($cart_item->price + ($cart_item->options->product_discount ?? 0)) - ($this->item_discount[$product_id] ?? 0);
                Cart::instance($this->cart_instance)->update($row_id, [
                    'price' => ($this->item_discount[$product_id] ?? 0),
                ]);
            }

            $this->updateCartOptions(
                $row_id,
                $product_id,
                $cart_item,
                $discount_amount,
                $this->item_cost_konsyinasi[$product_id] ?? 0,
                $this->warehouse_id[$product_id] ?? (int)$cart_item->options->warehouse_id
            );
        } else {
            $discount_amount = ($cart_item->price + ($cart_item->options->product_discount ?? 0)) * (($this->item_discount[$product_id] ?? 0) / 100);

            Cart::instance($this->cart_instance)->update($row_id, [
                'price' => ($cart_item->price + ($cart_item->options->product_discount ?? 0)) - $discount_amount,
            ]);

            $this->updateCartOptions(
                $row_id,
                $product_id,
                $cart_item,
                $discount_amount,
                $this->item_cost_konsyinasi[$product_id] ?? 0,
                $this->warehouse_id[$product_id] ?? (int)$cart_item->options->warehouse_id
            );
        }

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function calculate($product)
    {
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
            $sub_total = $price;
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

        return [
            'price' => $price,
            'unit_price' => $unit_price,
            'product_tax' => $product_tax,
            'product_cost' => $product_cost,
            'sub_total' => $sub_total
        ];
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount, $item_cost_konsyinasi, $warehouse_id)
    {
        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => $cart_item->options->stock,
                'unit'                  => $cart_item->options->unit,
                'product_tax'           => $cart_item->options->product_tax,
                'warehouse_id'          => (int)$warehouse_id,
                'product_cost'          => $item_cost_konsyinasi,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $discount_amount,
                'product_discount_type' => $this->discount_type[$product_id] ?? 'fixed',
            ]
        ]);
    }
}
