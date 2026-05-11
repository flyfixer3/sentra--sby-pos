<?php

namespace Modules\SaleOrder\Http\Livewire;

use App\Support\BranchContext;
use Livewire\Component;
use Modules\People\Entities\CustomerVehicle;
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
     *   'price' => int,                    // live sell unit price before item discount
     *   'original_price' => int,           // master price from DB (baseline)
     *   'product_discount_type' => string, // fixed = nominal discount, percentage = % off unit price
     *   'discount_value' => float|int,     // nominal discount or percentage value
     * ]
     */
    public array $items = [];
    public ?int $customerId = null;
    public array $customerVehicles = [];

    protected $listeners = [
        'productSelected' => 'onProductSelected',
        'selectProduct'   => 'onProductSelected',
        'selectedProduct' => 'onProductSelected',
        'saleOrderCustomerChanged' => 'onCustomerChanged',
    ];

    public function mount($prefillItems = [], $customerId = null)
    {
        $this->customerId = (int) $customerId > 0 ? (int) $customerId : null;
        $this->loadCustomerVehicles();

        $rows = is_array($prefillItems) ? $prefillItems : [];

        foreach ($rows as $r) {
            $pid = (int)($r['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $price = max(0, (int)($r['price'] ?? 0));
            $originalPrice = max(0, (int)($r['original_price'] ?? $r['unit_price'] ?? 0));
            $submittedUnitPrice = $r['unit_price'] ?? $originalPrice;
            $sellUnitPrice = max(0, (int) ($submittedUnitPrice ?: $price));
            if ($sellUnitPrice <= 0) {
                $sellUnitPrice = $price;
            }
            $discountType = $this->normalizeDiscountType($r['product_discount_type'] ?? 'fixed');
            $discountAmount = max(0, (int)($r['product_discount_amount'] ?? max(0, $sellUnitPrice - $price)));

            $discountValue = $discountType === 'percentage'
                ? ($sellUnitPrice > 0 ? round(($discountAmount / $sellUnitPrice) * 100, 2) : 0)
                : $discountAmount;

            $this->items[] = [
                'product_id'             => $pid,
                'product_name'           => $r['product_name'] ?? null,
                'product_code'           => $r['product_code'] ?? null,
                'quantity'               => max(1, (int)($r['quantity'] ?? 1)),
                'price'                  => $sellUnitPrice,
                'original_price'         => $originalPrice,
                'product_discount_type'  => $discountType,
                'discount_value'         => $discountValue,
                'installation_type'      => $this->normalizeInstallationType($r['installation_type'] ?? 'item_only'),
                'customer_vehicle_id'    => $this->normalizeInstallationType($r['installation_type'] ?? 'item_only') === 'with_installation'
                    ? ((int) ($r['customer_vehicle_id'] ?? 0) ?: null)
                    : null,
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
            'installation_type'      => 'item_only',
            'customer_vehicle_id'    => null,
        ];
    }

    public function onProductSelected($product): void
    {
        $pid = (int) data_get($product, 'id', 0);
        if ($pid <= 0) return;

        $masterPrice = (int) data_get($product, 'product_price', 0);
        $productName = trim((string) data_get($product, 'product_name', ''));
        $productCode = trim((string) data_get($product, 'product_code', ''));

        if ($productName === '' || $productCode === '' || $masterPrice <= 0) {
            $masterProduct = Product::withoutGlobalScopes()
                ->select('id', 'product_name', 'product_code', 'product_price')
                ->find($pid);

            if ($masterProduct) {
                $productName = $productName !== '' ? $productName : (string) ($masterProduct->product_name ?? '');
                $productCode = $productCode !== '' ? $productCode : (string) ($masterProduct->product_code ?? '');
                $masterPrice = $masterPrice > 0 ? $masterPrice : (int) ($masterProduct->product_price ?? 0);
            }
        }

        foreach ($this->items as $idx => $row) {
            if ((int)$row['product_id'] === $pid) {
                $this->items[$idx]['quantity'] = (int)$this->items[$idx]['quantity'] + 1;

                if (empty($this->items[$idx]['product_name'])) {
                    $this->items[$idx]['product_name'] = $productName;
                }
                if (empty($this->items[$idx]['product_code'])) {
                    $this->items[$idx]['product_code'] = $productCode;
                }
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
                $this->items[$idx]['product_name']   = $productName;
                $this->items[$idx]['product_code']   = $productCode;
                $this->items[$idx]['original_price'] = $masterPrice;
                $this->items[$idx]['price']          = $masterPrice;
                $this->items[$idx]['quantity']       = 1;
                $this->items[$idx]['product_discount_type'] = 'fixed';
                $this->items[$idx]['discount_value'] = 0;
                $this->items[$idx]['installation_type'] = 'item_only';
                $this->items[$idx]['customer_vehicle_id'] = null;
                return;
            }
        }

        $this->items[] = [
            'product_id'             => $pid,
            'product_name'           => $productName,
            'product_code'           => $productCode,
            'quantity'               => 1,
            'original_price'         => $masterPrice,
            'price'                  => $masterPrice,
            'product_discount_type'  => 'fixed',
            'discount_value'         => 0,
            'installation_type'      => 'item_only',
            'customer_vehicle_id'    => null,
        ];
    }

    public function duplicateRow(int $index): void
    {
        if (!array_key_exists($index, $this->items)) {
            return;
        }

        $row = $this->items[$index];
        if ((int) ($row['product_id'] ?? 0) <= 0) {
            return;
        }

        $masterPrice = max(0, (int) ($row['original_price'] ?? 0));
        $sellUnitPrice = max(0, (int) ($row['price'] ?? $masterPrice));

        $this->items[] = [
            'product_id'             => (int) $row['product_id'],
            'product_name'           => $row['product_name'] ?? null,
            'product_code'           => $row['product_code'] ?? null,
            'quantity'               => 1,
            'price'                  => $sellUnitPrice,
            'original_price'         => $masterPrice,
            'product_discount_type'  => 'fixed',
            'discount_value'         => 0,
            'installation_type'      => 'item_only',
            'customer_vehicle_id'    => null,
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
                'installation_type' => 'item_only',
                'customer_vehicle_id' => null,
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

    public function onCustomerChanged($customerId): void
    {
        $this->customerId = (int) $customerId > 0 ? (int) $customerId : null;
        $this->loadCustomerVehicles();
        $this->clearInvalidVehicles();
    }

    public function syncAllRowsBeforeSubmit(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->normalizeRow((int) $index);
        }

        $this->dispatchBrowserEvent('sale-order-cart-synced');
    }

    private function normalizeRow(int $index, string $field = ''): void
    {
        $price = max(0, (int)($this->items[$index]['price'] ?? 0));
        $type = $this->normalizeDiscountType($this->items[$index]['product_discount_type'] ?? 'fixed');

        $this->items[$index]['quantity'] = max(1, (int)($this->items[$index]['quantity'] ?? 1));
        $this->items[$index]['product_discount_type'] = $type;
        $this->items[$index]['installation_type'] = $this->normalizeInstallationType($this->items[$index]['installation_type'] ?? 'item_only');

        if (($this->items[$index]['installation_type'] ?? 'item_only') !== 'with_installation') {
            $this->items[$index]['customer_vehicle_id'] = null;
        } else {
            $vehicleId = (int) ($this->items[$index]['customer_vehicle_id'] ?? 0);
            $this->items[$index]['customer_vehicle_id'] = in_array($vehicleId, $this->getVehicleIds(), true)
                ? $vehicleId
                : null;
        }

        if ($field === 'price') {
            $this->items[$index]['price'] = $price;
            $this->clampDiscountValue($index, $type, $price);
            return;
        }

        if ($field === 'product_discount_type') {
            $this->items[$index]['discount_value'] = 0;
            return;
        }

        $this->clampDiscountValue($index, $type, $price);
    }

    private function clampDiscountValue(int $index, string $type, int $sellUnitPrice): void
    {
        if ($type === 'percentage') {
            $this->items[$index]['discount_value'] = max(0, min(100, (float) ($this->items[$index]['discount_value'] ?? 0)));
            return;
        }

        $this->items[$index]['discount_value'] = max(0, min($sellUnitPrice, (int) round((float) ($this->items[$index]['discount_value'] ?? 0))));
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

    private function normalizeInstallationType($type): string
    {
        return (string) $type === 'with_installation' ? 'with_installation' : 'item_only';
    }

    private function loadCustomerVehicles(): void
    {
        if (!$this->customerId) {
            $this->customerVehicles = [];
            return;
        }

        $branchId = BranchContext::id();

        $this->customerVehicles = CustomerVehicle::query()
            ->where('customer_id', (int) $this->customerId)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', (int) $branchId);
                });
            })
            ->orderBy('car_plate')
            ->get(['id', 'car_plate', 'vehicle_name'])
            ->map(function ($vehicle) {
                $label = trim((string) $vehicle->car_plate);
                $vehicleName = trim((string) ($vehicle->vehicle_name ?? ''));

                if ($vehicleName !== '') {
                    $label .= ' / ' . $vehicleName;
                }

                return [
                    'id' => (int) $vehicle->id,
                    'label' => $label !== '' ? $label : ('Vehicle #' . (int) $vehicle->id),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getVehicleIds(): array
    {
        return collect($this->customerVehicles)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function clearInvalidVehicles(): void
    {
        $validVehicleIds = $this->getVehicleIds();

        foreach ($this->items as $index => $row) {
            $type = $this->normalizeInstallationType($row['installation_type'] ?? 'item_only');
            $this->items[$index]['installation_type'] = $type;

            if ($type !== 'with_installation') {
                $this->items[$index]['customer_vehicle_id'] = null;
                continue;
            }

            $vehicleId = (int) ($row['customer_vehicle_id'] ?? 0);
            if ($vehicleId <= 0 || !in_array($vehicleId, $validVehicleIds, true)) {
                $this->items[$index]['customer_vehicle_id'] = null;
            }
        }
    }

    public function render()
    {
        return view('saleorder::livewire.product-table');
    }
}
