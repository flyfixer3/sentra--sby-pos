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
     *   'price' => int,            // editable selling price
     *   'original_price' => int,   // master price from DB (baseline)
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

            $price = max(0, (int)($r['price'] ?? 0));

            $this->items[] = [
                'product_id'      => $pid,
                'product_name'    => $r['product_name'] ?? null,
                'product_code'    => $r['product_code'] ?? null,
                'quantity'        => max(1, (int)($r['quantity'] ?? 1)),
                'price'           => $price,
                'original_price'  => max(0, (int)($r['original_price'] ?? 0)),
            ];
        }

        if (count($this->items) === 0) {
            $this->addEmptyRow();
            return;
        }

        $needIds = collect($this->items)
            ->filter(function ($row) {
                $pid = (int)($row['product_id'] ?? 0);
                if ($pid <= 0) return false;

                $nameEmpty = empty($row['product_name']);
                $codeEmpty = empty($row['product_code']);
                $origEmpty = (int)($row['original_price'] ?? 0) <= 0;

                return $nameEmpty || $codeEmpty || $origEmpty;
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

                $master = (int) ($p->product_price ?? 0);
                if ((int)($this->items[$idx]['original_price'] ?? 0) <= 0) {
                    $this->items[$idx]['original_price'] = $master;
                }

                if ((int)($this->items[$idx]['price'] ?? 0) <= 0 && $master > 0) {
                    $this->items[$idx]['price'] = $master;
                }
            }
        }
    }

    public function addEmptyRow(): void
    {
        $this->items[] = [
            'product_id'      => 0,
            'product_name'    => null,
            'product_code'    => null,
            'quantity'        => 1,
            'price'           => 0,
            'original_price'  => 0,
        ];
    }

    public function onProductSelected($product): void
    {
        $pid = (int) data_get($product, 'id', 0);
        if ($pid <= 0) return;

        $masterPrice = (int) data_get($product, 'product_price', 0);

        foreach ($this->items as $idx => $row) {
            if ((int)$row['product_id'] === $pid) {
                $this->items[$idx]['quantity'] = (int)$this->items[$idx]['quantity'] + 1;

                if ((int)($this->items[$idx]['original_price'] ?? 0) <= 0) {
                    $this->items[$idx]['original_price'] = $masterPrice;
                }
                if ((int)($this->items[$idx]['price'] ?? 0) <= 0) {
                    $this->items[$idx]['price'] = $masterPrice;
                }

                return;
            }
        }

        foreach ($this->items as $idx => $row) {
            if ((int)($row['product_id'] ?? 0) <= 0) {
                $this->items[$idx]['product_id']     = $pid;
                $this->items[$idx]['product_name']   = (string) data_get($product, 'product_name', '');
                $this->items[$idx]['product_code']   = (string) data_get($product, 'product_code', '');
                $this->items[$idx]['original_price'] = $masterPrice;
                $this->items[$idx]['price']          = $masterPrice;
                $this->items[$idx]['quantity']       = 1;
                return;
            }
        }

        $this->items[] = [
            'product_id'      => $pid,
            'product_name'    => (string) data_get($product, 'product_name', ''),
            'product_code'    => (string) data_get($product, 'product_code', ''),
            'quantity'        => 1,
            'original_price'  => $masterPrice,
            'price'           => $masterPrice,
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
                'original_price' => 0,
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
