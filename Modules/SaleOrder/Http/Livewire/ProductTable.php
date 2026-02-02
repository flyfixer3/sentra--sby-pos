<?php

namespace Modules\SaleOrder\Http\Livewire;

use Livewire\Component;

class ProductTable extends Component
{
    /**
     * items = array of:
     * [
     *   'product_id' => int,
     *   'product_name' => string|null,
     *   'product_code' => string|null,
     *   'quantity' => int,
     *   'price' => int,
     * ]
     */
    public array $items = [];

    // ✅ Kita listen beberapa kemungkinan event biar robust
    protected $listeners = [
        'productSelected' => 'onProductSelected',
        'selectProduct'   => 'onProductSelected',
        'selectedProduct' => 'onProductSelected',
    ];

    public function mount($prefillItems = [])
    {
        $rows = is_array($prefillItems) ? $prefillItems : [];

        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $this->items[] = [
                'product_id'   => $pid,
                'product_name' => $r['product_name'] ?? null,
                'product_code' => $r['product_code'] ?? null,
                'quantity'     => max(1, (int)($r['quantity'] ?? 1)),
                'price'        => max(0, (int)($r['price'] ?? 0)),
            ];
        }

        if (count($this->items) === 0) {
            $this->addEmptyRow();
        }
    }

    public function addEmptyRow(): void
    {
        $this->items[] = [
            'product_id'   => 0,
            'product_name' => null,
            'product_code' => null,
            'quantity'     => 1,
            'price'        => 0,
        ];
    }

    /**
     * Payload dari search-product biasanya Eloquent model (di blade kamu: selectProduct({{ $result }}))
     * jadi di Livewire akan masuk sebagai array/obj.
     */
    public function onProductSelected($product): void
    {
        // coba normalize
        $pid = (int) data_get($product, 'id', 0);
        if ($pid <= 0) return;

        // kalau sudah ada, +1 qty
        foreach ($this->items as $idx => $row) {
            if ((int)$row['product_id'] === $pid) {
                $this->items[$idx]['quantity'] = (int)$this->items[$idx]['quantity'] + 1;
                return;
            }
        }

        // replace empty row pertama kalau ada
        foreach ($this->items as $idx => $row) {
            if ((int)($row['product_id'] ?? 0) <= 0) {
                $this->items[$idx]['product_id']   = $pid;
                $this->items[$idx]['product_name'] = (string) data_get($product, 'product_name', '');
                $this->items[$idx]['product_code'] = (string) data_get($product, 'product_code', '');
                $this->items[$idx]['price']        = (int) data_get($product, 'product_price', 0);
                $this->items[$idx]['quantity']     = 1;
                return;
            }
        }

        // kalau tidak ada empty row, append baru
        $this->items[] = [
            'product_id'   => $pid,
            'product_name' => (string) data_get($product, 'product_name', ''),
            'product_code' => (string) data_get($product, 'product_code', ''),
            'quantity'     => 1,
            'price'        => (int) data_get($product, 'product_price', 0),
        ];
    }

    public function removeRow(int $index): void
    {
        if (count($this->items) <= 1) {
            // minimal 1 row
            $this->items = [[
                'product_id' => 0,
                'product_name' => null,
                'product_code' => null,
                'quantity' => 1,
                'price' => 0,
            ]];
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function render()
    {
        return view('saleorder::livewire.product-table');
    }
}
