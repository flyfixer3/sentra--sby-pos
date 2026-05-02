<?php

namespace App\Http\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;

class SearchProduct extends Component
{

    public bool $requireWarehouse = false;
    public bool $warehouseSelected = true;

    protected $listeners = [
        'fromWarehouseSelected' => 'onWarehouseSelected',
        'enableWarehouseRequirement' => 'enableWarehouseRequirement',
    ];

    public $query;
    public $search_results;
    public $how_many;

    public function enableWarehouseRequirement()
    {
        $this->requireWarehouse = true;
        $this->warehouseSelected = false;
    }

    public function onWarehouseSelected($payload)
    {
        if (!$this->requireWarehouse) return;

        if (is_array($payload)) {
            $payload = $payload['warehouseId'] ?? null;
        }

        $this->warehouseSelected = !empty($payload);
    }



    public function mount() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function render() {
        return view('livewire.search-product');
    }

    public function updatedQuery() {
        $branchId = session('active_branch');

        $query = Product::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('product_name', 'like', '%' . $this->query . '%')
                    ->orWhere('product_code', 'like', '%' . $this->query . '%');
            });

        // Product master bersifat global, tapi tetap izinkan produk cabang lama
        // agar flow transaksi lama tidak kehilangan item branch-specific.
        if ($branchId !== 'all' && is_numeric($branchId)) {
            $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $branchId);
            });
        }

        $this->search_results = $query
            ->orderBy('product_code')
            ->take($this->how_many)
            ->get();
    }

    public function loadMore() {
        $this->how_many += 5;
        $this->updatedQuery();
    }

    public function resetQuery() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function selectProduct($product) {
        $this->emit('productSelected', $product);
    }
}
