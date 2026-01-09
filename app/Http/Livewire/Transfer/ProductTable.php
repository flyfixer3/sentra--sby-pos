<?php

namespace App\Http\Livewire\Transfer;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Modules\Inventory\Entities\Stock;

class ProductTable extends Component
{
    public array $products = [];
    public ?int $fromWarehouseId = null;

    protected $listeners = [
        'productSelected' => 'addProduct',
        'fromWarehouseSelected' => 'setFromWarehouse',
    ];

    public function render()
    {
        return view('livewire.transfer.product-table');
    }

    public function setFromWarehouse($warehouseId): void
    {
        if (is_array($warehouseId)) {
            $warehouseId = $warehouseId['warehouseId'] ?? null;
        }

        $newId = $warehouseId ? (int) $warehouseId : null;

        if ($this->fromWarehouseId !== null && $newId !== $this->fromWarehouseId) {
            $this->products = [];
            session()->flash('message', 'Source warehouse changed. Selected products have been reset.');
        }

        $this->fromWarehouseId = $newId;

        if ($this->fromWarehouseId && !empty($this->products)) {
            foreach ($this->products as $idx => $p) {
                $pid = (int) ($p['id'] ?? 0);
                if ($pid > 0) {
                    $cond = strtolower((string)($this->products[$idx]['condition'] ?? 'good'));
                    $breakdown = $this->getStockBreakdown($this->fromWarehouseId, $pid);

                    $this->products[$idx]['stock_total']   = $breakdown['total'];
                    $this->products[$idx]['stock_good']    = $breakdown['good'];
                    $this->products[$idx]['stock_defect']  = $breakdown['defect'];
                    $this->products[$idx]['stock_damaged'] = $breakdown['damaged'];

                    $this->products[$idx]['stock_qty'] = $this->pickStockByCondition($breakdown, $cond);

                    $q = (int)($this->products[$idx]['quantity'] ?? 1);
                    if ($q < 1) $q = 1;
                    if ($q > (int)$this->products[$idx]['stock_qty']) {
                        $this->products[$idx]['quantity'] = max(1, (int)$this->products[$idx]['stock_qty']);
                        session()->flash('message', 'Quantity adjusted because stock is limited for selected condition.');
                    }
                }
            }
        }
    }

    /**
     * ✅ Add product dari search (default GOOD)
     * - boleh tambah produk yang sama kalau condition beda
     * - tapi tidak boleh duplikat (product_id + condition) yang sama
     */
    public function addProduct(array $payload): void
    {
        if (!$this->fromWarehouseId) {
            session()->flash('message', 'Please select Source Warehouse first!');
            return;
        }

        $productId = (int) ($payload['id'] ?? 0);
        if ($productId <= 0) {
            session()->flash('message', 'Invalid product payload.');
            return;
        }

        // default condition saat pertama add dari search
        $cond = 'good';

        // kalau sudah ada row untuk (product_id + good), jangan dobel
        if ($this->existsSameProductCondition($productId, $cond)) {
            session()->flash('message', 'Product already selected with GOOD condition. Use Split button to add another condition.');
            return;
        }

        $this->appendRow($productId, $payload, $cond);
    }

    public function removeProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;
        array_splice($this->products, $index, 1);
    }

    /**
     * ✅ Split row: bikin row baru untuk product yang sama,
     * otomatis ambil condition berikutnya yang belum dipakai.
     */
    public function splitProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;

        if (!$this->fromWarehouseId) {
            session()->flash('message', 'Please select Source Warehouse first!');
            return;
        }

        $pid = (int)($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $name = (string)($this->products[$index]['product_name'] ?? '-');
        $code = (string)($this->products[$index]['product_code'] ?? '-');
        $unit = (string)($this->products[$index]['product_unit'] ?? '');

        $used = $this->getUsedConditionsForProduct($pid); // ex: ['good','defect']
        $next = $this->pickNextCondition($used);          // ex: 'damaged' / null

        if (!$next) {
            session()->flash('message', 'This product already has GOOD/DEFECT/DAMAGED rows. Cannot add more.');
            return;
        }

        $payload = [
            'id' => $pid,
            'product_name' => $name,
            'product_code' => $code,
            'product_unit' => $unit,
        ];

        $this->appendRow($pid, $payload, $next);
    }

    /**
     * ✅ Saat dropdown condition berubah:
     * - cegah kalau sudah ada row lain dengan (product_id + condition) yang sama
     * - update stock badge sesuai condition
     */
    public function updateCondition(int $index, $value): void
    {
        if (!isset($this->products[$index])) return;

        $val = strtolower(trim((string) $value));
        if (!in_array($val, ['good', 'defect', 'damaged'], true)) {
            $val = 'good';
        }

        $pid = (int)($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $old = strtolower((string)($this->products[$index]['condition'] ?? 'good'));

        // kalau ganti ke condition yang sudah dipakai baris lain -> tolak
        if ($this->existsSameProductCondition($pid, $val, $index)) {
            session()->flash('message', "This product already has '{$val}' row. Please use another condition.");
            // revert
            $this->products[$index]['condition'] = $old;
            return;
        }

        $this->products[$index]['condition'] = $val;

        if ($this->fromWarehouseId) {
            $breakdown = $this->getStockBreakdown($this->fromWarehouseId, $pid);

            $this->products[$index]['stock_total']   = $breakdown['total'];
            $this->products[$index]['stock_good']    = $breakdown['good'];
            $this->products[$index]['stock_defect']  = $breakdown['defect'];
            $this->products[$index]['stock_damaged'] = $breakdown['damaged'];

            $this->products[$index]['stock_qty'] = $this->pickStockByCondition($breakdown, $val);

            $q = (int)($this->products[$index]['quantity'] ?? 1);
            if ($q < 1) $q = 1;
            $avail = (int)($this->products[$index]['stock_qty'] ?? 0);
            if ($q > $avail) {
                $this->products[$index]['quantity'] = max(1, $avail);
                session()->flash('message', 'Quantity adjusted because stock is limited for selected condition.');
            }
        }
    }

    // =========================
    // Helpers
    // =========================

    private function appendRow(int $productId, array $payload, string $cond): void
    {
        $breakdown = $this->getStockBreakdown($this->fromWarehouseId, $productId);
        $availableForCond = $this->pickStockByCondition($breakdown, $cond);

        $this->products[] = [
            'id'            => $productId,
            'product_name'  => (string) ($payload['product_name'] ?? '-'),
            'product_code'  => (string) ($payload['product_code'] ?? '-'),
            'product_unit'  => (string) ($payload['product_unit'] ?? ''),

            'stock_total'   => (int) $breakdown['total'],
            'stock_good'    => (int) $breakdown['good'],
            'stock_defect'  => (int) $breakdown['defect'],
            'stock_damaged' => (int) $breakdown['damaged'],

            'stock_qty'     => (int) $availableForCond,

            'condition'     => $cond,
            'quantity'      => 1,
        ];

        if ($availableForCond <= 0) {
            session()->flash('message', "Warning: selected product has 0 stock for {$cond} in this warehouse.");
        }
    }

    /**
     * cek apakah sudah ada row lain dengan product + condition yang sama
     * $ignoreIndex dipakai saat updateCondition (biar compare ke row lain)
     */
    private function existsSameProductCondition(int $productId, string $cond, ?int $ignoreIndex = null): bool
    {
        $cond = strtolower(trim($cond));

        foreach ($this->products as $i => $p) {
            if ($ignoreIndex !== null && $i === $ignoreIndex) continue;

            $pid = (int)($p['id'] ?? 0);
            $c   = strtolower((string)($p['condition'] ?? 'good'));

            if ($pid === $productId && $c === $cond) {
                return true;
            }
        }

        return false;
    }

    private function getUsedConditionsForProduct(int $productId): array
    {
        $used = [];
        foreach ($this->products as $p) {
            if ((int)($p['id'] ?? 0) === $productId) {
                $c = strtolower((string)($p['condition'] ?? 'good'));
                if (in_array($c, ['good','defect','damaged'], true)) $used[] = $c;
            }
        }
        return array_values(array_unique($used));
    }

    private function pickNextCondition(array $used): ?string
    {
        $order = ['good', 'defect', 'damaged'];
        foreach ($order as $c) {
            if (!in_array($c, $used, true)) return $c;
        }
        return null;
    }

    private function getStockBreakdown(int $warehouseId, int $productId): array
    {
        $branchId = session('active_branch');

        if ($branchId === 'all' || $branchId === null || $branchId === '') {
            return ['total' => 0, 'good' => 0, 'defect' => 0, 'damaged' => 0];
        }

        $branchId = (int)$branchId;

        $total = (int) Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', (int) $warehouseId)
            ->where('product_id', (int) $productId)
            ->value('qty_available');

        if ($total < 0) $total = 0;

        $defect = (int) DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', (int) $warehouseId)
            ->where('product_id', (int) $productId)
            ->whereNull('moved_out_at')
            ->sum('quantity');

        $damaged = (int) DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', (int) $warehouseId)
            ->where('product_id', (int) $productId)
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->sum('quantity');

        if ($defect < 0) $defect = 0;
        if ($damaged < 0) $damaged = 0;

        $good = $total - $defect - $damaged;
        if ($good < 0) $good = 0;

        return [
            'total'  => $total,
            'good'   => $good,
            'defect' => $defect,
            'damaged'=> $damaged,
        ];
    }

    private function pickStockByCondition(array $breakdown, string $cond): int
    {
        $cond = strtolower(trim($cond));

        return match ($cond) {
            'good'   => (int)($breakdown['good'] ?? 0),
            'defect' => (int)($breakdown['defect'] ?? 0),
            'damaged'=> (int)($breakdown['damaged'] ?? 0),
            default  => (int)($breakdown['good'] ?? 0),
        };
    }
}
