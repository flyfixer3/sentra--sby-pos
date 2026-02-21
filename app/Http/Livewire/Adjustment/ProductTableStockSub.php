<?php

namespace App\Http\Livewire\Adjustment;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\Warehouse;

class ProductTableStockSub extends Component
{
    protected $listeners = [
        'productSelected' => 'productSelected',
        'subSelectionSaved' => 'subSelectionSaved',
    ];

    public int $branchId = 0;

    // products selected from SearchProduct
    // each: product_id, product_code, product_name, expected_qty (default 1)
    public array $products = [];

    // selections per row index:
    // selections[idx] = [
    //   good_allocations => [[warehouse_id, warehouse_label, from_rack_id, rack_label, qty]],
    //   defect_ids => [id...],
    //   damaged_ids => [id...],
    //   note => '',
    // ]
    public array $selections = [];

    // warehouses dropdown options
    public array $warehouseOptions = [];

    // url endpoint for picker data
    public string $stockSubPickerUrl = '';

    public function mount()
    {
        $active = session('active_branch');
        $this->branchId = (int) ($active === 'all' ? 0 : $active);

        // endpoint (ubah kalau route name beda)
        // ideal: route('adjustments.stockSubPickerData')
        $this->stockSubPickerUrl = route('adjustments.stock_sub.picker_data');

        // warehouses in active branch
        if ($this->branchId > 0) {
            $warehouses = Warehouse::query()
                ->where('branch_id', $this->branchId)
                ->orderByDesc('is_main')
                ->orderBy('warehouse_name')
                ->get(['id', 'warehouse_name', 'is_main']);

            $this->warehouseOptions = $warehouses->map(function ($w) {
                return [
                    'id' => (int) $w->id,
                    'label' => $w->warehouse_name . ((int)$w->is_main === 1 ? ' (Main)' : ''),
                ];
            })->values()->toArray();
        }
    }

    /**
     * Called when SearchProduct emits productSelected
     * payload example:
     *  - ['id'=>1,'product_code'=>'XXX','product_name'=>'ABC']
     */
    public function productSelected($payload)
    {
        $productId = (int) ($payload['id'] ?? $payload['product_id'] ?? 0);
        if ($productId <= 0) return;

        // prevent duplicates
        foreach ($this->products as $p) {
            if ((int)($p['product_id'] ?? 0) === $productId) return;
        }

        $this->products[] = [
            'product_id'   => $productId,
            'product_code' => (string) ($payload['product_code'] ?? ''),
            'product_name' => (string) ($payload['product_name'] ?? $payload['name'] ?? 'Product'),
            'expected_qty' => 1, // default
        ];

        $idx = count($this->products) - 1;
        $this->selections[$idx] = [
            'good_allocations' => [],
            'defect_ids' => [],
            'damaged_ids' => [],
            'note' => '',
        ];
    }

    public function subSelectionSaved($rowIndex, $data)
    {
        $rowIndex = (int) $rowIndex;
        if (!isset($this->selections[$rowIndex])) return;

        $goodAlloc = is_array($data['good_allocations'] ?? null) ? $data['good_allocations'] : [];
        $defIds    = is_array($data['defect_ids'] ?? null) ? $data['defect_ids'] : [];
        $damIds    = is_array($data['damaged_ids'] ?? null) ? $data['damaged_ids'] : [];

        // normalize
        $defIds = array_values(array_unique(array_map('intval', $defIds)));
        $damIds = array_values(array_unique(array_map('intval', $damIds)));

        $this->selections[$rowIndex]['good_allocations'] = $goodAlloc;
        $this->selections[$rowIndex]['defect_ids'] = $defIds;
        $this->selections[$rowIndex]['damaged_ids'] = $damIds;
    }

    public function render()
    {
        return view('livewire.adjustment.product-table-stock-sub');
    }
}