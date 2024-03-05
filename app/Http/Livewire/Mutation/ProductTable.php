<?php

namespace App\Http\Livewire\Mutation;

use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{

    protected $listeners = ['productSelected'];

    public $products;
    public $hadMutations;

    public function mount($adjustedProducts = null) {
        $this->products = [];

        if ($adjustedProducts) {
            $this->hadMutations = true;
            $this->products = $adjustedProducts;
        } else {
            $this->hadMutations = false;
        }
    }

    public function render() {
        return view('livewire.mutation.product-table');
    }

    public function productSelected($product) {
        switch ($this->hadMutations) {
            case true:
                if (in_array($product, array_map(function ($mutation) {
                    return $mutation['product'];
                }, $this->products))) {
                    return session()->flash('message', 'Already exists in the product list!');
                }
                break;
            case false:
                if (in_array($product, $this->products)) {
                    return session()->flash('message', 'Already exists in the product list!');
                }
                break;
            default:
                return session()->flash('message', 'Something went wrong!');
        }

        array_push($this->products, $product);
    }

    public function removeProduct($key) {
        unset($this->products[$key]);
    }
}
