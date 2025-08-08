<?php

namespace App\Http\Livewire\Transfer;

use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{

    protected $listeners = ['productSelected'];

    public $products;
    public $hadTransfers;

    public function mount($transferProducts = null) {
        $this->products = [];

        if ($transferProducts) {
            $this->hadTransfers = true;
            $this->products = $transferProducts;
        } else {
            $this->hadTransfers = false;
        }
    }

    public function render() {
        return view('livewire.transfer.product-table');
    }

    public function productSelected($product) {
        switch ($this->hadTransfers) {
            case true:
                if (in_array($product, array_map(function ($transfer) {
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
