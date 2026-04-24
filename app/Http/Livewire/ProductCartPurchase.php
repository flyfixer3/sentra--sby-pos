<?php

namespace App\Http\Livewire;

use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class ProductCartPurchase extends Component
{
    public $listeners = [
        'productSelected',
        'discountModalRefresh',
        'purchaseWarehouseChanged',
    ];

    public $cart_instance;
    public $global_discount;
    public $global_discount_type = 'percentage';
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee;

    public $quantity;
    public $warehouse_id;
    public $loading_warehouse;

    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $gross_price;
    public $item_cost_konsyinasi;
    public $data;
    public $lock_purchase_price_edit = false;

    public $stock_mode = 'branch_all';

    public function mount($cartInstance, $data = null, $loading_warehouse = null, $stock_mode = 'branch_all')
    {
        $this->cart_instance = $cartInstance;

        $this->stock_mode = in_array($stock_mode, ['branch_all', 'warehouse'], true)
            ? $stock_mode
            : 'branch_all';

        $this->loading_warehouse = null;
        if (is_numeric($loading_warehouse)) {
            $this->loading_warehouse = Warehouse::find((int) $loading_warehouse);
        } else {
            $this->loading_warehouse = $loading_warehouse;
        }

        if (!$this->loading_warehouse) {
            $branchId = $this->resolveActiveBranchIdFromSessionOrCart();
            $warehouse = null;

            if (!empty($branchId) && $branchId !== 'all') {
                $warehouse = Warehouse::where('branch_id', (int) $branchId)
                    ->where('is_main', 1)
                    ->first();

                if (!$warehouse) {
                    $warehouse = Warehouse::where('branch_id', (int) $branchId)->first();
                }
            }

            if (!$warehouse) {
                $warehouse = Warehouse::where('is_main', 1)->first() ?? Warehouse::first();
            }

            $this->loading_warehouse = $warehouse;
        }

        if ($data) {
            $this->data = $data;

            $this->global_discount_type = ((float) ($data->discount_percentage ?? 0) > 0 || (float) ($data->discount_amount ?? 0) <= 0)
                ? 'percentage'
                : 'fixed';
            $this->global_discount = $this->global_discount_type === 'fixed'
                ? (float) ($data->discount_amount ?? 0)
                : (float) ($data->discount_percentage ?? 0);
            $this->global_tax = $data->tax_percentage;
            $this->global_qty = Cart::instance($this->cart_instance)->count();
            $this->shipping = $data->shipping_amount;
            $this->platform_fee = $data->fee_amount;

            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();

            $cart_items = Cart::instance($this->cart_instance)->content();

            foreach ($cart_items as $cart_item) {
                $productId = (int) $cart_item->id;

                $this->check_quantity[$productId] = (int) ($cart_item->options->stock ?? 0);
                $this->quantity[$productId] = (int) $cart_item->qty;

                if ($this->stock_mode === 'warehouse') {
                    $this->warehouse_id[$productId] = (int) ($cart_item->options->warehouse_id ?? ($this->loading_warehouse->id ?? 0));
                } else {
                    $this->warehouse_id[$productId] = !empty($cart_item->options->warehouse_id)
                        ? (int) $cart_item->options->warehouse_id
                        : null;
                }

                $this->discount_type[$productId] = $cart_item->options->product_discount_type ?? 'fixed';
                $this->item_cost_konsyinasi[$productId] = (float) ($cart_item->options->product_cost ?? 0);

                $grossPrice = $this->resolveGrossPrice($cart_item);
                $this->gross_price[$productId] = $grossPrice;

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$productId] = (float) ($cart_item->options->product_discount ?? 0);
                } else {
                    $discountAmount = (float) ($cart_item->options->product_discount ?? 0);

                    if ($grossPrice > 0) {
                        $this->item_discount[$productId] = round(($discountAmount / $grossPrice) * 100, 2);
                    } else {
                        $this->item_discount[$productId] = 0;
                    }
                }
            }
        } else {
            $this->global_discount = 0;
            $this->global_discount_type = 'percentage';
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0.00;
            $this->platform_fee = 0.00;

            $this->check_quantity = [];
            $this->quantity = [];
            $this->warehouse_id = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->gross_price = [];
            $this->item_cost_konsyinasi = [];
        }

        /**
         * Initial load:
         * samakan hasilnya dengan logic tombol centang,
         * jadi row cart sudah “fully refreshed” sejak awal.
         */
        $cartItems = Cart::instance($this->cart_instance)->content();
        foreach ($cartItems as $row) {
            $this->refreshCartRowState($row->rowId, (int) $row->id);
        }

        $this->global_qty = Cart::instance($this->cart_instance)->count();
        $this->syncQuantityDefaults();
        $this->syncGlobalDiscount();
    }

    private function resolveActiveBranchIdFromSessionOrCart()
    {
        $activeBranch = session('active_branch');

        if (!empty($activeBranch) && $activeBranch !== 'all') {
            return (int) $activeBranch;
        }

        $firstRow = Cart::instance($this->cart_instance)->content()->first();
        if ($firstRow && !empty($firstRow->options->branch_id)) {
            return (int) $firstRow->options->branch_id;
        }

        if ($this->data && !empty($this->data->branch_id)) {
            return (int) $this->data->branch_id;
        }

        return null;
    }

    private function getProductCode(int $productId): string
    {
        $product = Product::select('product_code')->find($productId);
        return $product?->product_code ?? 'UNKNOWN';
    }

    private function getProductUnit(int $productId): string
    {
        $product = Product::select('product_unit')->find($productId);
        return $product?->product_unit ?? 'Unit';
    }

    private function calculateStockContext(int $productId, ?int $warehouseId = null): array
    {
        if ($this->stock_mode === 'warehouse' && !empty($warehouseId)) {
            $stockLast = $this->getStockLastByWarehouse($productId, (int) $warehouseId);

            return [
                'stock' => (int) $stockLast,
                'stock_scope' => 'warehouse',
                'warehouse_id' => (int) $warehouseId,
            ];
        }

        $stockLast = $this->getStockLastAllWarehousesInActiveBranch($productId);

        return [
            'stock' => (int) $stockLast,
            'stock_scope' => 'branch',
            'warehouse_id' => null,
        ];
    }

    private function mergeOptions($row, array $overrides = []): array
    {
        $existingOptions = (array) $row->options;

        if (empty($existingOptions['code'])) {
            $existingOptions['code'] = $this->getProductCode((int) $row->id);
        }

        if (!array_key_exists('branch_id', $existingOptions) || empty($existingOptions['branch_id'])) {
            $existingOptions['branch_id'] = $this->resolveActiveBranchIdFromSessionOrCart();
        }

        if (empty($existingOptions['unit'])) {
            $existingOptions['unit'] = $this->getProductUnit((int) $row->id);
        }

        if (!array_key_exists('purchase_detail_id', $existingOptions)) {
            $existingOptions['purchase_detail_id'] = null;
        }

        return array_merge($existingOptions, $overrides);
    }

    private function syncQuantityDefaults(): void
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        foreach ($cart_items as $row) {
            $pid = (int) $row->id;
            if ($pid <= 0) {
                continue;
            }

            if (!isset($this->quantity[$pid]) || $this->quantity[$pid] === null || $this->quantity[$pid] === '') {
                $this->quantity[$pid] = (int) $row->qty;
            }

            if (!isset($this->check_quantity[$pid])) {
                $this->check_quantity[$pid] = (int) ($row->options->stock ?? 0);
            }

            if (!array_key_exists($pid, $this->warehouse_id)) {
                if ($this->stock_mode === 'warehouse') {
                    $this->warehouse_id[$pid] = (int) ($row->options->warehouse_id ?? ($this->loading_warehouse->id ?? 0));
                } else {
                    $this->warehouse_id[$pid] = !empty($row->options->warehouse_id)
                        ? (int) $row->options->warehouse_id
                        : null;
                }
            }

            if (!isset($this->discount_type[$pid]) || !$this->discount_type[$pid]) {
                $this->discount_type[$pid] = (string) ($row->options->product_discount_type ?? 'fixed');
            }

            if (!isset($this->item_discount[$pid])) {
                $grossPrice = $this->resolveGrossPrice($row);
                $this->gross_price[$pid] = $grossPrice;

                if (($row->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$pid] = (float) ($row->options->product_discount ?? 0);
                } else {
                    $discountAmount = (float) ($row->options->product_discount ?? 0);

                    if ($grossPrice > 0) {
                        $this->item_discount[$pid] = round(($discountAmount / $grossPrice) * 100, 2);
                    } else {
                        $this->item_discount[$pid] = 0;
                    }
                }
            }

            if (!isset($this->item_cost_konsyinasi[$pid])) {
                $this->item_cost_konsyinasi[$pid] = (float) ($row->options->product_cost ?? 0);
            }

            if (!isset($this->gross_price[$pid])) {
                $this->gross_price[$pid] = $this->resolveGrossPrice($row);
            }
        }
    }

    private function resolveGrossPrice($row): float
    {
        $gross = (float) ($row->options->unit_price ?? 0);
        if ($gross <= 0) {
            $gross = (float) ($row->price ?? 0) + (float) ($row->options->product_discount ?? 0);
        }

        return max(0, round($gross, 2));
    }

    private function calculateDiscountAmount(float $grossPrice, string $discountType, float $discountInput): float
    {
        $grossPrice = max(0, $grossPrice);
        $discountInput = max(0, $discountInput);

        if ($discountType === 'percentage') {
            $discountInput = min(100, $discountInput);
            return round($grossPrice * ($discountInput / 100), 2);
        }

        return round(min($discountInput, $grossPrice), 2);
    }

    private function calculateNetPrice(float $grossPrice, float $discountAmount): float
    {
        return round(max(0, $grossPrice - $discountAmount), 2);
    }

    private function resolveDiscountPricing($row, int $productId, ?float $grossPrice = null): array
    {
        $grossPrice = $grossPrice !== null
            ? max(0, round($grossPrice, 2))
            : $this->resolveGrossPrice($row);

        $discountType = (string) ($this->discount_type[$productId] ?? ($row->options->product_discount_type ?? 'fixed'));
        $discountType = in_array($discountType, ['fixed', 'percentage'], true) ? $discountType : 'fixed';

        if (array_key_exists($productId, (array) $this->item_discount)) {
            $discountInput = (float) ($this->item_discount[$productId] ?? 0);
        } elseif ($discountType === 'percentage') {
            $storedDiscount = (float) ($row->options->product_discount ?? 0);
            $discountInput = $grossPrice > 0 ? round(($storedDiscount / $grossPrice) * 100, 2) : 0;
        } else {
            $discountInput = (float) ($row->options->product_discount ?? 0);
        }

        $discountAmount = $this->calculateDiscountAmount($grossPrice, $discountType, $discountInput);
        $netPrice = $this->calculateNetPrice($grossPrice, $discountAmount);

        return [
            'type' => $discountType,
            'input' => $discountType === 'percentage'
                ? min(100, max(0, $discountInput))
                : $discountAmount,
            'amount' => $discountAmount,
            'gross' => $grossPrice,
            'net' => $netPrice,
        ];
    }

    private function findCartRow($row_id, $product_id = null)
    {
        $cart = Cart::instance($this->cart_instance);
        $rowId = (string) $row_id;

        $row = $cart->content()->first(function ($item) use ($rowId) {
            return (string) $item->rowId === $rowId;
        });

        if ($row || !$product_id) {
            return $row;
        }

        return $cart->content()->first(function ($item) use ($product_id) {
            return (int) $item->id === (int) $product_id;
        });
    }

    private function cartSubtotal(): float
    {
        return (float) Cart::instance($this->cart_instance)->content()->sum(function ($row) {
            return (float) ($row->price ?? 0) * (int) ($row->qty ?? 0);
        });
    }

    private function syncGlobalDiscount(): void
    {
        $discountInput = max(0, (float) ($this->global_discount ?? 0));

        if ($this->global_discount_type === 'fixed') {
            $subtotal = $this->cartSubtotal();
            $discountAmount = min($discountInput, $subtotal);
            $discountPercent = $subtotal > 0 ? (($discountAmount / $subtotal) * 100) : 0;
            Cart::instance($this->cart_instance)->setGlobalDiscount($discountPercent);
            return;
        }

        Cart::instance($this->cart_instance)->setGlobalDiscount(min(100, $discountInput));
    }

    public function updatedGlobalDiscountType()
    {
        $this->global_discount = 0;
        $this->syncGlobalDiscount();
    }

    public function render()
    {
        $this->syncQuantityDefaults();

        $cart_items = Cart::instance($this->cart_instance)->content();
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        return view('livewire.product-cart-purchase', [
            'cart_items' => $cart_items
        ]);
    }

    public function purchaseWarehouseChanged($warehouseId)
    {
        $warehouseId = (int) $warehouseId;
        if ($warehouseId <= 0) {
            return;
        }

        $activeBranch = $this->resolveActiveBranchIdFromSessionOrCart();

        if (!empty($activeBranch) && $activeBranch !== 'all') {
            $exists = Warehouse::where('id', $warehouseId)
                ->where('branch_id', (int) $activeBranch)
                ->exists();

            if (!$exists) {
                session()->flash('message', 'Selected warehouse does not belong to the active branch.');
                return;
            }
        }

        $wh = Warehouse::find($warehouseId);
        if (!$wh) {
            return;
        }

        $this->loading_warehouse = $wh;
        $this->stock_mode = 'warehouse';

        $this->syncCartToCurrentWarehouse();
    }

    private function syncCartToCurrentWarehouse(): void
    {
        $warehouseId = (int) ($this->loading_warehouse ? $this->loading_warehouse->id : 0);

        if ($warehouseId <= 0 && $this->stock_mode === 'warehouse') {
            return;
        }

        $cart = Cart::instance($this->cart_instance);
        $items = $cart->content();

        foreach ($items as $row) {
            $productId = (int) $row->id;

            $context = $this->calculateStockContext(
                $productId,
                $this->stock_mode === 'warehouse' ? $warehouseId : null
            );

            $grossPrice = $this->resolveGrossPrice($row);
            $discountAmount = (float) ($row->options->product_discount ?? 0);
            $netPrice = $this->calculateNetPrice($grossPrice, $discountAmount);
            $subTotal = $netPrice * (int) ($row->qty ?? 0);

            $cart->update($row->rowId, [
                'price' => $netPrice,
                'options' => $this->mergeOptions($row, [
                    'sub_total'    => $subTotal,
                    'stock'        => $context['stock'],
                    'stock_scope'  => $context['stock_scope'],
                    'warehouse_id' => $context['warehouse_id'],
                    'unit_price'   => $grossPrice,
                ])
            ]);

            $this->check_quantity[$productId] = $context['stock'];

            if ($this->stock_mode === 'warehouse') {
                $this->warehouse_id[$productId] = (int) $context['warehouse_id'];
            } else {
                $this->warehouse_id[$productId] = null;
            }
        }

        $this->global_qty = $cart->count();
        $this->syncGlobalDiscount();
    }

    private function refreshCartRowState($row_id, $product_id): void
    {
        $cart_item = $this->findCartRow($row_id, $product_id);
        if (!$cart_item) {
            return;
        }

        $warehouseId = null;
        if ($this->stock_mode === 'warehouse') {
            $warehouseId = (int) ($cart_item->options->warehouse_id ?? ($this->loading_warehouse->id ?? 0));
        }

        $context = $this->calculateStockContext((int) $product_id, $warehouseId);

        $pricing = $this->resolveDiscountPricing($cart_item, (int) $product_id);
        $grossPrice = $pricing['gross'];
        $discountAmount = $pricing['amount'];
        $netPrice = $pricing['net'];
        $subTotal = $netPrice * (int) ($cart_item->qty ?? 0);

        Cart::instance($this->cart_instance)->update($cart_item->rowId, [
            'price' => $netPrice,
            'options' => $this->mergeOptions($cart_item, [
                'sub_total'    => $subTotal,
                'stock'        => $context['stock'],
                'stock_scope'  => $context['stock_scope'],
                'warehouse_id' => $context['warehouse_id'],
                'unit_price'   => $grossPrice,
                'product_discount' => $discountAmount,
                'product_discount_type' => $pricing['type'],
            ])
        ]);

        $this->check_quantity[$product_id] = $context['stock'];
        $this->discount_type[$product_id] = $pricing['type'];
        $this->item_discount[$product_id] = $pricing['input'];
        $this->gross_price[$product_id] = $grossPrice;
    }

    private function normalizeCartRowSubtotals(): void
    {
        $cart = Cart::instance($this->cart_instance);

        foreach ($cart->content() as $row) {
            $grossPrice = $this->resolveGrossPrice($row);
            $discountAmount = (float) ($row->options->product_discount ?? 0);
            $netPrice = $this->calculateNetPrice($grossPrice, $discountAmount);
            $expectedSubTotal = round($netPrice * (int) ($row->qty ?? 0), 2);
            $storedSubTotal = round((float) ($row->options->sub_total ?? 0), 2);

            if ($storedSubTotal === $expectedSubTotal && round((float) $row->price, 2) === round($netPrice, 2)) {
                continue;
            }

            $options = (array) $row->options;
            $options['sub_total'] = $expectedSubTotal;
            $options['unit_price'] = $grossPrice;

            $cart->update($row->rowId, [
                'price' => $netPrice,
                'options' => $options,
            ]);
        }
    }

    private function getStockLastByWarehouse(int $productId, int $warehouseId): int
    {
        $mutation = Mutation::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->latest()
            ->first();

        return $mutation ? (int) $mutation->stock_last : 0;
    }

    private function getStockLastAllWarehousesInActiveBranch(int $productId): int
    {
        $branchId = $this->resolveActiveBranchIdFromSessionOrCart();

        if (empty($branchId) || $branchId === 'all') {
            return 0;
        }

        $warehouseIds = Warehouse::where('branch_id', (int) $branchId)->pluck('id')->toArray();
        if (empty($warehouseIds)) {
            return 0;
        }

        $sum = 0;
        foreach ($warehouseIds as $wid) {
            $sum += $this->getStockLastByWarehouse((int) $productId, (int) $wid);
        }

        return (int) $sum;
    }

    public function productSelected($product)
    {
        $cart = Cart::instance($this->cart_instance);

        $exists = $cart->search(function ($cartItem) use ($product) {
            return (int) $cartItem->id === (int) $product['id'];
        });

        if ($exists->isNotEmpty()) {
            session()->flash('message', 'Product exists in the cart!');
            return;
        }

        $warehouseId = (int) ($this->loading_warehouse ? $this->loading_warehouse->id : 0);

        if ($this->stock_mode === 'warehouse' && $warehouseId <= 0) {
            session()->flash('message', 'Default warehouse is not set. Please create/select a warehouse for this branch first.');
            return;
        }

        $context = $this->calculateStockContext(
            (int) $product['id'],
            $this->stock_mode === 'warehouse' ? $warehouseId : null
        );

        $calc = $this->calculate($product);

        $cart->add([
            'id'      => $product['id'],
            'name'    => $product['product_name'],
            'qty'     => 1,
            'price'   => $calc['price'],
            'weight'  => 1,
            'options' => [
                'purchase_detail_id'    => null,
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => $calc['sub_total'],
                'code'                  => $product['product_code'],
                'stock'                 => $context['stock'],
                'stock_scope'           => $context['stock_scope'],
                'unit'                  => $product['product_unit'],
                'warehouse_id'          => $context['warehouse_id'],
                'product_tax'           => $calc['product_tax'],
                'product_cost'          => $calc['product_cost'],
                'unit_price'            => $calc['unit_price'],
                'branch_id'             => $this->resolveActiveBranchIdFromSessionOrCart(),
            ]
        ]);

        $this->global_qty = $cart->count();
        $this->check_quantity[$product['id']] = $context['stock'];
        $this->quantity[$product['id']] = 1;
        $this->warehouse_id[$product['id']] = $context['warehouse_id'];
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = 0;
        $this->gross_price[$product['id']] = (float) $calc['unit_price'];
        $this->item_cost_konsyinasi[$product['id']] = (float) ($calc['product_cost'] ?? 0);
        $this->syncGlobalDiscount();
    }

    public function removeItem($row_id)
    {
        $row = $this->findCartRow($row_id);
        if (!$row) {
            $this->global_qty = Cart::instance($this->cart_instance)->count();
            $this->syncGlobalDiscount();
            return;
        }

        Cart::instance($this->cart_instance)->remove($row->rowId);
        $this->global_qty = Cart::instance($this->cart_instance)->count();
        $this->syncGlobalDiscount();
    }

    public function updatedGlobalQuantity()
    {
        Cart::instance($this->cart_instance)->setGlobalQuantity((int) $this->global_qty);
    }

    public function updatedGlobalTax()
    {
        Cart::instance($this->cart_instance)->setGlobalTax((int) $this->global_tax);
    }

    public function updatedGlobalDiscount()
    {
        $this->syncGlobalDiscount();
    }

    public function updateQuantity($row_id, $product_id)
    {
        $cart_item = $this->findCartRow($row_id, $product_id);
        if (!$cart_item) {
            $this->global_qty = Cart::instance($this->cart_instance)->count();
            $this->syncGlobalDiscount();
            return;
        }

        $quantity = (int) ($this->quantity[$product_id] ?? $cart_item->qty ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
            $this->quantity[$product_id] = $quantity;
        }

        if ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            if ((int) ($this->check_quantity[$product_id] ?? 0) < $quantity) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($cart_item->rowId, $quantity);
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $this->refreshCartRowState($cart_item->rowId, (int) $product_id);
        $this->syncGlobalDiscount();
    }

    public function finalizeCartBeforeSubmit($rows = [])
    {
        foreach ((array) $rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $cartRow = $this->findCartRow($row['row_id'] ?? null, $productId);
            if (!$cartRow) {
                continue;
            }

            if (array_key_exists('quantity', $row)) {
                $quantity = (int) ($row['quantity'] ?? 0);
                $this->quantity[$productId] = $quantity > 0 ? $quantity : 1;
            }

            if (array_key_exists('gross_price', $row)) {
                $this->gross_price[$productId] = max(0, (float) ($row['gross_price'] ?? 0));
            }

            if (array_key_exists('discount_type', $row)) {
                $discountType = (string) ($row['discount_type'] ?? 'fixed');
                $this->discount_type[$productId] = in_array($discountType, ['fixed', 'percentage'], true)
                    ? $discountType
                    : 'fixed';
            }

            if (array_key_exists('item_discount', $row)) {
                $this->item_discount[$productId] = max(0, (float) ($row['item_discount'] ?? 0));
            }

            $this->updateQuantity($cartRow->rowId, $productId);
            $cartRow = $this->findCartRow($cartRow->rowId, $productId);

            if ($cartRow) {
                $this->updatePricing($cartRow->rowId, $productId);
            }
        }

        $this->global_qty = Cart::instance($this->cart_instance)->count();
        $this->syncGlobalDiscount();
    }

    public function changeDiscountType($row_id, $product_id, $type)
    {
        $productId = (int) $product_id;
        $type = in_array($type, ['fixed', 'percentage'], true) ? $type : 'fixed';
        $this->discount_type[$productId] = $type;

        $row = $this->findCartRow($row_id, $productId);

        if (!$row) {
            $this->item_discount[$productId] = 0;
            return;
        }

        $grossPrice = $this->resolveGrossPrice($row);
        $discountAmount = (float) ($row->options->product_discount ?? 0);

        if ($type === 'fixed') {
            $this->item_discount[$productId] = $discountAmount;
        } else {
            $this->item_discount[$productId] = $grossPrice > 0
                ? round(($discountAmount / $grossPrice) * 100, 2)
                : 0;
        }

        $this->updatePricing($row->rowId, $productId);
    }

    public function discountModalRefresh($product_id, $row_id)
    {
        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id)
    {
        $this->updatePricing($row_id, $product_id);
    }

    public function updatePricing($row_id, $product_id)
    {
        $cart_item = $this->findCartRow($row_id, $product_id);

        if (!$cart_item) {
            session()->flash('discount_message' . $product_id, 'Cart item not found.');
            return;
        }

        if ($this->cart_instance === 'purchase' && $this->lock_purchase_price_edit) {
            session()->flash('discount_message' . $product_id, 'Purchase item price is locked because the linked Purchase Delivery is already partial.');
            return;
        }

        $grossPrice = max(0, (float) ($this->gross_price[$product_id] ?? $this->resolveGrossPrice($cart_item)));
        $pricing = $this->resolveDiscountPricing($cart_item, (int) $product_id, $grossPrice);
        $discountType = $pricing['type'];
        $discountAmount = $pricing['amount'];
        $netPrice = $pricing['net'];

        $this->discount_type[$product_id] = $discountType;
        $this->item_discount[$product_id] = $pricing['input'];

        $this->gross_price[$product_id] = $grossPrice;

        $this->updateCartOptions(
            $cart_item->rowId,
            $product_id,
            $cart_item,
            $discountAmount,
            $grossPrice,
            $this->warehouse_id[$product_id] ?? (int) ($cart_item->options->warehouse_id ?? 0),
            $grossPrice,
            $netPrice
        );

        $this->syncGlobalDiscount();
        session()->flash('discount_message' . $product_id, 'Purchase pricing updated successfully!');
    }

    public function calculate($product)
    {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;
        $product_cost = 0;

        return [
            'price' => $price,
            'unit_price' => $unit_price,
            'product_tax' => $product_tax,
            'product_cost' => $product_cost,
            'sub_total' => $sub_total
        ];
    }

    public function updateCartOptions(
        $row_id,
        $product_id,
        $cart_item,
        $discount_amount,
        $item_cost_konsyinasi,
        $warehouse_id,
        $overrideUnitPrice = null,
        $overrideRowPrice = null
    ) {
        $context = $this->calculateStockContext(
            (int) $product_id,
            $this->stock_mode === 'warehouse' ? (int) $warehouse_id : null
        );

        $freshItem = $this->findCartRow($row_id, $product_id);
        if (!$freshItem) {
            return;
        }

        $finalRowPrice = $overrideRowPrice !== null
            ? (float) $overrideRowPrice
            : (float) $freshItem->price;

        $finalUnitPrice = $overrideUnitPrice !== null
            ? (float) $overrideUnitPrice
            : (float) ($freshItem->options->unit_price ?? $finalRowPrice);

        $finalUnitPrice = max(0, round($finalUnitPrice, 2));
        $finalRowPrice = max(0, round($finalRowPrice, 2));

        Cart::instance($this->cart_instance)->update($freshItem->rowId, [
            'price' => $finalRowPrice,
        ]);

        $updatedItem = $this->findCartRow($freshItem->rowId, $product_id);
        if (!$updatedItem) {
            return;
        }

        $finalQty = (int) ($updatedItem->qty ?? 0);
        $finalSubTotal = round($finalRowPrice * $finalQty, 2);

        Cart::instance($this->cart_instance)->update($updatedItem->rowId, [
            'options' => $this->mergeOptions($updatedItem, [
                'sub_total'             => $finalSubTotal,
                'stock'                 => $context['stock'],
                'stock_scope'           => $context['stock_scope'],
                'warehouse_id'          => $context['warehouse_id'],
                'product_cost'          => (float) $item_cost_konsyinasi,
                'unit_price'            => (float) $finalUnitPrice,
                'product_discount'      => (float) $discount_amount,
                'product_discount_type' => $this->discount_type[$product_id] ?? 'fixed',
                'code'                  => $updatedItem->options->code ?? $this->getProductCode((int) $product_id),
                'unit'                  => $updatedItem->options->unit ?? $this->getProductUnit((int) $product_id),
                'branch_id'             => $updatedItem->options->branch_id ?? $this->resolveActiveBranchIdFromSessionOrCart(),
            ])
        ]);

        $this->check_quantity[$product_id] = $context['stock'];
    }
}
