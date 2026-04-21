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
     *   'price' => int,                    // net selling price after item discount
     *   'original_price' => int,           // master price from DB (baseline)
     *   'product_discount_type' => string, // fixed = nominal discount, percentage = % off unit price
     *   'discount_value' => float|int,     // nominal discount or percentage value
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
            $originalPrice = max(0, (int)($r['original_price'] ?? $r['unit_price'] ?? 0));
            $discountType = $this->normalizeDiscountType($r['product_discount_type'] ?? 'fixed');
            $discountAmount = max(0, (int)($r['product_discount_amount'] ?? max(0, $originalPrice - $price)));

            $discountValue = $discountType === 'percentage'
                ? ($originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100, 2) : 0)
                : $discountAmount;

            $this->items[] = [
                'product_id'             => $pid,
                'product_name'           => $r['product_name'] ?? null,
                'product_code'           => $r['product_code'] ?? null,
                'quantity'               => max(1, (int)($r['quantity'] ?? 1)),
                'price'                  => $price,
                'original_price'         => $originalPrice,
                'product_discount_type'  => $discountType,
                'discount_value'         => $discountValue,
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

                $this->syncDiscountValue($idx);
            }
        }
    }

    public function addEmptyRow(): void
    {
        $this->items[] = [
            'product_id'             => 0,
            'product_name'           => null,
            'product_code'           => null,
            'quantity'               => 1,
            'price'                  => 0,
            'original_price'         => 0,
            'product_discount_type'  => 'fixed',
            'discount_value'         => 0,
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
                $this->syncDiscountValue($idx);

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
                $this->items[$idx]['product_discount_type'] = 'fixed';
                $this->items[$idx]['discount_value'] = 0;
                return;
            }
        }

        $this->items[] = [
            'product_id'             => $pid,
            'product_name'           => (string) data_get($product, 'product_name', ''),
            'product_code'           => (string) data_get($product, 'product_code', ''),
            'quantity'               => 1,
            'original_price'         => $masterPrice,
            'price'                  => $masterPrice,
            'product_discount_type'  => 'fixed',
            'discount_value'         => 0,
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
                'product_discount_type' => 'fixed',
                'discount_value' => 0,
            ]];
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $name): void
    {
        $parts = explode('.', (string) $name);
        $index = isset($parts[0]) ? (int) $parts[0] : null;
        $field = $parts[1] ?? '';

        if ($index === null || !array_key_exists($index, $this->items)) {
            return;
        }

        $this->normalizeRow($index, $field);
    }

    private function normalizeRow(int $index, string $field = ''): void
    {
        $unitPrice = max(0, (int)($this->items[$index]['original_price'] ?? 0));
        $price = max(0, (int)($this->items[$index]['price'] ?? 0));
        $type = $this->normalizeDiscountType($this->items[$index]['product_discount_type'] ?? 'fixed');

        $this->items[$index]['quantity'] = max(1, (int)($this->items[$index]['quantity'] ?? 1));
        $this->items[$index]['product_discount_type'] = $type;

        if ($unitPrice <= 0) {
            $this->items[$index]['discount_value'] = 0;
            $this->items[$index]['price'] = 0;
            return;
        }

        // Kalau user ubah langsung Net Price, sinkronkan discount_value sesuai mode aktif
        if ($field === 'price') {
            $price = max(0, min($unitPrice, $price));
            $this->items[$index]['price'] = $price;

            if ($type === 'percentage') {
                $discountAmount = max(0, $unitPrice - $price);
                $this->items[$index]['discount_value'] = $unitPrice > 0
                    ? round(($discountAmount / $unitPrice) * 100, 2)
                    : 0;
            } else {
                $this->items[$index]['discount_value'] = max(0, $unitPrice - $price);
            }

            return;
        }

        // Kalau user ganti unit discount
        if ($field === 'product_discount_type') {
            if ($type === 'percentage') {
                // Dari fixed/net price -> ubah jadi discount %
                $currentNetPrice = max(0, min($unitPrice, $price));
                $discountAmount = max(0, $unitPrice - $currentNetPrice);

                $this->items[$index]['discount_value'] = $unitPrice > 0
                    ? round(($discountAmount / $unitPrice) * 100, 2)
                    : 0;
            } else {
                // Dari percentage -> ubah jadi nominal discount (Rp)
                $currentPercentage = (float)($this->items[$index]['discount_value'] ?? 0);
                $currentPercentage = max(0, min(100, $currentPercentage));
                $discountAmount = (int) round($unitPrice * ($currentPercentage / 100));

                $this->items[$index]['discount_value'] = max(0, $discountAmount);
            }
        }

        if ($type === 'percentage') {
            // IMPORTANT:
            // jika pilih %, yang diinput adalah persen DISCOUNT
            // contoh: harga akhir 70% => discount = 30%
            $percentage = (float)($this->items[$index]['discount_value'] ?? 0);
            $percentage = max(0, min(100, $percentage));

            $discountAmount = (int) round($unitPrice * ($percentage / 100));
            $netPrice = max(0, $unitPrice - $discountAmount);

            $this->items[$index]['discount_value'] = $percentage;
            $this->items[$index]['price'] = $netPrice;
            return;
        }

        // fixed = nilai input adalah DISCOUNT NOMINAL
        $nominalDiscount = (int) round((float)($this->items[$index]['discount_value'] ?? 0));
        $nominalDiscount = max(0, min($unitPrice, $nominalDiscount));

        $this->items[$index]['discount_value'] = $nominalDiscount;
        $this->items[$index]['price'] = max(0, $unitPrice - $nominalDiscount);
    }

    private function syncDiscountValue(int $index): void
    {
        if (!array_key_exists($index, $this->items)) {
            return;
        }

        $this->normalizeRow($index, 'price');
    }

    private function normalizeDiscountType($type): string
    {
        return (string) $type === 'percentage' ? 'percentage' : 'fixed';
    }

    public function render()
    {
        return view('saleorder::livewire.product-table');
    }
}
