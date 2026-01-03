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
        'qualityTypeChanged' => 'qualityTypeChanged',
    ];

    public string $mode = 'stock'; // stock | quality
    public array $products = [];

    // khusus quality
    public ?int $qualityWarehouseId = null;
    public int $branchId = 0;

    // NEW: type dropdown di quality tab
    public string $qualityType = 'defect'; // defect|damaged|defect_to_good|damaged_to_good

    public function mount($adjustedProducts = null, $mode = 'stock')
    {
        $this->mode = (string) $mode;
        $this->products = [];

        $active = session('active_branch');
        $this->branchId = is_numeric($active) ? (int) $active : 0;

        // default quality type (kalau mode quality)
        if ($this->mode === 'quality') {
            $this->qualityType = 'defect';
        }

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
                    'stock_label' => 'GOOD',
                    'available_qty' => (int) ($row['good_qty'] ?? 0),
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

    public function qualityTypeChanged($payload)
    {
        $type = '';

        if (is_array($payload)) {
            $type = (string) ($payload['type'] ?? $payload['value'] ?? '');
        } else {
            $type = (string) $payload;
        }

        $type = strtolower(trim($type));
        if (!in_array($type, ['defect', 'damaged', 'defect_to_good', 'damaged_to_good'], true)) {
            $type = 'defect';
        }

        $this->qualityType = $type;

        // kalau sudah ada product yang kepilih, refresh angka stock dan validasi qty max
        if ($this->mode === 'quality' && !empty($this->products) && $this->qualityWarehouseId) {
            $row = $this->products[0];
            $productId = (int) ($row['id'] ?? 0);

            if ($productId > 0) {
                $info = $this->getAvailableInfoForProduct(
                    $this->branchId,
                    (int) $this->qualityWarehouseId,
                    $productId,
                    $this->qualityType
                );

                $this->products[0]['stock_label'] = $info['label'];
                $this->products[0]['available_qty'] = $info['available_qty'];

                // kalau qty user lebih besar dari max available, turunkan otomatis
                $currentQty = (int) ($this->products[0]['quantity'] ?? 0);
                if ($currentQty > (int) $info['available_qty']) {
                    $this->products[0]['quantity'] = (int) $info['available_qty'];
                }

                // kalau available 0, hapus product biar gak salah proses
                if ((int) $info['available_qty'] <= 0) {
                    $this->products = [];
                    session()->flash('message', 'Selected product has no available quantity for this type in selected warehouse.');
                }
            }
        }

        $this->dispatchQualitySummary();
    }

    public function productSelected($product)
    {
        $productId = is_array($product) ? (int)($product['id'] ?? 0) : (int)$product;
        if (!$productId) return;

        foreach ($this->products as $row) {
            if ((int)$row['id'] === $productId) {
                session()->flash('message', 'Product already added!');
                $this->dispatchQualitySummary();
                return;
            }
        }

        if ($this->mode === 'quality' && count($this->products) >= 1) {
            session()->flash('message', 'Quality Reclass currently supports only 1 product per submit. Please remove existing product first.');
            $this->dispatchQualitySummary();
            return;
        }

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

        $stockLabel = 'GOOD';
        $availableQty = 0;

        if ($this->mode === 'quality') {
            $info = $this->getAvailableInfoForProduct(
                $this->branchId,
                (int)$this->qualityWarehouseId,
                (int)$p->id,
                $this->qualityType
            );

            $stockLabel = $info['label'];
            $availableQty = (int) $info['available_qty'];

            if ($availableQty <= 0) {
                session()->flash('message', "This product has no {$stockLabel} quantity in selected warehouse.");
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

            // quality info
            'stock_label' => $stockLabel,
            'available_qty' => (int) $availableQty,
        ];

        $this->dispatchQualitySummary();
    }

    public function removeProduct($key)
    {
        unset($this->products[$key]);
        $this->products = array_values($this->products);

        $this->dispatchQualitySummary();
    }

    private function getAvailableInfoForProduct(int $branchId, int $warehouseId, int $productId, string $qualityType): array
    {
        $label = 'GOOD';
        $available = 0;

        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            return ['label' => $label, 'available_qty' => 0];
        }

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
        if ($good < 0) $good = 0;

        // mapping type -> source qty
        if (in_array($qualityType, ['defect', 'damaged'], true)) {
            // GOOD -> defect/damaged
            $label = 'GOOD';
            $available = (int) $good;
        } elseif ($qualityType === 'defect_to_good') {
            $label = 'DEFECT';
            $available = (int) $defect;
        } elseif ($qualityType === 'damaged_to_good') {
            $label = 'DAMAGED';
            $available = (int) $damaged;
        } else {
            $label = 'GOOD';
            $available = (int) $good;
        }

        if ($available < 0) $available = 0;

        return ['label' => $label, 'available_qty' => $available];
    }

    public function updatedProducts()
    {
        // clamp qty kalau mode quality
        if ($this->mode === 'quality' && !empty($this->products)) {
            $max = (int) ($this->products[0]['available_qty'] ?? 0);
            $qty = (int) ($this->products[0]['quantity'] ?? 0);

            if ($max > 0 && $qty > $max) {
                $this->products[0]['quantity'] = $max;
            }
            if ($qty < 1) {
                $this->products[0]['quantity'] = 1;
            }
        }

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
