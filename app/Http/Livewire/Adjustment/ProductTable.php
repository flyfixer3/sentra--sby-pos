<?php

namespace App\Http\Livewire\Adjustment;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{
    protected $listeners = [
        'productSelected' => 'productSelected',
        'qualityWarehouseChanged' => 'qualityWarehouseChanged',
    ];

    public string $mode = 'stock'; // stock | quality
    public array $products = [];

    // khusus quality
    public ?int $qualityWarehouseId = null;
    public int $branchId = 0;

    public function mount($adjustedProducts = null, $mode = 'stock')
    {
        $this->mode = (string) $mode;
        $this->products = [];

        $active = session('active_branch');
        $this->branchId = is_numeric($active) ? (int) $active : 0;

        if (!empty($adjustedProducts)) {
            foreach ($adjustedProducts as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if (!$productId) continue;

                $p = Product::find($productId);
                if (!$p) continue;

                $this->products[] = [
                    'id' => $p->id,
                    'product_name' => $p->product_name,
                    'product_code' => $p->product_code,
                    'product_quantity' => $p->product_quantity,
                    'product_unit' => $p->product_unit,

                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'type' => $row['type'] ?? 'add',
                    'note' => $row['note'] ?? null,

                    // quality info
                    'good_qty' => (int) ($row['good_qty'] ?? 0),
                ];
            }
        }

        $this->dispatchQualitySummary();
    }

    public function render()
    {
        return view('livewire.adjustment.product-table');
    }

    public function qualityWarehouseChanged($payload)
    {
        // payload bisa:
        // - array: ['warehouseId' => '3']
        // - array: ['warehouse_id' => '3']
        // - string/int langsung: '3'
        $warehouseId = null;

        if (is_array($payload)) {
            $warehouseId =
                $payload['warehouseId'] ??
                $payload['warehouse_id'] ??
                $payload['id'] ??
                null;
        } else {
            $warehouseId = $payload;
        }

        $warehouseId = is_numeric($warehouseId) ? (int) $warehouseId : null;
        $this->qualityWarehouseId = ($warehouseId && $warehouseId > 0) ? $warehouseId : null;

        // Saat warehouse berubah, kosongkan list quality biar gak salah stok.
        if ($this->mode === 'quality') {
            $this->products = [];
            $this->dispatchQualitySummary();
        }
    }

    public function productSelected($product)
    {
        $productId = is_array($product) ? (int)($product['id'] ?? 0) : (int)$product;
        if (!$productId) return;

        // prevent duplicate
        foreach ($this->products as $row) {
            if ((int)$row['id'] === $productId) {
                session()->flash('message', 'Product already added!');
                $this->dispatchQualitySummary();
                return;
            }
        }

        // QUALITY RULE: backend storeQuality hanya support 1 product
        if ($this->mode === 'quality' && count($this->products) >= 1) {
            session()->flash('message', 'Quality Reclass currently supports only 1 product per submit. Please remove existing product first.');
            $this->dispatchQualitySummary();
            return;
        }

        // QUALITY RULE: warehouse harus dipilih dulu
        if ($this->mode === 'quality') {
            if (!$this->qualityWarehouseId) {
                session()->flash('message', 'Please select Warehouse first (Quality tab).');
                $this->dispatchQualitySummary();
                return;
            }
        }

        $p = Product::find($productId);
        if (!$p) {
            session()->flash('message', 'Product not found!');
            $this->dispatchQualitySummary();
            return;
        }

        $goodQty = 0;
        if ($this->mode === 'quality') {
            $goodQty = $this->getGoodQtyForProduct($this->branchId, (int)$this->qualityWarehouseId, (int)$p->id);
            if ($goodQty <= 0) {
                session()->flash('message', 'This product has no GOOD stock in selected warehouse.');
                $this->dispatchQualitySummary();
                return;
            }
        }

        $this->products[] = [
            'id' => $p->id,
            'product_name' => $p->product_name,
            'product_code' => $p->product_code,
            'product_quantity' => $p->product_quantity,
            'product_unit' => $p->product_unit,

            'quantity' => 1,
            'type' => 'add',
            'note' => null,

            'good_qty' => (int) $goodQty,
        ];

        $this->dispatchQualitySummary();
    }

    public function removeProduct($key)
    {
        unset($this->products[$key]);
        $this->products = array_values($this->products);

        $this->dispatchQualitySummary();
    }

    private function getGoodQtyForProduct(int $branchId, int $warehouseId, int $productId): int
    {
        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) return 0;

        $total = (int) DB::table('stocks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->sum('qty_available');

        $defect = (int) DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->sum('quantity');

        $damaged = (int) DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->sum('quantity');

        $good = $total - $defect - $damaged;
        return $good > 0 ? $good : 0;
    }

    public function updatedProducts()
    {
        $this->dispatchQualitySummary();
    }

    private function dispatchQualitySummary(): void
    {
        if ($this->mode !== 'quality') return;

        $productId = '';
        $qty = 0;
        $productText = 'No product selected';

        if (!empty($this->products)) {
            $row = $this->products[0];
            $productId = (string) ($row['id'] ?? '');
            $qty = (int) ($row['quantity'] ?? 0);
            $name = trim((string)($row['product_name'] ?? ''));
            $code = trim((string)($row['product_code'] ?? ''));
            $productText = trim($name . ' | ' . $code);
        }

        $this->dispatchBrowserEvent('quality-table-updated', [
            'product_id' => $productId,
            'qty' => $qty,
            'product_text' => $productText,
        ]);
    }
}
