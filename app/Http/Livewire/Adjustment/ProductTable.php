<?php

namespace App\Http\Livewire\Adjustment;

use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{
    protected $listeners = ['productSelected'];

    public $products = [];

    public function mount($adjustedProducts = null)
    {
        $this->products = [];

        // Mode EDIT: adjustedProducts dari DB
        if (!empty($adjustedProducts)) {
            foreach ($adjustedProducts as $row) {

                $productId = isset($row['product_id']) ? (int)$row['product_id'] : null;
                if (!$productId) {
                    continue;
                }

                $p = Product::find($productId);
                if (!$p) {
                    continue;
                }

                $this->products[] = [
                    'id' => $p->id,
                    'product_name' => $p->product_name,
                    'product_code' => $p->product_code,
                    'product_quantity' => $p->product_quantity,
                    'product_unit' => $p->product_unit,

                    'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 1,
                    'type' => isset($row['type']) ? $row['type'] : 'add',
                    'note' => $row['note'] ?? null,
                ];
            }
        }
    }

    public function render()
    {
        return view('livewire.adjustment.product-table');
    }

    public function productSelected($product)
    {
        /**
         * Payload dari search-product bisa beda2.
         * Kita normalize ke $productId lalu ambil data Product dari DB biar pasti lengkap.
         */
        $productId = null;

        if (is_array($product) && isset($product['id'])) {
            $productId = (int)$product['id'];
        } elseif (is_numeric($product)) {
            $productId = (int)$product;
        } elseif (is_array($product) && isset($product['product']['id'])) {
            $productId = (int)$product['product']['id'];
        } elseif (is_array($product) && isset($product['product_id'])) {
            $productId = (int)$product['product_id'];
        }

        if (!$productId) {
            return session()->flash('message', 'Invalid product selected!');
        }

        // Prevent duplicate
        foreach ($this->products as $row) {
            if ((int)$row['id'] === $productId) {
                return session()->flash('message', 'Already exists in the product list!');
            }
        }

        $p = Product::find($productId);
        if (!$p) {
            return session()->flash('message', 'Product not found!');
        }

        $this->products[] = [
            'id' => $p->id,
            'product_name' => $p->product_name,
            'product_code' => $p->product_code,
            'product_quantity' => $p->product_quantity,
            'product_unit' => $p->product_unit,

            'quantity' => 1,
            'type' => 'add',
            'note' => null,
        ];
    }

    public function removeProduct($key)
    {
        unset($this->products[$key]);
        $this->products = array_values($this->products);
    }
}
