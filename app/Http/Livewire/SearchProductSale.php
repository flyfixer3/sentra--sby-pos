<?php

namespace App\Http\Livewire;

use App\Support\ProductSearch;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Mutation\Entities\Mutation;
use Illuminate\Support\Facades\DB;

class SearchProductSale extends Component
{

    public $query;
    public $search_results;
    public $how_many;

    public function mount() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function render() {
        return view('livewire.search-product-sale');
    }

    public function updatedQuery() {
        $branchId = session('active_branch');

        $query = Mutation::withoutGlobalScopes()
            ->with([
                'product' => function ($q) {
                    $q->withoutGlobalScopes();
                },
                'warehouse' => function ($q) {
                    $q->withoutGlobalScopes();
                },
            ])
            ->whereHas('product', function ($q) use ($branchId) {
                $q->withoutGlobalScopes()
                    ->tap(function ($inner) {
                        ProductSearch::applyTokenSearch($inner, $this->query, ['product_name', 'product_code'], 'id');
                    });

                if ($branchId !== 'all' && is_numeric($branchId)) {
                    $q->where(function ($branchScoped) use ($branchId) {
                        $branchScoped->whereNull('branch_id')
                            ->orWhere('branch_id', (int) $branchId);
                    });
                }
            });

        if ($branchId !== 'all' && is_numeric($branchId)) {
            $warehouseIds = Warehouse::withoutGlobalScopes()
                ->where('branch_id', (int) $branchId)
                ->pluck('id');

            $query->whereIn('warehouse_id', $warehouseIds);
        }

        $this->search_results = $query
            ->latest('date')
            ->get()
            ->filter(function ($mutation) {
                return !empty($mutation->product) && !empty($mutation->warehouse);
            })
            ->unique(function ($mutation) {
                return $mutation->product_id . '-' . $mutation->warehouse_id;
            })
            ->take($this->how_many)
            ->values();
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
