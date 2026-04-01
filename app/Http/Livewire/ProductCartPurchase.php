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

            $this->global_discount = $data->discount_percentage;
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

                $currentUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
                if ($currentUnitPrice <= 0) {
                    $currentUnitPrice = (float) ($cart_item->price ?? 0);
                }

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$productId] = $currentUnitPrice;
                } else {
                    $discountAmount = (float) ($cart_item->options->product_discount ?? 0);

                    if ($currentUnitPrice > 0) {
                        $this->item_discount[$productId] = round(($discountAmount / $currentUnitPrice) * 100, 2);
                    } else {
                        $this->item_discount[$productId] = 0;
                    }
                }
            }
        } else {
            $this->global_discount = 0;
            $this->global_tax = 0;
            $this->global_qty = 0;
            $this->shipping = 0.00;
            $this->platform_fee = 0.00;

            $this->check_quantity = [];
            $this->quantity = [];
            $this->warehouse_id = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->item_cost_konsyinasi = [];
        }

        /**
         * Initial load:
         * - jangan overwrite stock/code/warehouse context hasil preload controller
         * - cukup benarkan subtotal yang stale
         */
        $this->normalizeCartRowSubtotals();
        $this->syncQuantityDefaults();
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
                $currentUnitPrice = (float) ($row->options->unit_price ?? 0);
                if ($currentUnitPrice <= 0) {
                    $currentUnitPrice = (float) ($row->price ?? 0);
                }

                if (($row->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$pid] = $currentUnitPrice;
                } else {
                    $discountAmount = (float) ($row->options->product_discount ?? 0);

                    if ($currentUnitPrice > 0) {
                        $this->item_discount[$pid] = round(($discountAmount / $currentUnitPrice) * 100, 2);
                    } else {
                        $this->item_discount[$pid] = 0;
                    }
                }
            }

            if (!isset($this->item_cost_konsyinasi[$pid])) {
                $this->item_cost_konsyinasi[$pid] = (float) ($row->options->product_cost ?? 0);
            }
        }
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

            $unitPrice = (float) ($row->options->unit_price ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) ($row->price ?? 0);
            }

            $subTotal = $unitPrice * (int) ($row->qty ?? 0);

            $cart->update($row->rowId, [
                'options' => $this->mergeOptions($row, [
                    'sub_total'    => $subTotal,
                    'stock'        => $context['stock'],
                    'stock_scope'  => $context['stock_scope'],
                    'warehouse_id' => $context['warehouse_id'],
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
    }

    private function normalizeCartRowSubtotals(): void
    {
        $cart = Cart::instance($this->cart_instance);

        foreach ($cart->content() as $row) {
            $unitPrice = (float) ($row->options->unit_price ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) ($row->price ?? 0);
            }

            $expectedSubTotal = round($unitPrice * (int) ($row->qty ?? 0), 2);
            $storedSubTotal = round((float) ($row->options->sub_total ?? 0), 2);

            if ($storedSubTotal === $expectedSubTotal) {
                continue;
            }

            $options = (array) $row->options;
            $options['sub_total'] = $expectedSubTotal;

            $cart->update($row->rowId, [
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
            'price'   => $calc['unit_price'],
            'weight'  => 1,
            'options' => [
                'purchase_detail_id'    => null,
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => $calc['unit_price'],
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
        $this->item_discount[$product['id']] = (float) $calc['unit_price'];
        $this->item_cost_konsyinasi[$product['id']] = (float) ($calc['product_cost'] ?? 0);
    }

    public function removeItem($row_id)
    {
        Cart::instance($this->cart_instance)->remove($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();
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
        Cart::instance($this->cart_instance)->setGlobalDiscount((int) $this->global_discount);
    }

    public function updateQuantity($row_id, $product_id)
    {
        if ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            if ((int) ($this->check_quantity[$product_id] ?? 0) < (int) ($this->quantity[$product_id] ?? 0)) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, (int) $this->quantity[$product_id]);

        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $warehouseId = null;
        if ($this->stock_mode === 'warehouse') {
            $warehouseId = (int) ($cart_item->options->warehouse_id ?? ($this->loading_warehouse->id ?? 0));
        }

        $context = $this->calculateStockContext((int) $product_id, $warehouseId);

        $unitPrice = (float) ($cart_item->options->unit_price ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = (float) ($cart_item->price ?? 0);
        }

        $subTotal = $unitPrice * (int) ($cart_item->qty ?? 0);

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => $this->mergeOptions($cart_item, [
                'sub_total'    => $subTotal,
                'stock'        => $context['stock'],
                'stock_scope'  => $context['stock_scope'],
                'warehouse_id' => $context['warehouse_id'],
            ])
        ]);

        $this->check_quantity[$product_id] = $context['stock'];
    }

    public function updatedDiscountType($value, $name)
    {
        $productId = (int) $name;

        if ($value === 'fixed') {
            $row = Cart::instance($this->cart_instance)
                ->content()
                ->first(function ($item) use ($productId) {
                    return (int) $item->id === $productId;
                });

            if ($row) {
                $currentUnitPrice = (float) ($row->options->unit_price ?? 0);
                if ($currentUnitPrice <= 0) {
                    $currentUnitPrice = (float) ($row->price ?? 0);
                }

                $this->item_discount[$productId] = $currentUnitPrice;
                return;
            }
        }

        $this->item_discount[$productId] = 0;
    }

    public function discountModalRefresh($product_id, $row_id)
    {
        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id)
    {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);

        if (!$cart_item) {
            session()->flash('discount_message' . $product_id, 'Cart item not found.');
            return;
        }

        $discountType = $this->discount_type[$product_id] ?? 'fixed';
        $inputValue   = (float) ($this->item_discount[$product_id] ?? 0);

        if ($this->cart_instance === 'purchase') {
            if ($this->lock_purchase_price_edit) {
                session()->flash('discount_message' . $product_id, 'Purchase item price is locked because the linked Purchase Delivery is already partial.');
                return;
            }

            $currentUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
            if ($currentUnitPrice <= 0) {
                $currentUnitPrice = (float) ($cart_item->price ?? 0);
            }

            $newUnitPrice = $currentUnitPrice;
            $discountAmount = 0;

            if ($discountType === 'fixed') {
                if ($inputValue <= 0) {
                    session()->flash('discount_message' . $product_id, 'Purchase unit price must be greater than 0.');
                    return;
                }

                $newUnitPrice = round($inputValue, 2);
                $discountAmount = round(max($currentUnitPrice - $newUnitPrice, 0), 2);
            } else {
                if ($inputValue < 0 || $inputValue > 100) {
                    session()->flash('discount_message' . $product_id, 'Percentage must be between 0 and 100.');
                    return;
                }

                $discountAmount = round($currentUnitPrice * ($inputValue / 100), 2);
                $newUnitPrice = round($currentUnitPrice - $discountAmount, 2);

                if ($newUnitPrice < 0) {
                    $newUnitPrice = 0;
                }
            }

            $this->updateCartOptions(
                $row_id,
                $product_id,
                $cart_item,
                $discountAmount,
                $this->item_cost_konsyinasi[$product_id] ?? ($cart_item->options->product_cost ?? 0),
                $this->warehouse_id[$product_id] ?? (int) ($cart_item->options->warehouse_id ?? 0),
                $newUnitPrice,
                $newUnitPrice
            );

            if ($discountType === 'fixed') {
                $this->item_discount[$product_id] = $newUnitPrice;
            } else {
                $this->item_discount[$product_id] = $inputValue;
            }

            session()->flash('discount_message' . $product_id, 'Purchase price updated successfully!');
            return;
        }

        if ($discountType === 'fixed') {
            $discount_amount = 0;

            if (!empty($inputValue)) {
                $discount_amount = ($cart_item->price + ($cart_item->options->product_discount ?? 0)) - $inputValue;
            }

            $this->updateCartOptions(
                $row_id,
                $product_id,
                $cart_item,
                $discount_amount,
                $this->item_cost_konsyinasi[$product_id] ?? 0,
                $this->warehouse_id[$product_id] ?? (int) ($cart_item->options->warehouse_id ?? 0),
                (float) ($cart_item->options->unit_price ?? 0),
                (float) ($inputValue ?: $cart_item->price)
            );
        } else {
            $basePrice = (float) ($cart_item->price + ($cart_item->options->product_discount ?? 0));
            $discount_amount = round($basePrice * ($inputValue / 100), 2);
            $newRowPrice = round($basePrice - $discount_amount, 2);

            $this->updateCartOptions(
                $row_id,
                $product_id,
                $cart_item,
                $discount_amount,
                $this->item_cost_konsyinasi[$product_id] ?? 0,
                $this->warehouse_id[$product_id] ?? (int) ($cart_item->options->warehouse_id ?? 0),
                (float) ($cart_item->options->unit_price ?? 0),
                $newRowPrice
            );
        }

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function calculate($product)
    {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;
        $product_cost = 0;

        if ($product['product_tax_type'] == 1) {
            $price = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 1));
            $unit_price = $product['product_price'];
            $product_cost = $product['product_cost'];
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $unit_price;
        } elseif ($product['product_tax_type'] == 2) {
            $price = $product['product_price'];
            $unit_price = $product['product_price'] - ($product['product_price'] * ($product['product_order_tax'] / 1));
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 1);
            $sub_total = $unit_price;
            $product_cost = $product['product_cost'];
        } else {
            $price = $product['product_price'];
            $unit_price = $product['product_price'];
            $product_tax = 0.00;
            $sub_total = $unit_price;
            $product_cost = $product['product_cost'];
        }

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

        $freshItem = Cart::instance($this->cart_instance)->get($row_id);
        if (!$freshItem) {
            return;
        }

        $finalRowPrice = $overrideRowPrice !== null
            ? (float) $overrideRowPrice
            : (float) $freshItem->price;

        $finalUnitPrice = $overrideUnitPrice !== null
            ? (float) $overrideUnitPrice
            : (float) ($freshItem->options->unit_price ?? $finalRowPrice);

        if ($finalUnitPrice <= 0) {
            $finalUnitPrice = $finalRowPrice;
        }

        if ($finalRowPrice <= 0) {
            $finalRowPrice = $finalUnitPrice;
        }

        Cart::instance($this->cart_instance)->update($row_id, [
            'price' => $finalRowPrice,
        ]);

        $updatedItem = Cart::instance($this->cart_instance)->get($row_id);
        if (!$updatedItem) {
            return;
        }

        $finalQty = (int) ($updatedItem->qty ?? 0);
        $finalSubTotal = round($finalUnitPrice * $finalQty, 2);

        Cart::instance($this->cart_instance)->update($row_id, [
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
