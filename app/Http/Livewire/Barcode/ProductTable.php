<?php

namespace App\Http\Livewire\Barcode;

use App\Support\BranchContext;
use Livewire\Component;
use Modules\Product\Entities\Product;
use Modules\Product\Support\LabelPayloadFactory;

class ProductTable extends Component
{
    public $product;
    public $quantity;
    public $labels;

    protected $listeners = ['productSelected'];

    public function mount()
    {
        $this->product = '';
        $this->quantity = 0;
        $this->labels = [];
    }

    public function render()
    {
        return view('livewire.barcode.product-table');
    }

    public function productSelected(Product $product)
    {
        $this->product = $product;
        $this->quantity = 1;
        $this->labels = [];
    }

    public function generateBarcodes($productId, $quantity)
    {
        if ($quantity > 100) {
            return session()->flash('message', 'Max quantity is 100 per barcode generation!');
        }

        $product = Product::withoutGlobalScopes()->findOrFail((int) $productId);

        $this->labels = app(LabelPayloadFactory::class)
            ->buildProductLabels($product, (int) $quantity, BranchContext::id());
    }

    public function getPdf()
    {
        $pdf = \PDF::loadView('product::barcode.print', [
            'labels' => $this->labels,
            'documentTitle' => 'GOOD Product Labels',
        ]);

        return $pdf->stream('barcodes-' . $this->product->product_code . '.pdf');
    }

    public function updatedQuantity()
    {
        $this->labels = [];
    }
}
