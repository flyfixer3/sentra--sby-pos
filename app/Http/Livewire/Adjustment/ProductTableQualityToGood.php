<?php

namespace App\Http\Livewire\Adjustment;

use Livewire\Component;
use Modules\Product\Entities\Warehouse;

class ProductTableQualityToGood extends Component
{
    protected $listeners = [
        'productSelected' => 'productSelected',
        'qualityToGoodSelectionSaved' => 'qualityToGoodSelectionSaved',
        'qualityToGoodTypeChanged' => 'qualityToGoodTypeChanged',
    ];

    public int $branchId = 0;

    // products selected from SearchProduct
    // each: product_id, product_code, product_name, expected_qty (default 1)
    public array $products = [];

    // selections per row index:
    // selections[idx] = ['unit_ids' => [id...]]
    public array $selections = [];

    // warehouses dropdown options (for modal filter)
    public array $warehouseOptions = [];

    // url endpoint for picker data
    public string $qualityToGoodPickerUrl = '';

    // 'defect' | 'damaged'
    public string $condition = 'defect';

    public function mount(): void
    {
        $active = session('active_branch');
        $this->branchId = (int) ($active === 'all' ? 0 : $active);

        // ✅ route harus ada (yang kemarin error)
        $this->qualityToGoodPickerUrl = route('adjustments.quality.to_good.picker');

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

    public function qualityToGoodTypeChanged(string $type): void
    {
        // type dari JS: defect_to_good / damaged_to_good
        $this->condition = ($type === 'damaged_to_good') ? 'damaged' : 'defect';
    }

    public function productSelected($payload): void
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
            'expected_qty' => 1,
        ];

        $idx = count($this->products) - 1;
        $this->selections[$idx] = [
            'unit_ids' => [],
        ];
    }

    public function qualityToGoodSelectionSaved(int $rowIndex, array $payload): void
    {
        if (!isset($this->selections[$rowIndex])) {
            $this->selections[$rowIndex] = ['unit_ids' => []];
        }

        $ids = $payload['unit_ids'] ?? [];
        if (!is_array($ids)) $ids = [];

        // normalize int unique
        $clean = [];
        foreach ($ids as $x) {
            $v = (int) $x;
            if ($v > 0) $clean[$v] = true;
        }

        $this->selections[$rowIndex]['unit_ids'] = array_keys($clean);

        // optional: biar UI bisa re-check status
        $this->dispatchBrowserEvent('qtg-selection-updated', ['rowIndex' => $rowIndex]);
    }

    public function render()
    {
        return view('livewire.adjustment.product-table-quality-to-good');
    }

    public function removeProduct(int $idx): void
    {
        // guard index
        if (!isset($this->products[$idx])) {
            return;
        }

        // buang product & selections index tsb
        unset($this->products[$idx]);
        unset($this->selections[$idx]);

        // reindex array biar loop blade tetap rapi (0..n)
        $this->products = array_values($this->products);
        $this->selections = array_values($this->selections);

        // optional: emit supaya JS re-validate setelah row hilang
        $this->dispatchBrowserEvent('qtg-row-removed');
    }
}