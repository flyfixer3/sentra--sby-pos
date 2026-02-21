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

    /**
     * mode:
     * - stock_add  (UI receive details)
     * - quality
     */
    public string $mode = 'stock_add';
    public array $products = [];

    // quality
    public ?int $qualityWarehouseId = null;
    public int $branchId = 0;
    public string $qualityType = 'defect'; // defect|damaged|defect_to_good|damaged_to_good

    // stock
    public ?int $stockWarehouseId = null;

    // racks options (QUALITY dropdown)
    public array $rackOptions = [];

    public function mount($adjustedProducts = null, $mode = 'stock_add', $warehouseId = null)
    {
        $this->mode = (string) $mode;
        $this->products = [];

        $active = session('active_branch');
        $this->branchId = is_numeric($active) ? (int) $active : 0;

        // Set default type for quality
        if ($this->mode === 'quality') {
            $this->qualityType = 'defect';
        }

        // ✅ NEW: set initial warehouse id (passed from blade)
        $warehouseId = is_numeric($warehouseId) ? (int) $warehouseId : null;

        if ($this->mode === 'stock_add') {
            $this->stockWarehouseId = ($warehouseId && $warehouseId > 0) ? $warehouseId : null;
        }

        if ($this->mode === 'quality') {
            $this->qualityWarehouseId = ($warehouseId && $warehouseId > 0) ? $warehouseId : null;

            // preload racks for quality dropdown
            $this->rackOptions = $this->loadRacksForWarehouse($this->qualityWarehouseId);
        }

        // edit mode (optional)
        if (!empty($adjustedProducts) && is_iterable($adjustedProducts)) {
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

                    // stock_add defaults
                    'qty_good' => (int) ($row['qty_good'] ?? 0),
                    'qty_defect' => (int) ($row['qty_defect'] ?? 0),
                    'qty_damaged' => (int) ($row['qty_damaged'] ?? 0),
                    'good_allocations' => (array) ($row['good_allocations'] ?? []),
                    'defects' => (array) ($row['defects'] ?? []),
                    'damaged_items' => (array) ($row['damaged_items'] ?? []),

                    // quality defaults
                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'rack_id' => isset($row['rack_id']) ? (int) $row['rack_id'] : null,
                    'stock_label' => (string) ($row['stock_label'] ?? 'GOOD'),
                    'available_qty' => (int) ($row['available_qty'] ?? 0),

                    // display stock
                    'stock_qty' => (int) ($row['stock_qty'] ?? 0),
                ];
            }
        }

        $this->dispatchQualitySummary();
    }

    public function render()
    {
        if ($this->mode === 'stock_add') {
            return view('livewire.adjustment.product-table-stock');
        }

        // default: quality
        return view('livewire.adjustment.product-table-quality');
    }

    // =========================
    // STOCK WAREHOUSE HANDLER (MODE GUARDED)
    // =========================
    public function stockWarehouseChanged($payload): void
    {
        // ✅ IMPORTANT: hanya stock_add yang boleh respon event stock
        if ($this->mode !== 'stock_add') return;

        $warehouseId = null;

        if (is_array($payload)) {
            $warehouseId = $payload['warehouseId'] ?? $payload['warehouse_id'] ?? $payload['id'] ?? null;
        } else {
            $warehouseId = $payload;
        }

        $warehouseId = is_numeric($warehouseId) ? (int) $warehouseId : null;
        $this->stockWarehouseId = ($warehouseId && $warehouseId > 0) ? $warehouseId : null;

        // stock_add: ganti WH = reset list (sesuai behavior kamu sebelumnya)
        $this->products = [];
    }

    // =========================
    // QUALITY HANDLERS (MODE GUARDED)
    // =========================
    public function qualityWarehouseChanged($payload)
    {
        // ✅ IMPORTANT: hanya quality yang boleh respon event quality
        if ($this->mode !== 'quality') return;

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

        $this->products = [];

        // ✅ rack options untuk dropdown QUALITY
        $this->rackOptions = $this->loadRacksForWarehouse($this->qualityWarehouseId);

        $this->dispatchQualitySummary();
    }

    public function qualityTypeChanged($payload)
    {
        if ($this->mode !== 'quality') return;

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

        // recalc jika sudah ada product
        if (!empty($this->products) && $this->qualityWarehouseId) {
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

        if ($this->mode === 'stock_add') {
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

        // =========================
        // STOCK qty display (FIX: use stock_racks)
        // =========================
        $stockQty = 0;
        if ($this->mode === 'stock_add') {
            $row = DB::table('stock_racks')
                ->where('branch_id', $this->branchId)
                ->where('warehouse_id', (int) $this->stockWarehouseId)
                ->where('product_id', (int) $p->id)
                ->selectRaw('
                    COALESCE(SUM(qty_available), 0) as qty_available,
                    COALESCE(SUM(qty_good), 0) as qty_good,
                    COALESCE(SUM(qty_defect), 0) as qty_defect,
                    COALESCE(SUM(qty_damaged), 0) as qty_damaged
                ')
                ->first();

            $qtyAvailable = (int) ($row->qty_available ?? 0);
            $fallbackTotal = (int) (($row->qty_good ?? 0) + ($row->qty_defect ?? 0) + ($row->qty_damaged ?? 0));

            $stockQty = $qtyAvailable > 0 ? $qtyAvailable : $fallbackTotal;
        }

        // =========================
        // QUALITY mode info
        // =========================
        $stockLabel = 'GOOD';
        $availableQty = 0;

        if ($this->mode === 'quality') {
            // load rack options once if not loaded
            if (empty($this->rackOptions) && $this->qualityWarehouseId) {
                $this->rackOptions = $this->loadRacksForWarehouse($this->qualityWarehouseId);
            }

            $defaultRackId = !empty($this->rackOptions) ? (int) ($this->rackOptions[0]['id'] ?? 0) : 0;
            if ($defaultRackId <= 0) $defaultRackId = null;

            $info = $this->getAvailableInfoForProduct(
                $this->branchId,
                (int) $this->qualityWarehouseId,
                $defaultRackId ? (int) $defaultRackId : null,
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

            $this->products[] = [
                'id' => $p->id,
                'product_name' => $p->product_name,
                'product_code' => $p->product_code,
                'product_quantity' => $p->product_quantity,
                'product_unit' => $p->product_unit,

                // quality
                'quantity' => 1,
                'rack_id' => $defaultRackId,
                'stock_label' => $stockLabel,
                'available_qty' => $availableQty,

                // display stock
                'stock_qty' => 0,
            ];

            $this->dispatchQualitySummary();
            return;
        }

        // =========================
        // STOCK_ADD row init (fields must match blade)
        // =========================
        if ($this->mode === 'stock_add') {
            $this->products[] = [
                'id' => $p->id,
                'product_name' => $p->product_name,
                'product_code' => $p->product_code,
                'product_quantity' => $p->product_quantity,
                'product_unit' => $p->product_unit,

                'stock_qty' => $stockQty,

                // UI expects these fields
                'qty_good' => 0,
                'qty_defect' => 0,
                'qty_damaged' => 0,
                'good_allocations' => [],
                'defects' => [],
                'damaged_items' => [],
            ];
        }
    }

    public function removeProduct($key)
    {
        unset($this->products[$key]);
        $this->products = array_values($this->products);

        $this->dispatchQualitySummary();
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

    private function getAvailableInfoForProduct(
        int $branchId,
        int $warehouseId,
        ?int $rackId,
        int $productId,
        string $qualityType
    ): array {
        $label = 'GOOD';

        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            return [
                'label' => $label,
                'available_qty' => 0,
                'total_available' => 0,
            ];
        }

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
        $totalAvailable = (int) (($row->qty_good ?? 0) + ($row->qty_defect ?? 0) + ($row->qty_damaged ?? 0));
        if ($qtyAvailable > 0) {
            $totalAvailable = $qtyAvailable;
        }

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
            'total_available' => $totalAvailable,
        ];
    }

    public function updatedProducts($value = null, $name = null)
    {
        if ($this->mode !== 'quality') return;

        if (empty($this->products) || !$this->qualityWarehouseId) {
            $this->dispatchQualitySummary();
            return;
        }

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
