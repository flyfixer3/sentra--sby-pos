<?php

namespace App\Http\Livewire;

use App\Support\BranchContext;
use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;
use Modules\Product\Entities\Warehouse;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Services\HppService;

class ProductCartSale extends Component
{
    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $global_discount;
    public $header_discount_type = 'percentage';
    public $header_discount_value = 0;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee = 0;
    public $is_locked_by_so = false;

    // [line_key] => sellable stock pada branch (GOOD - RESERVED)
    public $check_quantity;

    // [line_key] => qty
    public $quantity;

    public $discount_type;
    public $item_discount;
    public $item_cost_konsyinasi;

    public $so_dp_total = 0;
    public $so_dp_allocated = 0;
    public $so_sale_order_reference = null;

    public $data;

    public function mount($cartInstance, $data = null)
    {
        $this->cart_instance = $cartInstance;

        // default init (biar aman di semua scenario)
        $this->global_discount = 0;
        $this->header_discount_type = 'percentage';
        $this->header_discount_value = 0;
        $this->global_tax = 0;
        $this->global_qty = 0;
        $this->shipping = 0;
        $this->platform_fee = 0;

        $this->check_quantity = [];
        $this->quantity = [];
        $this->discount_type = [];
        $this->item_discount = [];
        $this->item_cost_konsyinasi = [];

        if (!empty($data)) {
            $this->data = $data;
            $this->is_locked_by_so = !empty(data_get($data, 'sale_order_id'));
            $this->so_dp_total = (int) data_get($data, 'deposit_received_amount', 0);
            $this->so_dp_allocated = (int) data_get($data, 'dp_allocated_for_this_invoice', 0);
            $this->so_sale_order_reference = (string) data_get($data, 'sale_order_reference', null);

            // ✅ FIX: pake float biar 23.90% gak jadi 23%
            $this->global_discount = (float) data_get($data, 'discount_percentage', 0);
            $this->header_discount_type = 'percentage';
            $this->header_discount_value = (float) $this->global_discount;
            $this->global_tax      = (float) data_get($data, 'tax_percentage', 0);

            // uang tetap int (sesuai DB kamu sekarang)
            $this->shipping        = (int) data_get($data, 'shipping_amount', 0);
            $this->platform_fee    = (int) data_get($data, 'fee_amount', 0);

            // keep qty consistent
            $this->global_qty = Cart::instance($this->cart_instance)->count();

            // ✅ INI FIX UTAMA: set global ke Cart biar Cart::discount() & tax() hidup
            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();

            // init per-row states from cart. The cart package may recalculate rowId
            // when options change, so use a stable line_key when available.
            $cart_items = Cart::instance($this->cart_instance)->content();
            foreach ($cart_items as $cart_item) {
                $lineKey = $this->getLineKey($cart_item);

                $this->check_quantity[$lineKey] = (int) ($cart_item->options->stock ?? 0);
                $this->quantity[$lineKey] = (int) $cart_item->qty;

                $this->discount_type[$lineKey] = (string) ($cart_item->options->product_discount_type ?? 'fixed');
                $this->item_cost_konsyinasi[$lineKey] = (float) ($cart_item->options->product_cost ?? 0);

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$lineKey] = (float) ($cart_item->price ?? 0);
                } else {
                    $priceBase = ((float) ($cart_item->price + ($cart_item->options->product_discount ?? 0)) > 0)
                        ? (float) ($cart_item->price + ($cart_item->options->product_discount ?? 0))
                        : 1;
                    $disc = (float) ($cart_item->options->product_discount ?? 0);
                    $this->item_discount[$lineKey] = round(100 * ($disc / $priceBase), 2);
                }
            }
        } else {
            // no data: tetap sync qty dari cart kalau ada
            $this->global_qty = Cart::instance($this->cart_instance)->count();
        }

        // ✅ FIX qty state biar ga blank / ke-override
        $this->syncQuantityDefaults();

        // ✅ DP info (kalau create invoice from delivery)
        $this->loadSaleOrderDepositInfo();
    }

    public function render()
    {
        // ✅ setiap render, pastikan qty state tetap kebaca
        $this->syncQuantityDefaults();

        // ✅ NEW: tiap render juga update DP info (karena subtotal bisa berubah saat qty berubah)
        $this->loadSaleOrderDepositInfo();

        if ($this->header_discount_type === 'fixed') {
            $this->syncHeaderDiscountToCart();
        }

        // keep global_qty konsisten
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart-sale', [
            'cart_items' => $cart_items
        ]);
    }

    private function getBranchStockSnapshot(int $productId): array
    {
        $branchId = (int) BranchContext::id();
        if ($branchId <= 0 || $productId <= 0) {
            return [
                'good' => 0,
                'reserved' => 0,
                'sellable' => 0,
            ];
        }

        $warehouseIds = Warehouse::query()
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $good = 0;
        foreach ($warehouseIds as $warehouseId) {
            $last = Mutation::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', (int) $warehouseId)
                ->latest()
                ->value('stock_last');

            $good += max(0, (int) ($last ?? 0));
        }

        $reserved = (int) \DB::table('stocks')
            ->where('branch_id', $branchId)
            ->whereNull('warehouse_id')
            ->where('product_id', $productId)
            ->value('qty_reserved');

        $reserved = max(0, $reserved);
        $sellable = max(0, $good - $reserved);

        return [
            'good' => (int) $good,
            'reserved' => (int) $reserved,
            'sellable' => (int) $sellable,
        ];
    }

    private function getSellableStockByBranch(int $productId): int
    {
        return (int) ($this->getBranchStockSnapshot($productId)['sellable'] ?? 0);
    }

    private function resolveStockContext(int $productId, string $stockScope = 'branch', ?int $warehouseId = null): array
    {
        $stockScope = $stockScope === 'warehouse' ? 'warehouse' : 'branch';
        $warehouseId = (int) ($warehouseId ?? 0);

        if ($stockScope === 'warehouse' && $warehouseId > 0) {
            $stock = (int) Mutation::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->latest()
                ->value('stock_last');

            if ($stock < 0) {
                $stock = 0;
            }

            return [
                'stock' => (int) $stock,
                'reserved' => 0,
                'sellable' => (int) $stock,
                'scope' => 'warehouse',
            ];
        }

        $stockSnapshot = $this->getBranchStockSnapshot($productId);

        return [
            'stock' => (int) ($stockSnapshot['sellable'] ?? 0),
            'reserved' => (int) ($stockSnapshot['reserved'] ?? 0),
            'sellable' => (int) ($stockSnapshot['sellable'] ?? 0),
            'scope' => 'branch',
        ];
    }

    private function isSaleDeliveryInvoiceRow($cartItem): bool
    {
        return (string) ($cartItem->options->invoice_source ?? '') === 'sale_delivery';
    }

    private function getLineKey($cartItem): string
    {
        $lineKey = (string) ($cartItem->options->line_key ?? '');

        return $lineKey !== '' ? $lineKey : (string) $cartItem->rowId;
    }

    private function makeDefaultLineKey(int $productId): string
    {
        return 'product_' . $productId;
    }

    private function findCartRowByLineKey(string $lineKey)
    {
        return Cart::instance($this->cart_instance)
            ->content()
            ->first(function ($item) use ($lineKey) {
                return $this->getLineKey($item) === $lineKey;
            });
    }

    public function productSelected($result)
    {
        $cart = Cart::instance($this->cart_instance);
        $product = $result;

        // ✅ SALE CREATE (walk-in) defaultnya: stock = sellable branch pool (GOOD - RESERVED)
        // karena warehouse baru dipilih saat Confirm Sale Delivery.
        $stockSnapshot = $this->getBranchStockSnapshot((int) ($product['id'] ?? 0));
        $stockTotal = (int) ($stockSnapshot['sellable'] ?? 0);

        if ($stockTotal <= 0 && ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return')) {
            session()->flash('message', 'The requested quantity is not available in stock (Sellable stock = 0).');
            return;
        }

        $calc = $this->calculate($product);

        $pid = (int) ($product['id'] ?? 0);
        $lineKey = $this->makeDefaultLineKey($pid);

        $cartItem = $cart->add([
            'id'      => (int) ($product['id'] ?? 0),
            'name'    => (string) ($product['product_name'] ?? '-'),
            'qty'     => 1,
            'price'   => (int) ($calc['price'] ?? 0),
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => (int) ($calc['sub_total'] ?? 0),
                'code'                  => (string) ($product['product_code'] ?? ''),

                // ✅ stock ditampilkan sebagai sellable branch pool
                'stock'                 => (int) $stockTotal,
                'reserved_stock'        => (int) ($stockSnapshot['reserved'] ?? 0),
                'sellable_stock'        => (int) ($stockSnapshot['sellable'] ?? 0),

                // ✅ FIX UTAMA BIAR UI NOTE GAK RANCU:
                // kalau ini kosong, blade kamu fallback ke 'warehouse' => "Stock shown is from warehouse..."
                'stock_scope'           => 'branch',

                'unit'                  => (string) ($product['product_unit'] ?? ''),

                // ✅ tetap null karena warehouse dipilih saat confirm delivery
                'warehouse_id'          => null,

                // ✅ supaya blade gak bikin "from warehouse: (kosong)" juga
                'warehouse_name'        => '',

                'product_tax'           => (int) ($calc['product_tax'] ?? 0),
                'product_cost'          => (int) ($calc['product_cost'] ?? 0),
                'unit_price'            => (int) ($calc['unit_price'] ?? 0),
                'line_key'              => $lineKey,
            ]
        ]);

        $lineKey = $this->getLineKey($cartItem);

        $this->global_qty = $cart->count();
        $this->check_quantity[$lineKey] = (int) $stockTotal;
        $this->quantity[$lineKey] = (int) $cartItem->qty;
        $this->discount_type[$lineKey] = 'fixed';
        $this->item_discount[$lineKey] = null;
        $this->item_cost_konsyinasi[$lineKey] = 0;

        // ✅ safety biar state gak blank setelah rerender
        $this->syncQuantityDefaults();
    }

    public function removeItem($row_id)
    {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updatedGlobalQuantity()
    {
        Cart::instance($this->cart_instance)->setGlobalQuantity((integer)$this->global_qty);
    }

    public function updatedGlobalTax()
    {
        $val = round((float) ($this->global_tax ?? 0), 2);
        if ($val < 0) $val = 0;
        if ($val > 100) $val = 100;

        $this->global_tax = $val;

        Cart::instance($this->cart_instance)->setGlobalTax((float) $val);
    }

    public function updatedGlobalDiscount()
    {
        $val = round((float) ($this->global_discount ?? 0), 2);
        if ($val < 0) $val = 0;
        if ($val > 100) $val = 100;

        $this->global_discount = $val;
        $this->header_discount_type = 'percentage';
        $this->header_discount_value = $val;

        Cart::instance($this->cart_instance)->setGlobalDiscount((float) $val);
    }

    public function updatedHeaderDiscountType()
    {
        $this->header_discount_type = $this->header_discount_type === 'fixed' ? 'fixed' : 'percentage';
        $this->syncHeaderDiscountToCart();
    }

    public function updatedHeaderDiscountValue()
    {
        $this->syncHeaderDiscountToCart();
    }

    private function syncHeaderDiscountToCart(): void
    {
        $this->header_discount_type = $this->header_discount_type === 'fixed' ? 'fixed' : 'percentage';

        $value = round((float) ($this->header_discount_value ?? 0), 2);
        if ($value < 0) {
            $value = 0;
        }

        if ($this->header_discount_type === 'percentage') {
            if ($value > 100) {
                $value = 100;
            }

            $this->header_discount_value = $value;
            $this->global_discount = $value;
            Cart::instance($this->cart_instance)->setGlobalDiscount((float) $value);
            return;
        }

        $subtotal = (float) Cart::instance($this->cart_instance)->subtotal(0, '.', '');
        $percentage = $subtotal > 0 ? round(($value / $subtotal) * 100, 2) : 0;

        if ($percentage > 100) {
            $percentage = 100;
        }

        $this->header_discount_value = $value;
        $this->global_discount = $percentage;
        Cart::instance($this->cart_instance)->setGlobalDiscount((float) $percentage);
    }

    public function updateQuantity($row_id, $product_id, $line_key = null)
    {
        $product_id = (int) $product_id;
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $lineKey = (string) ($line_key ?: $this->getLineKey($cart_item));

        // ✅ fallback kalau state qty kosong (penyebab utama blank)
        $qty = (int) ($this->quantity[$lineKey] ?? 0);
        if ($qty <= 0) {
            $qty = $cart_item ? (int) $cart_item->qty : 1;
            $this->quantity[$lineKey] = $qty;
        }

        // ✅ preserve warehouse context dari cart options (kalau create from delivery)
        $warehouseId   = (int) ($cart_item->options->warehouse_id ?? 0);
        $warehouseName = (string) ($cart_item->options->warehouse_name ?? '');
        $stockScope    = (string) ($cart_item->options->stock_scope ?? '');
        $reservedStock = (int) ($cart_item->options->reserved_stock ?? 0);
        $sellableStock = (int) ($cart_item->options->sellable_stock ?? 0);
        $isDeliveryInvoice = $this->isSaleDeliveryInvoiceRow($cart_item);
        $code = $cart_item->options->code;
        $unit = $cart_item->options->unit;
        $productTax = $cart_item->options->product_tax;
        $productCost = $cart_item->options->product_cost;
        $unitPrice = $cart_item->options->unit_price;
        $productDiscount = $cart_item->options->product_discount;
        $productDiscountType = $cart_item->options->product_discount_type;
        $invoiceSource = $cart_item->options->invoice_source ?? null;
        $deliveredQty = (int) ($cart_item->options->delivered_qty ?? 0);
        $alreadyInvoicedQty = (int) ($cart_item->options->already_invoiced_qty ?? 0);
        $remainingInvoiceableQty = (int) ($cart_item->options->remaining_invoiceable_qty ?? 0);
        $currentStockQty = (int) ($cart_item->options->current_stock_qty ?? 0);

        if ($isDeliveryInvoice) {
            $stock = max(0, (int) ($cart_item->options->remaining_invoiceable_qty ?? 0));
        } else {
            $stockContext = $this->resolveStockContext($product_id, $stockScope, $warehouseId);
            $stock = (int) ($stockContext['stock'] ?? 0);
            $stockScope = (string) ($stockContext['scope'] ?? 'branch');
            $reservedStock = (int) ($stockContext['reserved'] ?? 0);
            $sellableStock = (int) ($stockContext['sellable'] ?? 0);
        }

        $this->check_quantity[$lineKey] = (int) $stock;

        if ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return') {
            if ((int) $stock < (int) $qty) {
                session()->flash(
                    'message',
                    $isDeliveryInvoice
                        ? 'The requested quantity exceeds the remaining quantity that can be invoiced from this delivery.'
                        : 'The requested quantity is not available in stock.'
                );
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, $qty);

        // refresh row after update
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $subTotal = (float) ($cart_item->price * $cart_item->qty);

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $subTotal,
                'code'                  => $code,
                'stock'                 => (int) $stock,
                'reserved_stock'        => (int) $reservedStock,
                'sellable_stock'        => (int) $sellableStock,
                'stock_scope'           => $stockScope,
                'unit'                  => $unit,

                // ✅ jangan di-null lagi, preserve
                'warehouse_id'          => $warehouseId ?: null,
                'warehouse_name'        => $warehouseName,
                'invoice_source'        => $invoiceSource,
                'delivered_qty'         => $deliveredQty,
                'already_invoiced_qty'  => $alreadyInvoicedQty,
                'remaining_invoiceable_qty' => $remainingInvoiceableQty,
                'current_stock_qty'     => $currentStockQty,

                'product_tax'           => $productTax,
                'product_cost'          => $productCost,
                'unit_price'            => $unitPrice,
                'product_discount'      => $productDiscount,
                'product_discount_type' => $productDiscountType,
                'line_key'              => $lineKey,
            ]
        ]);

        // ✅ keep state konsisten
        $this->syncQuantityDefaults();
    }

    public function finalizeCartBeforeSubmit($rows = [])
    {
        foreach ((array) $rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0 || !array_key_exists('quantity', $row)) {
                continue;
            }

            $lineKey = (string) ($row['line_key'] ?? '');
            $cartRow = Cart::instance($this->cart_instance)
                ->content()
                ->first(function ($item) use ($row, $productId, $lineKey) {
                    if ($lineKey !== '' && $this->getLineKey($item) === $lineKey) {
                        return true;
                    }

                    return (string) $item->rowId === (string) ($row['row_id'] ?? '')
                        || ((int) $item->id === $productId && $lineKey === '');
                });

            if (!$cartRow) {
                continue;
            }

            $quantity = (int) ($row['quantity'] ?? 0);
            $lineKey = $lineKey !== '' ? $lineKey : $this->getLineKey($cartRow);
            $this->quantity[$lineKey] = $quantity > 0 ? $quantity : 1;
            $this->updateQuantity($cartRow->rowId, $productId, $lineKey);
        }

        $this->syncQuantityDefaults();
    }

    public function updatedDiscountType($value, $name)
    {
        $lineKey = (string) $name;
        $row = $this->findCartRowByLineKey($lineKey);

        if (!$row) {
            $this->item_discount[$lineKey] = 0;
            return;
        }

        $basePrice = (float) (($row->price ?? 0) + ($row->options->product_discount ?? 0));
        if ($basePrice < 0) {
            $basePrice = 0;
        }

        if ($value === 'fixed') {
            $this->item_discount[$lineKey] = $basePrice;
        } else {
            $this->item_discount[$lineKey] = 0;
        }
    }

    public function discountModalRefresh($product_id, $row_id, $line_key = null)
    {
        $product_id = (int) $product_id;
        $row = Cart::instance($this->cart_instance)->get($row_id);
        $lineKey = (string) ($line_key ?: ($row ? $this->getLineKey($row) : ''));
        if ($row) {
            $basePrice = (float) (($row->price ?? 0) + ($row->options->product_discount ?? 0));
            if (($this->discount_type[$lineKey] ?? 'fixed') === 'fixed') {
                $this->item_discount[$lineKey] = (float) ($row->price ?? 0);
            } else {
                $this->item_discount[$lineKey] = $basePrice > 0
                    ? round((float) ($row->options->product_discount ?? 0) / $basePrice * 100, 2)
                    : 0;
            }
        }

        $this->updateQuantity($row_id, $product_id, $lineKey);
    }

    public function setProductDiscount($row_id, $product_id, $line_key = null)
    {
        $product_id = (int) $product_id;
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $lineKey = (string) ($line_key ?: ($cart_item ? $this->getLineKey($cart_item) : ''));

        if (!$cart_item) {
            session()->flash('discount_message' . $lineKey, 'Cart item not found.');
            return;
        }

        $discountType = (string) ($this->discount_type[$lineKey] ?? 'fixed');
        $inputValue = (float) ($this->item_discount[$lineKey] ?? 0);

        $basePrice = (float) (($cart_item->price ?? 0) + ($cart_item->options->product_discount ?? 0));
        if ($basePrice < 0) {
            $basePrice = 0;
        }

        if ($discountType === 'fixed') {
            if ($inputValue < 0) {
                $inputValue = 0;
            }

            $newRowPrice = (float) $inputValue;
            $discountAmount = max(0, $basePrice - $newRowPrice);
        } else {
            if ($inputValue < 0 || $inputValue > 100) {
                session()->flash('discount_message' . $lineKey, 'Percentage must be between 0 and 100.');
                return;
            }

            $discountAmount = round($basePrice * ($inputValue / 100), 2);
            $newRowPrice = round($basePrice - $discountAmount, 2);
        }

        if ($newRowPrice < 0) {
            $newRowPrice = 0;
        }

        Cart::instance($this->cart_instance)->update($row_id, [
            'price' => $newRowPrice,
        ]);

        $updatedItem = Cart::instance($this->cart_instance)->get($row_id);
        if (!$updatedItem) {
            return;
        }

        $this->updateCartOptions($row_id, $product_id, $updatedItem, $discountAmount, $lineKey);

        if ($discountType === 'fixed') {
            $this->item_discount[$lineKey] = $newRowPrice;
        } else {
            $this->item_discount[$lineKey] = $inputValue;
        }

        session()->flash('discount_message' . $lineKey, 'Discount added to the product!');
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount, $line_key = null)
    {
        $lineKey = (string) ($line_key ?: $this->getLineKey($cart_item));
        $warehouseId   = (int) ($cart_item->options->warehouse_id ?? 0);
        $warehouseName = (string) ($cart_item->options->warehouse_name ?? '');
        $stockScope    = (string) ($cart_item->options->stock_scope ?? 'branch');
        $reservedStock = (int) ($cart_item->options->reserved_stock ?? 0);
        $sellableStock = (int) ($cart_item->options->sellable_stock ?? 0);
        $isDeliveryInvoice = $this->isSaleDeliveryInvoiceRow($cart_item);

        if ($isDeliveryInvoice) {
            $stock = max(0, (int) ($cart_item->options->remaining_invoiceable_qty ?? 0));
        } else {
            $stockContext = $this->resolveStockContext((int) $product_id, $stockScope, $warehouseId);
            $stock = (int) ($stockContext['stock'] ?? 0);
            $stockScope = (string) ($stockContext['scope'] ?? 'branch');
            $reservedStock = (int) ($stockContext['reserved'] ?? 0);
            $sellableStock = (int) ($stockContext['sellable'] ?? 0);
        }

        $subTotal = (float) (($cart_item->price ?? 0) * ($cart_item->qty ?? 0));

        Cart::instance($this->cart_instance)->update($row_id, ['options' => [
            'sub_total'             => $subTotal,
            'code'                  => $cart_item->options->code,
            'stock'                 => (int) $stock,
            'reserved_stock'        => (int) $reservedStock,
            'sellable_stock'        => (int) $sellableStock,
            'stock_scope'           => $stockScope,
            'unit'                  => $cart_item->options->unit,
            'warehouse_id'          => $warehouseId ?: null,
            'warehouse_name'        => $warehouseName,
            'invoice_source'        => $cart_item->options->invoice_source ?? null,
            'delivered_qty'         => (int) ($cart_item->options->delivered_qty ?? 0),
            'already_invoiced_qty'  => (int) ($cart_item->options->already_invoiced_qty ?? 0),
            'remaining_invoiceable_qty' => (int) ($cart_item->options->remaining_invoiceable_qty ?? 0),
            'current_stock_qty'     => (int) ($cart_item->options->current_stock_qty ?? 0),
            'product_tax'           => $cart_item->options->product_tax,
            'product_cost'          => $cart_item->options->product_cost,
            'unit_price'            => $cart_item->options->unit_price,
            'product_discount'      => (float) $discount_amount,
            'product_discount_type' => $this->discount_type[$lineKey] ?? 'fixed',
            'line_key'              => $lineKey,
        ]]);

        $this->check_quantity[$lineKey] = (int) $stock;
    }

    /**
     * ✅ HPP-aware calculation.
     * product_cost = snapshot HPP (as-of sale date) dari ledger product_hpps berdasarkan branch aktif.
     */
    private function calculate($product): array
    {
        $price = (int) ($product['product_price'] ?? 0);
        $sub_total = $price;
        $product_tax = 0;
        $unit_price = $price;

        $branchId  = (int) BranchContext::id();
        $productId = (int) ($product['id'] ?? 0);

        // Preview HPP mengikuti waktu transaksi saat ini.
        // Snapshot final yang disimpan tetap dihitung lagi di controller saat sale dibuat.
        $saleDate = now();

        $hpp = 0.0;
        if ($branchId > 0 && $productId > 0) {
            $hppService = new HppService();

            // ✅ gunakan as-of (kalau method belum ada, kamu bikin ya di HppService)
            // fallback kalau method belum ada: getCurrentHpp()
            if (method_exists($hppService, 'getHppAsOf')) {
                $hpp = (float) $hppService->getHppAsOf($branchId, $productId, $saleDate);
            } else {
                $hpp = (float) $hppService->getCurrentHpp($branchId, $productId);
            }
        }

        $product_cost = (int) round(max(0.0, $hpp), 0);

        return [
            'price'        => $price,
            'sub_total'    => $sub_total,
            'product_tax'  => $product_tax,
            'product_cost' => $product_cost,
            'unit_price'   => $unit_price,
        ];
    }

    private function syncQuantityDefaults(): void
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        foreach ($cart_items as $row) {
            $pid = (int) $row->id;
            if ($pid <= 0) continue;
            $lineKey = $this->getLineKey($row);

            // ✅ inti fix: kalau state qty belum ada, isi dari cart qty
            if (!isset($this->quantity[$lineKey]) || $this->quantity[$lineKey] === null || $this->quantity[$lineKey] === '') {
                $this->quantity[$lineKey] = (int) $row->qty;
            }

            // safety: stock state
            if (!isset($this->check_quantity[$lineKey])) {
                $this->check_quantity[$lineKey] = (int) ($row->options->stock ?? 0);
            }

            // safety: discount type
            if (!isset($this->discount_type[$lineKey]) || !$this->discount_type[$lineKey]) {
                $this->discount_type[$lineKey] = (string) ($row->options->product_discount_type ?? 'fixed');
            }

            if (!isset($this->item_discount[$lineKey])) {
                if (($row->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$lineKey] = (float) ($row->price ?? 0);
                } else {
                    $priceBase = ((float)($row->price + ($row->options->product_discount ?? 0)) > 0)
                        ? (float)($row->price + ($row->options->product_discount ?? 0))
                        : 1;
                    $this->item_discount[$lineKey] = round(100 * (((float)($row->options->product_discount ?? 0)) / $priceBase));
                }
            }

            if (!isset($this->item_cost_konsyinasi[$lineKey])) {
                $this->item_cost_konsyinasi[$lineKey] = (float) ($row->options->product_cost ?? 0);
            }
        }
    }

    private function loadSaleOrderDepositInfo(): void
    {
        // reset default
        $this->so_dp_total = 0;
        $this->so_dp_allocated = 0;
        $this->so_sale_order_reference = null;

        // hanya relevan untuk invoice sale
        if ($this->cart_instance !== 'sale') return;

        /**
         * ✅ FIX: kalau invoice ini locked by SO dan controller sudah kirim dp numbers,
         * jangan hitung ulang di Livewire (biar gak beda / kelihatan “double”).
         */
        if (!empty($this->is_locked_by_so) && !empty($this->data)) {
            $this->so_dp_total = (int) data_get($this->data, 'deposit_received_amount', 0);
            $this->so_dp_allocated = (int) data_get($this->data, 'dp_allocated_for_this_invoice', 0);
            $this->so_sale_order_reference = (string) data_get($this->data, 'sale_order_reference', null);

            // kalau dp_allocated memang 0 (misal belum ada dp), ya sudah.
            return;
        }

        // =========================
        // fallback behavior lama (non-locked)
        // =========================
        $saleDeliveryId = (int) request()->get('sale_delivery_id', 0);
        if ($saleDeliveryId <= 0) return;

        try {
            $delivery = \Modules\SaleDelivery\Entities\SaleDelivery::query()
                ->where('id', $saleDeliveryId)
                ->first();

            if (!$delivery) return;

            $saleOrderId = (int) ($delivery->sale_order_id ?? 0);
            if ($saleOrderId <= 0) return;

            $saleOrder = \Modules\SaleOrder\Entities\SaleOrder::query()
                ->where('id', $saleOrderId)
                ->first();

            if (!$saleOrder) return;

            $dpTotal = (int) ($saleOrder->deposit_received_amount ?? 0);
            if ($dpTotal <= 0) return;

            // cart subtotal (items only)
            $cartSubtotal = 0;
            $cart_items = Cart::instance($this->cart_instance)->content();
            foreach ($cart_items as $row) {
                $qty = (int) ($row->qty ?? 0);
                $price = (int) ($row->price ?? 0);
                if ($qty <= 0) continue;
                $cartSubtotal += ($qty * max(0, $price));
            }

            // so subtotal (items only)
            $soSubtotal = (int) \Illuminate\Support\Facades\DB::table('sale_order_items')
                ->where('sale_order_id', $saleOrderId)
                ->selectRaw('SUM(COALESCE(quantity,0) * COALESCE(price,0)) as s')
                ->value('s');

            $allocated = 0;
            if ($soSubtotal > 0 && $cartSubtotal > 0) {
                $allocated = (int) round($dpTotal * ($cartSubtotal / $soSubtotal));
                if ($allocated < 0) $allocated = 0;
                if ($allocated > $dpTotal) $allocated = $dpTotal;
            } else {
                $allocated = $dpTotal;
            }

            $this->so_dp_total = $dpTotal;
            $this->so_dp_allocated = $allocated;
            $this->so_sale_order_reference = (string) ($saleOrder->reference ?? ('SO-' . $saleOrderId));
        } catch (\Throwable $e) {
            $this->so_dp_total = 0;
            $this->so_dp_allocated = 0;
            $this->so_sale_order_reference = null;
        }
    }
}
