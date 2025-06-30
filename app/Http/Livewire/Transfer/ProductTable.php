<?php

namespace Modules\Transfer\Http\Livewire;

use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{
    public $products = [];

    protected $listeners = ['productSelected'];

    public function productSelected($productId)
    {
        $product = Product::find($productId);

        if (!$product) return;

        foreach ($this->products as $item) {
            if ($item['id'] == $product->id) return;
        }

        $this->products[] = [
            'id' => $product->id,
            'name' => $product->product_name,
            'quantity' => 1,
        ];
    }

    public function removeProduct($index)
    {
        unset($this->products[$index]);
        $this->products = array_values($this->products);
    }

    public function updatedProducts()
    {
        // Real-time update handled here
    }

    public function render()
    {
        return view('transfer::livewire.product-table');
    }
}
