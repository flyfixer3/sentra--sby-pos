<?php

namespace App\Http\Livewire\Transfer;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Modules\Inventory\Entities\Stock;

class ProductTable extends Component
{
    public array $products = [];
    public ?int $fromWarehouseId = null;

    /**
     * rackOptions[rowIndex] = [ ['id'=>1,'label'=>'A-01 - Rack A'], ... ]
     */
    public array $rackOptions = [];

    protected $listeners = [
        'productSelected' => 'addProduct',
        'fromWarehouseSelected' => 'setFromWarehouse',
    ];

    public function render()
    {
        return view('livewire.transfer.product-table');
    }

    private function parseWarehouseId($warehouseId): ?int
    {
        if (is_array($warehouseId)) {
            $warehouseId = $warehouseId['warehouseId'] ?? null;
        }
        return $warehouseId ? (int) $warehouseId : null;
    }

    public function setFromWarehouse($warehouseId): void
    {
        $newId = $this->parseWarehouseId($warehouseId);

        if ($this->fromWarehouseId !== null && $newId !== $this->fromWarehouseId) {
            $this->products = [];
            $this->rackOptions = [];
            session()->flash('message', 'Source warehouse changed. Selected products have been reset.');
        }

        $this->fromWarehouseId = $newId;

        if ($this->fromWarehouseId && !empty($this->products)) {
            foreach ($this->products as $idx => $p) {
                $pid = (int) ($p['id'] ?? 0);
                if ($pid <= 0) continue;

                $cond = strtolower((string)($this->products[$idx]['condition'] ?? 'good'));
                if (!in_array($cond, ['good', 'defect', 'damaged'], true)) $cond = 'good';

                // 1) refresh stock from WH
                $breakdown = $this->getStockBreakdown($this->fromWarehouseId, $pid);
                $whAvail = $this->pickStockByCondition($breakdown, $cond);

                $this->products[$idx]['stock_total']   = $breakdown['total'];
                $this->products[$idx]['stock_good']    = $breakdown['good'];
                $this->products[$idx]['stock_defect']  = $breakdown['defect'];
                $this->products[$idx]['stock_damaged'] = $breakdown['damaged'];

                // stok per condition dari WH (untuk kolom "Stock (From WH)")
                $this->products[$idx]['stock_qty_wh'] = (int)$whAvail;

                // 2) refresh racks
                $this->refreshRackOptionsForRow($idx);
                $this->sanitizeSelectedRack($idx);
                $this->autoPickRackIfEmpty($idx);

                // 3) apply effective stock = rack stock (if selected) else wh stock
                $this->applyEffectiveStockForRow($idx);

                // 4) clamp qty
                $this->clampQtyToEffectiveStock($idx);
            }
        }
    }

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

        $cond = 'good';

        if ($this->existsSameProductCondition($productId, $cond)) {
            session()->flash('message', 'Product already selected with GOOD condition. Use Split button to add another condition.');
            return;
        }

        $this->appendRow($productId, $payload, $cond);

        $idx = count($this->products) - 1;

        $this->refreshRackOptionsForRow($idx);
        $this->autoPickRackIfEmpty($idx);

        $this->applyEffectiveStockForRow($idx);
        $this->clampQtyToEffectiveStock($idx);
    }

    public function removeProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;

        array_splice($this->products, $index, 1);
        $this->rebuildRackOptions();
    }

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

        $used = $this->getUsedConditionsForProduct($pid);
        $next = $this->pickNextCondition($used);

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

        $newIdx = count($this->products) - 1;

        $this->refreshRackOptionsForRow($newIdx);
        $this->autoPickRackIfEmpty($newIdx);

        $this->applyEffectiveStockForRow($newIdx);
        $this->clampQtyToEffectiveStock($newIdx);
    }

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

        if ($this->existsSameProductCondition($pid, $val, $index)) {
            session()->flash('message', "This product already has '{$val}' row. Please use another condition.");
            $this->products[$index]['condition'] = $old;
            return;
        }

        $this->products[$index]['condition'] = $val;

        if ($this->fromWarehouseId) {
            // 1) refresh stock WH breakdown
            $breakdown = $this->getStockBreakdown($this->fromWarehouseId, $pid);
            $whAvail = $this->pickStockByCondition($breakdown, $val);

            $this->products[$index]['stock_total']   = $breakdown['total'];
            $this->products[$index]['stock_good']    = $breakdown['good'];
            $this->products[$index]['stock_defect']  = $breakdown['defect'];
            $this->products[$index]['stock_damaged'] = $breakdown['damaged'];

            $this->products[$index]['stock_qty_wh'] = (int)$whAvail;

            // 2) refresh rack options for new condition
            $this->refreshRackOptionsForRow($index);
            $this->sanitizeSelectedRack($index);
            $this->autoPickRackIfEmpty($index);

            // 3) apply effective stock (rack if selected else wh)
            $this->applyEffectiveStockForRow($index);

            // 4) clamp
            $this->clampQtyToEffectiveStock($index);
        }
    }

    public function updateFromRack(int $index, $rackId): void
    {
        if (!isset($this->products[$index])) return;

        $rid = $rackId ? (int) $rackId : null;

        $allowed = collect($this->rackOptions[$index] ?? [])
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();

        if ($rid !== null && !in_array((int)$rid, $allowed, true)) {
            $this->products[$index]['from_rack_id'] = null;
            session()->flash('message', 'Selected rack is not valid for this product/condition/warehouse.');
            $this->applyEffectiveStockForRow($index);
            $this->clampQtyToEffectiveStock($index);
            return;
        }

        $this->products[$index]['from_rack_id'] = $rid;

        // ✅ Ini inti revisi: setelah pilih rack, stok efektif di row ikut rack
        $this->applyEffectiveStockForRow($index);
        $this->clampQtyToEffectiveStock($index);
    }

    // =========================
    // Existing helpers
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

            // ✅ stok per condition dari WH (buat kolom WH)
            'stock_qty_wh'  => (int) $availableForCond,

            // ✅ stok rack yang kepilih (buat kolom rack)
            'stock_qty_rack'=> 0,

            // ✅ stok efektif untuk validasi qty (rack kalau dipilih, kalau tidak ya WH)
            'stock_qty'     => (int) $availableForCond,

            'condition'     => $cond,
            'quantity'      => 1,

            'from_rack_id'  => null,

            // agar blade aman
            'rack_options'  => [],
        ];

        if ($availableForCond <= 0) {
            session()->flash('message', "Warning: selected product has 0 stock for {$cond} in this warehouse.");
        }
    }

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

    // =========================
    // ✅ NEW: Effective Stock logic (WH vs Rack)
    // =========================

    private function applyEffectiveStockForRow(int $index): void
    {
        if (!isset($this->products[$index])) return;
        if (!$this->fromWarehouseId) return;

        $pid = (int)($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return;

        $cond = strtolower((string)($this->products[$index]['condition'] ?? 'good'));
        if (!in_array($cond, ['good','defect','damaged'], true)) $cond = 'good';

        $whAvail = (int)($this->products[$index]['stock_qty_wh'] ?? 0);

        $rackId = $this->products[$index]['from_rack_id'] ?? null;
        $rackId = $rackId ? (int)$rackId : null;

        if ($rackId) {
            $rackAvail = $this->getRackAvailableForCondition($this->fromWarehouseId, $pid, $cond, $rackId);

            $this->products[$index]['stock_qty_rack'] = (int)$rackAvail;

            // ✅ stok efektif pakai rack (karena user sudah pilih rack)
            $this->products[$index]['stock_qty'] = (int)$rackAvail;
        } else {
            $this->products[$index]['stock_qty_rack'] = 0;

            // ✅ stok efektif fallback ke WH
            $this->products[$index]['stock_qty'] = (int)$whAvail;
        }
    }

    private function clampQtyToEffectiveStock(int $index): void
    {
        if (!isset($this->products[$index])) return;

        $avail = (int)($this->products[$index]['stock_qty'] ?? 0);
        $q = (int)($this->products[$index]['quantity'] ?? 1);

        if ($q < 1) $q = 1;

        // kalau avail 0, tetap biarkan qty 1? biasanya mending clamp ke 1 tapi akan gagal saat submit.
        // sesuai pola sebelumnya, kita clamp maksimal ke avail (kalau avail 0, jadi 1? itu aneh).
        // jadi: kalau avail <=0 => set qty 1 tapi kasih warning.
        if ($avail <= 0) {
            $this->products[$index]['quantity'] = 1;
            session()->flash('message', 'Selected rack/condition has 0 stock. Please choose another rack or condition.');
            return;
        }

        if ($q > $avail) {
            $this->products[$index]['quantity'] = max(1, $avail);
            session()->flash('message', 'Quantity adjusted because stock is limited for selected rack/condition.');
            return;
        }

        $this->products[$index]['quantity'] = $q;
    }

    private function getRackAvailableForCondition(int $warehouseId, int $productId, string $cond, int $rackId): int
    {
        $branchId = session('active_branch');
        if ($branchId === 'all' || $branchId === null || $branchId === '') return 0;
        $branchId = (int)$branchId;

        $cond = strtolower(trim($cond));
        $col = match ($cond) {
            'defect' => 'qty_defect',
            'damaged'=> 'qty_damaged',
            default  => 'qty_good',
        };

        try {
            $val = (int) DB::table('stock_racks')
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int)$warehouseId)
                ->where('product_id', (int)$productId)
                ->where('rack_id', (int)$rackId)
                ->value($col);
        } catch (\Throwable $e) {
            return 0;
        }

        return $val > 0 ? $val : 0;
    }

    // =========================
    // ✅ Rack helpers (revisi label: tanpa qty)
    // =========================

    private function rebuildRackOptions(): void
    {
        $new = [];
        foreach ($this->products as $i => $p) {
            $new[$i] = $this->getRackOptionsForRow($i);
            $this->products[$i]['rack_options'] = $new[$i];
        }
        $this->rackOptions = $new;

        foreach ($this->products as $i => $p) {
            $this->sanitizeSelectedRack($i);
            $this->autoPickRackIfEmpty($i);

            $this->applyEffectiveStockForRow($i);
            $this->clampQtyToEffectiveStock($i);
        }
    }

    private function refreshRackOptionsForRow(int $index): void
    {
        $opts = $this->getRackOptionsForRow($index);

        $this->rackOptions[$index] = $opts;
        $this->products[$index]['rack_options'] = $opts;
    }

    private function getRackOptionsForRow(int $index): array
    {
        if (!$this->fromWarehouseId) return [];
        if (!isset($this->products[$index])) return [];

        $branchId = session('active_branch');
        if ($branchId === 'all' || $branchId === null || $branchId === '') return [];
        $branchId = (int) $branchId;

        $pid = (int) ($this->products[$index]['id'] ?? 0);
        if ($pid <= 0) return [];

        $cond = strtolower((string)($this->products[$index]['condition'] ?? 'good'));
        if (!in_array($cond, ['good','defect','damaged'], true)) $cond = 'good';

        $qtyCol = match ($cond) {
            'defect' => 'sr.qty_defect',
            'damaged'=> 'sr.qty_damaged',
            default  => 'sr.qty_good',
        };

        try {
            $rows = DB::table('stock_racks as sr')
                ->join('racks as r', 'r.id', '=', 'sr.rack_id')
                ->where('sr.branch_id', $branchId)
                ->where('sr.warehouse_id', (int) $this->fromWarehouseId)
                ->where('sr.product_id', $pid)
                ->where($qtyCol, '>', 0)
                ->orderByRaw("CASE WHEN r.code IS NULL OR r.code = '' THEN 1 ELSE 0 END ASC")
                ->orderBy('r.code')
                ->orderBy('r.name')
                ->orderBy('r.id')
                ->select([
                    'sr.rack_id',
                    'sr.qty_good',
                    'sr.qty_defect',
                    'sr.qty_damaged',
                    'r.code as rack_code',
                    'r.name as rack_name',
                ])
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $opts = [];

        foreach ($rows as $row) {
            $rackId = (int) $row->rack_id;

            $avail = match ($cond) {
                'defect' => (int) ($row->qty_defect ?? 0),
                'damaged'=> (int) ($row->qty_damaged ?? 0),
                default  => (int) ($row->qty_good ?? 0),
            };

            if ($avail <= 0) continue;

            $code = trim((string)($row->rack_code ?? ''));
            $name = trim((string)($row->rack_name ?? ''));

            // ✅ REVISI UTAMA: label TANPA qty "(11)"
            $label = $code !== '' ? $code : ("Rack#".$rackId);
            if ($name !== '') $label .= " - ".$name;

            $opts[] = [
                'id' => $rackId,
                'label' => $label,
                // keep available untuk internal kalau suatu saat mau dipakai
                'available' => $avail,
            ];
        }

        return $opts;
    }

    private function sanitizeSelectedRack(int $index): void
    {
        if (!isset($this->products[$index])) return;

        $selected = $this->products[$index]['from_rack_id'] ?? null;
        if (!$selected) return;

        $allowed = collect($this->rackOptions[$index] ?? [])
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();

        if (!in_array((int)$selected, $allowed, true)) {
            $this->products[$index]['from_rack_id'] = null;
        }
    }

    private function autoPickRackIfEmpty(int $index): void
    {
        if (!isset($this->products[$index])) return;

        $selected = $this->products[$index]['from_rack_id'] ?? null;
        if ($selected) return;

        $opts = $this->rackOptions[$index] ?? [];
        if (empty($opts)) return;

        $this->products[$index]['from_rack_id'] = (int) $opts[0]['id'];
    }
}
