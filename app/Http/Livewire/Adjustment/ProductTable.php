<?php

namespace App\Http\Livewire\Adjustment;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductTable extends Component
{
    protected $listeners = [
        'productSelected' => 'productSelected',

        // quality
        'qualityWarehouseChanged' => 'qualityWarehouseChanged',
        'qualityTypeChanged' => 'qualityTypeChanged',

        // stock
        'stockWarehouseChanged' => 'stockWarehouseChanged',
    ];

    public string $mode = 'stock'; // stock | quality
    public array $products = [];

    // quality
    public ?int $qualityWarehouseId = null;
    public int $branchId = 0;
    public string $qualityType = 'defect'; // defect|damaged|defect_to_good|damaged_to_good

    // stock
    public ?int $stockWarehouseId = null;

    // racks options (by selected warehouse)
    public array $rackOptions = [];

    public function mount($adjustedProducts = null, $mode = 'stock')
    {
        $this->mode = (string) $mode;
        $this->products = [];

        $active = session('active_branch');
        $this->branchId = is_numeric($active) ? (int) $active : 0;

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

                    // rack
                    'rack_id' => isset($row['rack_id']) ? (int) $row['rack_id'] : null,

                    // quality info
                    'stock_label' => 'GOOD',
                    'available_qty' => (int) ($row['good_qty'] ?? 0),

                    // stock info
                    'stock_qty' => (int) ($row['stock_qty'] ?? 0),
                ];
            }
        }

        $this->dispatchQualitySummary();
    }

    public function render()
    {
        return view('livewire.adjustment.product-table');
    }

    // =========================
    // STOCK WAREHOUSE HANDLER
    // =========================
    public function stockWarehouseChanged($payload): void
    {
        $warehouseId = null;

        if (is_array($payload)) {
            $warehouseId = $payload['warehouseId'] ?? $payload['warehouse_id'] ?? $payload['id'] ?? null;
        } else {
            $warehouseId = $payload;
        }

        $warehouseId = is_numeric($warehouseId) ? (int) $warehouseId : null;
        $this->stockWarehouseId = ($warehouseId && $warehouseId > 0) ? $warehouseId : null;

        if ($this->mode === 'stock') {
            $this->products = [];
        }

        $this->rackOptions = $this->loadRacksForWarehouse($this->stockWarehouseId);
    }

    private function loadRacksForWarehouse(?int $warehouseId): array
    {
        if (!$warehouseId || $warehouseId <= 0) return [];

        $rows = DB::table('racks')
            ->where('warehouse_id', (int) $warehouseId)
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $opts = [];
        foreach ($rows as $r) {
            $label = trim((string) ($r->code ?? '')) . ' - ' . trim((string) ($r->name ?? ''));
            $opts[] = [
                'id' => (int) $r->id,
                'label' => trim($label, ' -'),
            ];
        }

        return $opts;
    }

    // =========================
    // QUALITY HANDLERS
    // =========================
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

        if ($this->mode === 'quality') {
            $this->products = [];
        }

        // ✅ IMPORTANT: load rack options juga untuk QUALITY
        $this->rackOptions = $this->loadRacksForWarehouse($this->qualityWarehouseId);

        $this->dispatchQualitySummary();
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

        if ($this->mode === 'quality' && !empty($this->products) && $this->qualityWarehouseId) {
            $productId = (int) ($this->products[0]['id'] ?? 0);
            $rackId    = (int) ($this->products[0]['rack_id'] ?? 0);

            if ($productId > 0) {
                $info = $this->getAvailableInfoForProduct(
                    $this->branchId,
                    (int) $this->qualityWarehouseId,
                    $rackId > 0 ? $rackId : null,
                    $productId,
                    $this->qualityType
                );

                $this->products[0]['stock_label'] = $info['label'];
                $this->products[0]['available_qty'] = (int) $info['available_qty'];

                $currentQty = (int) ($this->products[0]['quantity'] ?? 0);
                if ($currentQty > (int) $info['available_qty']) {
                    $this->products[0]['quantity'] = (int) $info['available_qty'];
                }

                if ((int) $info['available_qty'] <= 0) {
                    $this->products = [];
                    session()->flash('message', 'Selected product has no available quantity for this type in selected rack/warehouse.');
                }
            }
        }

        $this->dispatchQualitySummary();
    }

    // =========================
    // PRODUCT SELECTED
    // =========================
    public function productSelected($product)
    {
        $productId = is_array($product) ? (int) ($product['id'] ?? 0) : (int) $product;
        if (!$productId) return;

        foreach ($this->products as $row) {
            if ((int) $row['id'] === $productId) {
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

        if ($this->mode === 'stock') {
            if (!$this->stockWarehouseId) {
                session()->flash('message', 'Please select Warehouse first (Stock tab).');
                return;
            }
        }

        $p = Product::find($productId);
        if (!$p) {
            session()->flash('message', 'Product not found!');
            $this->dispatchQualitySummary();
            return;
        }

        // ✅ default rack = first option (for BOTH modes)
        $defaultRackId = !empty($this->rackOptions) ? (int) ($this->rackOptions[0]['id'] ?? 0) : 0;
        if ($defaultRackId <= 0) $defaultRackId = null;

        // =========================
        // STOCK mode (tetap)
        // =========================
        $stockQty = 0;
        if ($this->mode === 'stock') {
            $stockQty = (int) DB::table('stocks')
                ->where('branch_id', $this->branchId)
                ->where('warehouse_id', (int) $this->stockWarehouseId)
                ->where('product_id', (int) $p->id)
                ->sum('qty_available');
        }

        // =========================
        // QUALITY mode (FIX: pakai stock_racks)
        // =========================
        $stockLabel = 'GOOD';
        $availableQty = 0;

        if ($this->mode === 'quality') {
            $info = $this->getAvailableInfoForProduct(
                $this->branchId,
                (int) $this->qualityWarehouseId,
                $defaultRackId ? (int)$defaultRackId : null,
                (int) $p->id,
                $this->qualityType
            );

            $stockLabel = $info['label'];
            $availableQty = (int) $info['available_qty'];

            if ($availableQty <= 0) {
                session()->flash('message', "This product has no {$stockLabel} quantity in selected rack/warehouse.");
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

            'stock_qty' => $stockQty,

            'quantity' => 1,
            'type' => 'add',
            'note' => null,

            // ✅ rack now used in QUALITY too
            'rack_id' => $defaultRackId,

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

    private function getAvailableInfoForProduct(
        int $branchId,
        int $warehouseId,
        ?int $rackId,
        int $productId,
        string $qualityType
    ): array {
        // label yang ditampilkan di badge (GOOD/DEFECT/DAMAGED)
        $label = 'GOOD';

        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            return [
                'label' => $label,
                'available_qty' => 0,
                'total_available' => 0,
            ];
        }

        // Ambil stok per rack dari stock_racks (SUM karena bisa saja ada multiple row)
        $q = DB::table('stock_racks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId);

        if (!empty($rackId) && (int) $rackId > 0) {
            $q->where('rack_id', (int) $rackId);
        }

        $row = $q->selectRaw('
                COALESCE(SUM(qty_available), 0) as qty_available,
                COALESCE(SUM(qty_good), 0) as qty_good,
                COALESCE(SUM(qty_defect), 0) as qty_defect,
                COALESCE(SUM(qty_damaged), 0) as qty_damaged
            ')
            ->first();

        $qtyAvailable = (int) ($row->qty_available ?? 0);

        // ✅ total available = good + defect + damaged (sesuai request kamu)
        // (kalau qty_available sudah merepresentasikan itu, hasilnya tetap konsisten)
        $totalAvailable = (int) (($row->qty_good ?? 0) + ($row->qty_defect ?? 0) + ($row->qty_damaged ?? 0));
        if ($qtyAvailable > 0) {
            // pilih qty_available sebagai sumber utama jika memang sudah ter-maintain benar
            $totalAvailable = $qtyAvailable;
        }

        // ==========================================================
        // Mapping available qty berdasarkan action:
        // - defect / damaged          => sumbernya GOOD (yang akan dipindah)
        // - defect_to_good            => sumbernya DEFECT
        // - damaged_to_good           => sumbernya DAMAGED
        // ==========================================================
        $available = 0;

        if (in_array($qualityType, ['defect', 'damaged'], true)) {
            $label = 'GOOD';
            $available = (int) ($row->qty_good ?? 0);
        } elseif ($qualityType === 'defect_to_good') {
            $label = 'DEFECT';
            $available = (int) ($row->qty_defect ?? 0);
        } elseif ($qualityType === 'damaged_to_good') {
            $label = 'DAMAGED';
            $available = (int) ($row->qty_damaged ?? 0);
        } else {
            $label = 'GOOD';
            $available = (int) ($row->qty_good ?? 0);
        }

        if ($available < 0) $available = 0;
        if ($totalAvailable < 0) $totalAvailable = 0;

        return [
            'label' => $label,
            'available_qty' => $available,
            'total_available' => $totalAvailable, // kalau mau kamu tampilkan juga
        ];
    }

    public function updatedProducts($value = null, $name = null)
    {
        // STOCK mode ga perlu logic tambahan
        if ($this->mode !== 'quality') {
            return;
        }

        if (empty($this->products) || !$this->qualityWarehouseId) {
            $this->dispatchQualitySummary();
            return;
        }

        // kita hanya support 1 product untuk quality
        $productId = (int) ($this->products[0]['id'] ?? 0);
        $rackId    = (int) ($this->products[0]['rack_id'] ?? 0);

        if ($productId > 0) {
            // Recalc available setiap kali rack/qty berubah
            $info = $this->getAvailableInfoForProduct(
                $this->branchId,
                (int) $this->qualityWarehouseId,
                $rackId > 0 ? $rackId : null,
                $productId,
                $this->qualityType
            );

            $this->products[0]['stock_label'] = $info['label'];
            $this->products[0]['available_qty'] = (int) $info['available_qty'];

            $max = (int) ($this->products[0]['available_qty'] ?? 0);
            $qty = (int) ($this->products[0]['quantity'] ?? 0);

            if ($max > 0 && $qty > $max) {
                $this->products[0]['quantity'] = $max;
            }
            if ($qty < 1) {
                $this->products[0]['quantity'] = 1;
            }

            if ($max <= 0) {
                $this->products = [];
                session()->flash('message', 'Selected product has no available quantity for this type in selected rack/warehouse.');
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
            $name = trim((string) ($row['product_name'] ?? ''));
            $code = trim((string) ($row['product_code'] ?? ''));
            $productText = trim($name . ' | ' . $code);
        }

        $this->dispatchBrowserEvent('quality-table-updated', [
            'product_id' => $productId,
            'qty' => $qty,
            'product_text' => $productText,
        ]);
    }
}
