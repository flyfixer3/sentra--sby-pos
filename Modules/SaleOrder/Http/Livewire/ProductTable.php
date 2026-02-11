<?php

namespace Modules\SaleOrder\Http\Livewire;

use Livewire\Component;
use Modules\Product\Entities\Product;

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

        // ✅ Kalau kosong, tetap ada 1 row
        if (count($this->items) === 0) {
            $this->addEmptyRow();
            return;
        }

        // ✅ FIX UTAMA: kalau ada pid tapi name/code kosong, lookup dari master Product
        $needIds = collect($this->items)
            ->filter(function ($row) {
                $pid = (int)($row['product_id'] ?? 0);
                if ($pid <= 0) return false;

                $nameEmpty = empty($row['product_name']);
                $codeEmpty = empty($row['product_code']);

                return $nameEmpty || $codeEmpty;
            })
            ->pluck('product_id')
            ->unique()
            ->values();

        if ($needIds->count() > 0) {
            $map = Product::query()
                ->select('id', 'product_name', 'product_code', 'product_price')
                ->whereIn('id', $needIds->all())
                ->get()
                ->keyBy('id');

            foreach ($this->items as $idx => $row) {
                $pid = (int)($row['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $p = $map->get($pid);
                if (!$p) continue;

                if (empty($this->items[$idx]['product_name'])) {
                    $this->items[$idx]['product_name'] = (string) ($p->product_name ?? '');
                }
                if (empty($this->items[$idx]['product_code'])) {
                    $this->items[$idx]['product_code'] = (string) ($p->product_code ?? '');
                }

                // OPTIONAL: kalau price 0 dan product punya price, isi otomatis
                if ((int)($this->items[$idx]['price'] ?? 0) <= 0 && $p->product_price !== null) {
                    $this->items[$idx]['price'] = (int) $p->product_price;
                }
            }
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

    public function onProductSelected($product): void
    {
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
