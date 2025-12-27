<?php

namespace App\Http\Livewire\Transfer;

use Illuminate\Support\Facades\Log;
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
        // handle payload dari JS: bisa "99" atau ["warehouseId" => "99"]
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
                    $this->products[$idx]['stock_qty'] = $this->getAvailableStock($this->fromWarehouseId, $pid);
                }
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

        foreach ($this->products as $p) {
            if ((int)($p['id'] ?? 0) === $productId) {
                session()->flash('message', 'Product already selected.');
                return;
            }
        }

        $available = $this->getAvailableStock($this->fromWarehouseId, $productId);

        $this->products[] = [
            'id'            => $productId,
            'product_name'  => (string) ($payload['product_name'] ?? '-'),
            'product_code'  => (string) ($payload['product_code'] ?? '-'),
            'product_unit'  => (string) ($payload['product_unit'] ?? ''),
            'stock_qty'     => (int) $available,
            'quantity'      => 1,
        ];

        if ($available <= 0) {
            session()->flash('message', 'Warning: selected product has 0 stock in this warehouse.');
        }
    }

    public function removeProduct(int $index): void
    {
        if (!isset($this->products[$index])) return;
        array_splice($this->products, $index, 1);
    }

    private function getAvailableStock(int $warehouseId, int $productId): int
    {
        $branchId = session('active_branch');

        Log::info('DEBUG STOCK CHECK', [
            'branch_id_session' => $branchId,
            'warehouse_id_param' => $warehouseId,
            'product_id_param' => $productId,
        ]);

        $row = Stock::query()
            ->where('branch_id', (int) $branchId)
            ->where('warehouse_id', (int) $warehouseId)
            ->where('product_id', (int) $productId)
            ->first();

        Log::info('DEBUG STOCK ROW', [
            'found' => (bool) $row,
            'row' => $row ? $row->toArray() : null,
        ]);

        return (int) ($row->qty_available ?? 0);
    }
    // private function getAvailableStock(int $warehouseId, int $productId): int
    // {
    //     $branchId = session('active_branch');

    //     dd([
    //         'DEBUG_POINT' => 'getAvailableStock()',
    //         'branch_id_session' => $branchId,
    //         'warehouse_id_param' => $warehouseId,
    //         'product_id_param' => $productId,

    //         'query_preview' => [
    //             'branch_id' => (int) $branchId,
    //             'warehouse_id' => (int) $warehouseId,
    //             'product_id' => (int) $productId,
    //         ],

    //         'stock_row' => Stock::query()
    //             ->where('branch_id', (int) $branchId)
    //             ->where('warehouse_id', (int) $warehouseId)
    //             ->where('product_id', (int) $productId)
    //             ->first(),
    //     ]);
    // }

}
