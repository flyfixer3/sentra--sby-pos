<?php

namespace App\Http\Livewire\Inventory;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Modules\Product\Entities\Product;

class RackMoveProductTable extends Component
{
    public array $products = [];

    public ?int $fromWarehouseId = null;
    public ?int $fromRackId = null;

    protected $listeners = [
        'productSelected' => 'addProduct',
        'rackMoveFromWarehouseSelected' => 'setFromWarehouse',
        'rackMoveFromRackSelected' => 'setFromRack',
    ];

    public function render()
    {
        return view('livewire.inventory.rack-move-product-table');
    }

    private function parseId($value): ?int
    {
        if (is_array($value)) {
            $value = $value['value'] ?? $value['id'] ?? $value['warehouseId'] ?? $value['rackId'] ?? null;
        }
        return $value ? (int) $value : null;
    }

    public function setFromWarehouse($warehouseId): void
    {
        $newId = $this->parseId($warehouseId);

        if ($this->fromWarehouseId !== null && $newId !== $this->fromWarehouseId) {
            // kalau ganti warehouse, reset juga rack + items
            $this->fromRackId = null;
            $this->products = [];
            session()->flash('message', 'Source warehouse changed. Selected products have been reset.');
        }

        $this->fromWarehouseId = $newId;
    }

    public function setFromRack($rackId): void
    {
        $newId = $this->parseId($rackId);

        if ($this->fromRackId !== null && $newId !== $this->fromRackId) {
            // rack berubah, produk tetap, tapi stock harus di-refresh
            $this->fromRackId = $newId;
            $this->refreshAllRowsStock();
            return;
        }

        $this->fromRackId = $newId;
        $this->refreshAllRowsStock();
    }

    public function addProduct(array $payload): void
    {
        if (!$this->fromWarehouseId) {
            session()->flash('message', 'Please select Source Warehouse first!');
            return;
        }
        if (!$this->fromRackId) {
            session()->flash('message', 'Please select Source Rack first!');
            return;
        }

        $productId = (int) ($payload['id'] ?? 0);
        if ($productId <= 0) {
            session()->flash('message', 'Invalid product payload.');
            return;
        }

        $cond = 'good';
        if ($this->existsSameProductCondition($productId, $cond)) {
            session()->flash('message', 'Product already selected with GOOD condition. Use Split button to add another condition.');
            return;
        }

        $product = Product::withoutGlobalScopes()->find($productId);
        if (!$product) {
            session()->flash('message', 'Product not found.');
            return;
        }

        $row = [
            'id' => $productId,
            'product_name' => (string) ($payload['product_name'] ?? $product->product_name ?? '-'),
            'product_code' => (string) ($payload['product_code'] ?? $product->product_code ?? '-'),
            'product_unit' => (string) ($payload['product_unit'] ?? $product->product_unit ?? ''),
            'condition' => $cond,
            'quantity' => 1,

            // stock breakdown (from rack)
            'stock_total' => 0,
            'stock_good' => 0,
            'stock_defect' => 0,
            'stock_damaged' => 0,
            'stock_qty' => 0,
        ];

        $this->products[] = $row;
        $idx = count($this->products) - 1;

        $this->refreshRowStock($idx);
        $this->clampQtyToStock($idx);
    }

    public function removeProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;
        array_splice($this->products, $index, 1);
    }

    public function splitProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;

        if (!$this->fromWarehouseId || !$this->fromRackId) {
            session()->flash('message', 'Please select Source Warehouse & Source Rack first!');
            return;
        }

        $pid = (int) ($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $used = $this->getUsedConditionsForProduct($pid);
        $next = $this->pickNextCondition($used);
        if (!$next) {
            session()->flash('message', 'This product already has GOOD/DEFECT/DAMAGED rows. Cannot add more.');
            return;
        }

        $payload = [
            'id' => $pid,
            'product_name' => (string) ($this->products[$index]['product_name'] ?? '-'),
            'product_code' => (string) ($this->products[$index]['product_code'] ?? '-'),
            'product_unit' => (string) ($this->products[$index]['product_unit'] ?? ''),
        ];

        $this->addProduct(array_merge($payload, ['condition' => $next]));

        // addProduct default good, jadi kita override baris terakhir ke $next
        $newIdx = count($this->products) - 1;
        $this->products[$newIdx]['condition'] = $next;
        $this->refreshRowStock($newIdx);
        $this->clampQtyToStock($newIdx);
    }

    public function updateCondition(int $index, $value): void
    {
        if (!isset($this->products[$index])) return;

        $val = strtolower(trim((string) $value));
        if (!in_array($val, ['good', 'defect', 'damaged'], true)) {
            $val = 'good';
        }

        $pid = (int) ($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $old = strtolower((string) ($this->products[$index]['condition'] ?? 'good'));

        if ($this->existsSameProductCondition($pid, $val, $index)) {
            session()->flash('message', "This product already has '{$val}' row. Please use another condition.");
            $this->products[$index]['condition'] = $old;
            return;
        }

        $this->products[$index]['condition'] = $val;
        $this->refreshRowStock($index);
        $this->clampQtyToStock($index);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function existsSameProductCondition(int $productId, string $cond, ?int $excludeIndex = null): bool
    {
        foreach ($this->products as $i => $p) {
            if ($excludeIndex !== null && $i === $excludeIndex) continue;
            if ((int) ($p['id'] ?? 0) === $productId && strtolower((string) ($p['condition'] ?? 'good')) === $cond) {
                return true;
            }
        }
        return false;
    }

    private function getUsedConditionsForProduct(int $productId): array
    {
        $used = [];
        foreach ($this->products as $p) {
            if ((int) ($p['id'] ?? 0) !== $productId) continue;
            $c = strtolower((string) ($p['condition'] ?? 'good'));
            if (in_array($c, ['good', 'defect', 'damaged'], true)) $used[$c] = true;
        }
        return array_keys($used);
    }

    private function pickNextCondition(array $used): ?string
    {
        $candidates = ['good', 'defect', 'damaged'];
        foreach ($candidates as $c) {
            if (!in_array($c, $used, true)) return $c;
        }
        return null;
    }

    private function refreshAllRowsStock(): void
    {
        if (!$this->fromWarehouseId || !$this->fromRackId) return;
        foreach ($this->products as $i => $p) {
            $this->refreshRowStock($i);
            $this->clampQtyToStock($i);
        }
    }

    private function refreshRowStock(int $index): void
    {
        if (!isset($this->products[$index])) return;
        if (!$this->fromWarehouseId || !$this->fromRackId) return;

        $pid = (int) ($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $cond = strtolower((string) ($this->products[$index]['condition'] ?? 'good'));
        if (!in_array($cond, ['good', 'defect', 'damaged'], true)) $cond = 'good';

        $branchId = session('active_branch');
        if (!is_numeric($branchId)) return;
        $branchId = (int) $branchId;

        $row = DB::table('stock_racks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $this->fromWarehouseId)
            ->where('rack_id', $this->fromRackId)
            ->where('product_id', $pid)
            ->first();

        $good = (int) ($row->qty_good ?? 0);
        $def = (int) ($row->qty_defect ?? 0);
        $dam = (int) ($row->qty_damaged ?? 0);
        $total = $good + $def + $dam;

        $stockByCond = match ($cond) {
            'good' => $good,
            'defect' => $def,
            'damaged' => $dam,
            default => $good,
        };

        $this->products[$index]['stock_total'] = $total;
        $this->products[$index]['stock_good'] = $good;
        $this->products[$index]['stock_defect'] = $def;
        $this->products[$index]['stock_damaged'] = $dam;
        $this->products[$index]['stock_qty'] = (int) $stockByCond;
    }

    private function clampQtyToStock(int $index): void
    {
        if (!isset($this->products[$index])) return;

        $max = (int) ($this->products[$index]['stock_qty'] ?? 0);
        $qty = (int) ($this->products[$index]['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;
        if ($max >= 0 && $qty > $max) {
            $qty = max(1, $max);
        }

        $this->products[$index]['quantity'] = $qty;
    }
}