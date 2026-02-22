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

    // from SearchProduct
    // each: product_id, product_code, product_name, expected_qty
    public array $products = [];

    // selections per row:
    // selections[idx] = ['unit_ids' => [id...]]
    public array $selections = [];

    public array $warehouseOptions = [];

    // 'defect' | 'damaged'
    public string $condition = 'defect';

    public string $qualityToGoodPickerUrl = '';

    public function mount(): void
    {
        $this->branchId = (int) session('active_branch');

        // kalau route picker belum ada, tetap kasih string kosong biar view gak error
        // (modalnya aja yang nanti gak bisa load)
        $this->qualityToGoodPickerUrl = function_exists('route')
            ? (string) route('adjustments.quality.to_good.picker')
            : '';

        $this->hydrateWarehouseOptions();
    }

    protected function hydrateWarehouseOptions(): void
    {
        $rows = Warehouse::query()
            ->where('branch_id', $this->branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get(['id', 'warehouse_name', 'is_main']);

        $this->warehouseOptions = $rows->map(function ($w) {
            return [
                'id' => (int) $w->id,
                'label' => $w->warehouse_name . ((int) $w->is_main === 1 ? ' (Main)' : ''),
            ];
        })->values()->all();
    }

    public function productSelected(array $payload): void
    {
        // payload standar dari SearchProduct biasanya: id, code, name
        $productId = (int) ($payload['id'] ?? $payload['product_id'] ?? 0);
        if ($productId <= 0) return;

        // jangan duplicate
        foreach ($this->products as $p) {
            if ((int) ($p['product_id'] ?? 0) === $productId) {
                return;
            }
        }

        $this->products[] = [
            'product_id' => $productId,
            'product_code' => (string) ($payload['code'] ?? $payload['product_code'] ?? ''),
            'product_name' => (string) ($payload['name'] ?? $payload['product_name'] ?? ''),
            'expected_qty' => 1,
        ];

        $idx = count($this->products) - 1;
        $this->selections[$idx] = ['unit_ids' => []];
    }

    public function qualityToGoodTypeChanged(string $type): void
    {
        // type dari select: defect_to_good / damaged_to_good
        $this->condition = ($type === 'damaged_to_good') ? 'damaged' : 'defect';
    }

    public function qualityToGoodSelectionSaved(int $rowIndex, array $selection): void
    {
        if (!isset($this->products[$rowIndex])) return;

        $unitIds = $selection['unit_ids'] ?? [];
        if (!is_array($unitIds)) $unitIds = [];

        $this->selections[$rowIndex] = [
            'unit_ids' => array_values(array_unique(array_map('intval', $unitIds))),
        ];
    }

    public function render()
    {
        return view('livewire.adjustment.product-table-quality-to-good');
    }
}