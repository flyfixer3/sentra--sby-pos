<?php

namespace App\Http\Livewire;

use App\Support\ProductAvailability;
use App\Support\ProductSearch;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;

class SearchProduct extends Component
{
    public bool $requireWarehouse = false;
    public bool $warehouseSelected = true;
    public ?string $selectionContext = null;
    public ?int $warehouseId = null;

    protected $listeners = [
        /*
         * Transfer emits fromWarehouseSelected to tell the UI that a source
         * warehouse has been selected. For Transfer autocomplete labels, we
         * intentionally do NOT scope availability by this warehouse.
         *
         * Reason:
         * Inventory Stock and Purchase product search show branch-wide available
         * stock. Transfer previously showed a warehouse-scoped number such as
         * 26 PC avail for FDB-FJ40ASH, while Inventory Stock correctly showed
         * 6 PC avail for the active branch.
         *
         * The Transfer ProductTable component still listens to fromWarehouseSelected
         * separately and uses it for real transfer stock/rack validation.
         */
        'fromWarehouseSelected' => 'onSourceWarehouseSelectedForRequirement',

        /*
         * These events are allowed to set a real warehouse scope for modules
         * that intentionally need warehouse-aware product availability labels.
         */
        'rackMoveFromWarehouseSelected' => 'onWarehouseSelected',
        'purchaseWarehouseChanged' => 'onWarehouseSelected',

        'enableWarehouseRequirement' => 'enableWarehouseRequirement',
        'productSelectionContextChanged' => 'productSelectionContextChanged',
    ];

    public $query;
    public $search_results;
    public $how_many;

    public function mount($warehouseId = null)
    {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
        $this->warehouseId = !empty($warehouseId) ? (int) $warehouseId : null;
    }

    public function render()
    {
        return view('livewire.search-product');
    }

    public function enableWarehouseRequirement()
    {
        $this->requireWarehouse = true;
        $this->warehouseSelected = false;
    }

    /**
     * Used by Transfer source warehouse selection.
     *
     * This method only unlocks/enables the search input when a source warehouse
     * is selected. It must not set $this->warehouseId, because the visible
     * autocomplete label must follow Inventory Stock branch-wide availability.
     */
    public function onSourceWarehouseSelectedForRequirement($payload): void
    {
        $selectedWarehouseId = $this->extractWarehouseId($payload);

        if ($this->requireWarehouse) {
            $this->warehouseSelected = !empty($selectedWarehouseId);
        }

        /*
         * Important:
         * Keep Transfer autocomplete availability branch-wide.
         * Do not assign $selectedWarehouseId into $this->warehouseId here.
         */
        $this->warehouseId = null;

        if (!empty($this->query)) {
            $this->updatedQuery();
        }
    }

    /**
     * Used only by modules that intentionally want product availability labels
     * scoped to a specific warehouse.
     */
    public function onWarehouseSelected($payload): void
    {
        $warehouseId = $this->extractWarehouseId($payload);

        $this->warehouseId = !empty($warehouseId) ? (int) $warehouseId : null;

        if ($this->requireWarehouse) {
            $this->warehouseSelected = !empty($this->warehouseId);
        }

        if (!empty($this->query)) {
            $this->updatedQuery();
        }
    }

    public function productSelectionContextChanged($context): void
    {
        $context = is_string($context) ? trim($context) : null;
        $this->selectionContext = $context !== '' ? $context : null;
    }

    public function updatedQuery()
    {
        $branchId = session('active_branch');

        $query = Product::withoutGlobalScopes()
            ->tap(function ($q) {
                ProductSearch::applyTokenSearch($q, $this->query, ['product_name', 'product_code'], 'id');
            });

        /*
         * Product master is global, but still allow old branch-specific products
         * so existing transaction flows do not lose branch-specific items.
         */
        if ($branchId !== 'all' && is_numeric($branchId)) {
            $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $branchId);
            });
        }

        $products = $query
            ->orderBy('product_code')
            ->take($this->how_many)
            ->get();

        $this->search_results = ProductAvailability::applyLabels(
            $products,
            ProductAvailability::activeBranchId(),
            $this->warehouseId
        );
    }

    public function loadMore()
    {
        $this->how_many += 5;
        $this->updatedQuery();
    }

    public function resetQuery()
    {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function selectProduct($product)
    {
        if ($this->selectionContext) {
            $payload = is_array($product) ? $product : $product->toArray();
            $payload['__selection_context'] = $this->selectionContext;
            $this->emit('productSelected', $payload);
            return;
        }

        $this->emit('productSelected', $product);
    }

    private function extractWarehouseId($payload): ?int
    {
        $warehouseId = null;

        if (is_array($payload)) {
            $warehouseId = $payload['warehouseId'] ?? $payload['value'] ?? null;
        } else {
            $warehouseId = $payload;
        }

        return !empty($warehouseId) ? (int) $warehouseId : null;
    }
}