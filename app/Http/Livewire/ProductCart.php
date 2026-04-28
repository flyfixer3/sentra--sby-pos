<?php

namespace App\Http\Livewire;

use App\Support\BranchContext;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\People\Entities\CustomerVehicle;
use Modules\Mutation\Entities\Mutation;

class ProductCart extends Component
{

    public $listeners = ['productSelected', 'discountModalRefresh', 'quotationCustomerChanged' => 'onQuotationCustomerChanged'];

    public $cart_instance;
    public $global_discount;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee;
    public $quantity;
    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $data;
    public $installation_type;
    public $customer_vehicle_id;
    public $customer_vehicles;
    public $customer_id;

    public function mount($cartInstance, $data = null, $customerId = null) {
        $this->cart_instance = $cartInstance;
        $this->installation_type = [];
        $this->customer_vehicle_id = [];
        $this->customer_vehicles = collect();
        $this->customer_id = $customerId ?: ($data->customer_id ?? null);

        if ($data) {
            $this->data = $data;

            $this->global_discount = $data->discount_percentage;
            $this->global_tax = $data->tax_percentage;
            $this->global_qty = (int) Cart::instance($this->cart_instance)
            ->content()
            ->sum('qty');
            $this->shipping = $data->shipping_amount;
            $this->platform_fee = $data->fee_amount;

            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();
            // $this->updatedGlobalQuantity();

            $cart_items = Cart::instance($this->cart_instance)->content();

            foreach ($cart_items as $cart_item) {
                $stateKey = $this->cartStateKey($cart_item);
                $this->check_quantity[$stateKey] = [$cart_item->options->stock];
                $this->quantity[$stateKey] = $cart_item->qty;
                $this->discount_type[$stateKey] = $cart_item->options->product_discount_type;
                if ($cart_item->options->product_discount_type == 'fixed') {
                    $this->item_discount[$stateKey] = $cart_item->options->product_discount;
                } elseif ($cart_item->options->product_discount_type == 'percentage') {
                    $this->item_discount[$stateKey] = round(100 * ($cart_item->options->product_discount / $cart_item->price));
                }
                $this->initializeQuotationMetadata($cart_item);
            }
        } else {
            $this->global_discount = 0;
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0.00;
            $this->platform_fee = 0.00;
            $this->check_quantity = [];
            $this->quantity = [];
            $this->discount_type = [];
            $this->item_discount = [];
        }

        $this->loadQuotationCustomerVehicles();
    }

    public function render() {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart', [
            'cart_items' => $cart_items
        ]);
    }

    private function isQuotationCart(): bool
    {
        return $this->cart_instance === 'quotation';
    }

    private function normalizeInstallationType($value): string
    {
        return $value === 'with_installation' ? 'with_installation' : 'item_only';
    }
    private function normalizeQuotationProductDiscountType($value): string
    {
        $discountType = strtolower(trim((string) $value));

        return in_array($discountType, ['fixed', 'percentage'], true) ? $discountType : 'fixed';
    }

    private function cartOptionsToArray($options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_object($options) && method_exists($options, 'toArray')) {
            return $options->toArray();
        }

        if (is_object($options)) {
            $decoded = json_decode(json_encode($options), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeQuotationCartOptions($cartItem, array $overrides = [], ?int $quantity = null): array
    {
        $options = $this->cartOptionsToArray($cartItem->options ?? []);
        $unitPrice = (float) ($options['unit_price'] ?? $cartItem->price ?? 0);

        if ($unitPrice <= 0) {
            $unitPrice = (float) ($cartItem->price ?? 0);
        }

        if ($unitPrice <= 0) {
            $unitPrice = (float) ($options['price'] ?? 0);
        }

        $rowQuantity = $quantity ?? (int) ($cartItem->qty ?? ($options['qty'] ?? 1));
        $rowQuantity = $rowQuantity > 0 ? $rowQuantity : 1;

        $normalized = array_merge($options, [
            'code' => $options['code'] ?? ($options['product_code'] ?? null),
            'product_code' => $options['product_code'] ?? ($options['code'] ?? null),
            'unit' => $options['unit'] ?? null,
            'stock' => $options['stock'] ?? 0,
            'reserved_stock' => $options['reserved_stock'] ?? null,
            'sellable_stock' => $options['sellable_stock'] ?? null,
            'stock_scope' => $options['stock_scope'] ?? null,
            'product_cost' => $options['product_cost'] ?? null,
            'warehouse_id' => $options['warehouse_id'] ?? null,
            'warehouse_name' => $options['warehouse_name'] ?? null,
            'product_tax' => $options['product_tax'] ?? 0,
            'unit_price' => $unitPrice,
            'product_discount' => $options['product_discount'] ?? 0,
            'product_discount_type' => $this->normalizeQuotationProductDiscountType($options['product_discount_type'] ?? null),
            'sub_total' => $unitPrice * $rowQuantity,
            'line_key' => $options['line_key'] ?? null,
            'installation_type' => $this->normalizeInstallationType($options['installation_type'] ?? 'item_only'),
            'customer_vehicle_id' => $options['customer_vehicle_id'] ?? null,
        ]);

        foreach ($overrides as $key => $value) {
            $normalized[$key] = $value;
        }

        $normalized['code'] = $normalized['code'] ?? ($normalized['product_code'] ?? null);
        $normalized['product_code'] = $normalized['product_code'] ?? ($normalized['code'] ?? null);
        $normalized['product_discount_type'] = $this->normalizeQuotationProductDiscountType($normalized['product_discount_type'] ?? null);
        $normalized['installation_type'] = $this->normalizeInstallationType($normalized['installation_type'] ?? 'item_only');

        return $normalized;
    }

    private function makeQuotationLineKey($productId): string
    {
        return 'quotation_' . (int) $productId . '_' . str_replace('-', '', (string) Str::uuid());
    }

    private function cartStateKey($cartItem, $lineKey = null): string
    {
        if ($this->isQuotationCart()) {
            return (string) ($lineKey ?: ($cartItem->options->line_key ?? $cartItem->rowId));
        }

        return (string) $cartItem->id;
    }

    private function findCartRow($rowId = null, $lineKey = null)
    {
        $content = Cart::instance($this->cart_instance)->content();

        if ($lineKey) {
            $row = $content->first(function ($item) use ($lineKey) {
                return (string) ($item->options->line_key ?? '') === (string) $lineKey;
            });

            if ($row) {
                return $row;
            }
        }

        if ($rowId) {
            return $content->first(function ($item) use ($rowId) {
                return (string) $item->rowId === (string) $rowId;
            });
        }

        return null;
    }

    private function loadQuotationCustomerVehicles(): void
    {
        if (!$this->isQuotationCart()) {
            return;
        }

        $customerId = (int) ($this->customer_id ?? 0);
        if ($customerId <= 0) {
            $this->customer_vehicles = collect();
            return;
        }

        $branchId = BranchContext::id();

        $this->customer_vehicles = CustomerVehicle::query()
            ->where('customer_id', $customerId)
            ->when(is_numeric($branchId), function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                        ->orWhere('branch_id', (int) $branchId);
                });
            })
            ->orderBy('car_plate')
            ->get();
    }

    private function quotationVehicleIds(): array
    {
        return $this->customer_vehicles
            ? $this->customer_vehicles->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    private function initializeQuotationMetadata($cartItem): void
    {
        if (!$this->isQuotationCart()) {
            return;
        }

        $lineKey = $this->cartStateKey($cartItem);
        $type = $this->normalizeInstallationType($cartItem->options->installation_type ?? 'item_only');

        $this->installation_type[$lineKey] = $type;
        $this->customer_vehicle_id[$lineKey] = $type === 'with_installation'
            ? ((int) ($cartItem->options->customer_vehicle_id ?? 0) ?: null)
            : null;
    }

    private function syncQuotationMetadataToCart($rowId, $lineKey = null): void
    {
        if (!$this->isQuotationCart()) {
            return;
        }

        $cartItem = $this->findCartRow($rowId, $lineKey);
        if (!$cartItem) {
            return;
        }

        $lineKey = $this->cartStateKey($cartItem, $lineKey);
        $type = $this->normalizeInstallationType($this->installation_type[$lineKey] ?? $cartItem->options->installation_type ?? 'item_only');
        $vehicleId = $type === 'with_installation'
            ? ((int) ($this->customer_vehicle_id[$lineKey] ?? $cartItem->options->customer_vehicle_id ?? 0) ?: null)
            : null;

        $this->installation_type[$lineKey] = $type;
        $this->customer_vehicle_id[$lineKey] = $vehicleId;

        $options = $this->normalizeQuotationCartOptions($cartItem, [
            'line_key' => $lineKey,
            'installation_type' => $type,
            'customer_vehicle_id' => $vehicleId,
        ], (int) ($cartItem->qty ?? 1));

        Cart::instance($this->cart_instance)->update($cartItem->rowId, ['options' => $options]);
    }

    public function productSelected($product) {
        $cart = Cart::instance($this->cart_instance);

        $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
            return $cartItem->id == $product['id'];
        });

        $total_stock = Mutation::with('warehouse')->where('product_id', $product['id'])
        ->latest()
        ->get()
        ->unique('warehouse_id')
        ->sortByDesc('stock_last')
        ->sum('stock_last');

        if ($exists->isNotEmpty()) {
            session()->flash('message', 'Product exists in the cart!');

            return;
        }

        $lineKey = $this->isQuotationCart() ? $this->makeQuotationLineKey($product['id']) : null;

        $options = [
            'product_discount'      => 0.00,
            'product_discount_type' => 'fixed',
            'sub_total'             => $this->calculate($product)['sub_total'],
            'code'                  => $product['product_code'],
            'product_code'          => $product['product_code'],
            'stock'                 => $total_stock,
            'unit'                  => $product['product_unit'],
            'product_tax'           => $this->calculate($product)['product_tax'],
            'unit_price'            => $this->calculate($product)['unit_price']
        ];

        if ($this->isQuotationCart()) {
            $options['line_key'] = $lineKey;
            $options['installation_type'] = 'item_only';
            $options['customer_vehicle_id'] = null;
        }

        $cart->add([
            'id'      => $product['id'],
            'name'    => $product['product_name'],
            'qty'     => 1,
            'price'   => $this->calculate($product)['price'],
            'weight'  => 1,
            'options' => $options
        ]);
        $this->global_qty = $cart->count();
        $stateKey = $this->isQuotationCart() ? $lineKey : (string) $product['id'];
        $this->check_quantity[$stateKey] = $total_stock;
        $this->quantity[$stateKey] = 1;
        $this->discount_type[$stateKey] = 'fixed';
        $this->item_discount[$stateKey] = 0;
        if ($this->isQuotationCart()) {
            $this->installation_type[$stateKey] = 'item_only';
            $this->customer_vehicle_id[$stateKey] = null;
        }

        // $this->updatedGlobalQuantity();
    }

    public function removeItem($row_id, $lineKey = null) {
        $cartItem = $this->findCartRow($row_id, $lineKey);
        if (!$cartItem) {
            session()->flash('message', 'Cart row not found. Please refresh the page.');
            return;
        }

        Cart::instance($this->cart_instance)->remove($cartItem->rowId);
    }

    public function updatedGlobalQuantity() {
        Cart::instance($this->cart_instance)->setGlobalQuantity((integer)$this->global_qty);
    }
    public function updatedGlobalTax() {
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }

    public function updatedGlobalDiscount() {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id, $lineKey = null) {
        $cart_item = $this->findCartRow($row_id, $lineKey);
        if (!$cart_item) {
            return;
        }

        $stateKey = $this->cartStateKey($cart_item, $lineKey);

        if  ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            if (($this->check_quantity[$stateKey] ?? 0) < ($this->quantity[$stateKey] ?? 0)) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($cart_item->rowId, $this->quantity[$stateKey] ?? 1);

        $cart_item = Cart::instance($this->cart_instance)->get($cart_item->rowId) ?: $this->findCartRow(null, $lineKey);
        if (!$cart_item) {
            return;
        }

        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $options = $this->cartOptionsToArray($cart_item->options);

        if ($this->isQuotationCart()) {
            $options = $this->normalizeQuotationCartOptions($cart_item, [
                'line_key' => $cart_item->options->line_key ?? $lineKey,
            ], (int) $cart_item->qty);
        } else {
            $options = array_merge($options, [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $options['code'] ?? null,
                'product_code'          => $options['product_code'] ?? $options['code'] ?? null,
                'stock'                 => $options['stock'] ?? 0,
                'unit'                  => $options['unit'] ?? null,
                'product_tax'           => $options['product_tax'] ?? 0,
                'unit_price'            => $options['unit_price'] ?? $cart_item->price,
                'product_discount'      => $options['product_discount'] ?? 0,
                'product_discount_type' => $options['product_discount_type'] ?? 'fixed',
            ]);
        }

        Cart::instance($this->cart_instance)->update($cart_item->rowId, [
            'options' => $options,
        ]);

        $this->syncQuotationMetadataToCart($cart_item->rowId, $lineKey);
    }

    public function finalizeCartBeforeSubmit($rows = [])
    {
        foreach ((array) $rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0 || !array_key_exists('quantity', $row)) {
                continue;
            }

            $lineKey = (string) ($row['line_key'] ?? '');
            $cartRow = $this->findCartRow($row['row_id'] ?? null, $lineKey ?: null);

            if (!$cartRow && !$this->isQuotationCart()) {
                $cartRow = Cart::instance($this->cart_instance)
                    ->content()
                    ->first(function ($item) use ($productId) {
                        return (int) $item->id === $productId;
                    });
            }

            if (!$cartRow) {
                continue;
            }

            $quantity = (int) ($row['quantity'] ?? 0);
            $stateKey = $this->cartStateKey($cartRow, $lineKey ?: null);
            $this->quantity[$stateKey] = $quantity > 0 ? $quantity : 1;

            if ($this->isQuotationCart()) {
                $this->installation_type[$stateKey] = $this->normalizeInstallationType($row['installation_type'] ?? 'item_only');
                $this->customer_vehicle_id[$stateKey] = $this->installation_type[$stateKey] === 'with_installation'
                    ? ((int) ($row['customer_vehicle_id'] ?? 0) ?: null)
                    : null;
            }

            $this->updateQuantity($cartRow->rowId, $productId, $lineKey ?: null);
        }
    }

    public function updatedDiscountType($value, $name) {
        $this->item_discount[$name] = 0;
    }

    public function updatedInstallationType($value, $name) {
        if (!$this->isQuotationCart()) {
            return;
        }

        $this->installation_type[$name] = $this->normalizeInstallationType($value);
        if ($this->installation_type[$name] !== 'with_installation') {
            $this->customer_vehicle_id[$name] = null;
        }

        $this->syncQuotationMetadataToCart(null, $name);
    }

    public function updatedCustomerVehicleId($value, $name) {
        if (!$this->isQuotationCart()) {
            return;
        }

        $vehicleId = (int) $value;
        $this->customer_vehicle_id[$name] = in_array($vehicleId, $this->quotationVehicleIds(), true) ? $vehicleId : null;
        $this->syncQuotationMetadataToCart(null, $name);
    }

    public function onQuotationCustomerChanged($customerId) {
        if (!$this->isQuotationCart()) {
            return;
        }

        $this->customer_id = (int) $customerId ?: null;
        $this->loadQuotationCustomerVehicles();
        $allowedVehicleIds = $this->quotationVehicleIds();

        foreach ((array) $this->customer_vehicle_id as $lineKey => $vehicleId) {
            if ($vehicleId && !in_array((int) $vehicleId, $allowedVehicleIds, true)) {
                $this->customer_vehicle_id[$lineKey] = null;
                $this->syncQuotationMetadataToCart(null, $lineKey);
            }
        }
    }

    public function discountModalRefresh($product_id, $row_id, $lineKey = null) {
        $this->updateQuantity($row_id, $product_id, $lineKey);
    }

    public function setProductDiscount($row_id, $product_id, $lineKey = null) {
        $cart_item = $this->findCartRow($row_id, $lineKey);
        if (!$cart_item) {
            session()->flash('message', 'Cart row not found. Please refresh the page.');
            return;
        }

        $stateKey = $this->cartStateKey($cart_item, $lineKey);

        if ($this->discount_type[$stateKey] == 'fixed') {
            $discount_amount = ($cart_item->price + $cart_item->options->product_discount) - $this->item_discount[$stateKey];
            Cart::instance($this->cart_instance)
                ->update($cart_item->rowId, [
                    'price' => $this->item_discount[$stateKey]
                ]);

            $updatedItem = Cart::instance($this->cart_instance)->get($cart_item->rowId) ?: $this->findCartRow(null, $lineKey);
            if (!$updatedItem) {
                return;
            }

            $this->updateCartOptions($updatedItem->rowId, $product_id, $updatedItem, $discount_amount, $lineKey);
        } elseif ($this->discount_type[$stateKey] == 'percentage') {
            $discount_amount = ($cart_item->price + $cart_item->options->product_discount) * ($this->item_discount[$stateKey] / 100);

            Cart::instance($this->cart_instance)
                ->update($cart_item->rowId, [
                    'price' => ($cart_item->price + $cart_item->options->product_discount) - $discount_amount
                ]);

            $updatedItem = Cart::instance($this->cart_instance)->get($cart_item->rowId) ?: $this->findCartRow(null, $lineKey);
            if (!$updatedItem) {
                return;
            }

            $this->updateCartOptions($updatedItem->rowId, $product_id, $updatedItem, $discount_amount, $lineKey);
        }

        session()->flash('discount_message' . $stateKey, 'Discount added to the product!');
    }

    public function calculate($product) {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;

        if ($product['product_tax_type'] == 1) {
            $price = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
            $unit_price = $product['product_price'];
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
        } elseif ($product['product_tax_type'] == 2) {
            $price = $product['product_price'];
            $unit_price = $product['product_price'] - ($product['product_price'] * ($product['product_order_tax'] / 1));
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $product['product_price'];
        } else {
            $price = $product['product_price'];
            $unit_price = $product['product_price'];
            $product_tax = 0.00;
            $sub_total = $product['product_price'];
        }

        return ['price' => $price, 'unit_price' => $unit_price, 'product_tax' => $product_tax, 'sub_total' => $sub_total];
    }

    public function duplicateQuotationRow($row_id, $lineKey = null)
    {
        if (!$this->isQuotationCart()) {
            return;
        }

        $source = $this->findCartRow($row_id, $lineKey);
        if (!$source) {
            session()->flash('message', 'Cart row not found. Please refresh the page.');
            return;
        }

        $newLineKey = $this->makeQuotationLineKey($source->id);
        $options = $this->normalizeQuotationCartOptions($source, [
            'line_key' => $newLineKey,
            'product_discount' => 0,
            'product_discount_type' => 'fixed',
            'installation_type' => 'item_only',
            'customer_vehicle_id' => null,
            'sub_total' => (float) (($source->options->unit_price ?? $source->price) > 0
                ? ($source->options->unit_price ?? $source->price)
                : 0),
        ], 1);

        $unitPrice = (float) ($options['unit_price'] ?? $source->price ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = (float) ($source->price ?? 0);
        }
        $options['sub_total'] = $unitPrice;

        Cart::instance($this->cart_instance)->add([
            'id' => $source->id,
            'name' => $source->name,
            'qty' => 1,
            'price' => $unitPrice,
            'weight' => $source->weight,
            'options' => $options,
        ]);

        $this->quantity[$newLineKey] = 1;
        $this->check_quantity[$newLineKey] = $options['stock'] ?? 0;
        $this->discount_type[$newLineKey] = 'fixed';
        $this->item_discount[$newLineKey] = 0;
        $this->installation_type[$newLineKey] = 'item_only';
        $this->customer_vehicle_id[$newLineKey] = null;
        $this->global_qty = Cart::instance($this->cart_instance)->count();
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount, $lineKey = null) {
        $freshItem = $this->findCartRow($row_id, $lineKey);
        if (!$freshItem) {
            return;
        }

        $stateKey = $this->cartStateKey($freshItem, $lineKey);
        $installationType = $this->normalizeInstallationType($this->installation_type[$stateKey] ?? $freshItem->options->installation_type ?? 'item_only');
        $vehicleId = $installationType === 'with_installation'
            ? ((int) ($this->customer_vehicle_id[$stateKey] ?? $freshItem->options->customer_vehicle_id ?? 0) ?: null)
            : null;

        $options = $this->cartOptionsToArray($freshItem->options);

        if ($this->isQuotationCart()) {
            $options = $this->normalizeQuotationCartOptions($freshItem, [
                'product_discount' => $discount_amount,
                'product_discount_type' => $this->normalizeQuotationProductDiscountType($this->discount_type[$stateKey] ?? $freshItem->options->product_discount_type ?? 'fixed'),
                'line_key' => $freshItem->options->line_key ?? $lineKey,
                'installation_type' => $installationType,
                'customer_vehicle_id' => $vehicleId,
            ], (int) $freshItem->qty);
        } else {
            $options = array_merge($options, [
                'sub_total'             => $freshItem->price * $freshItem->qty,
                'code'                  => $freshItem->options->code,
                'product_code'          => $freshItem->options->product_code ?? $freshItem->options->code,
                'stock'                 => $freshItem->options->stock,
                'unit'                  => $freshItem->options->unit,
                'product_tax'           => $freshItem->options->product_tax,
                'unit_price'            => $freshItem->options->unit_price,
                'product_discount'      => $discount_amount,
                'product_discount_type' => $this->discount_type[$stateKey],
                'line_key'              => $freshItem->options->line_key ?? null,
                'installation_type'     => $installationType,
                'customer_vehicle_id'   => $vehicleId,
            ]);
        }

        Cart::instance($this->cart_instance)->update($freshItem->rowId, ['options' => $options]);
    }
}
