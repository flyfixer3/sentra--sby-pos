<?php

namespace App\Http\Livewire;

use App\Support\BranchContext;
use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;
use Modules\Mutation\Entities\Mutation;

class ProductCartSale extends Component
{
    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $global_discount;
    public $global_tax;
    public $global_qty;
    public $shipping;
    public $platform_fee = 0;
    public $is_locked_by_so = false;

    // [product_id] => total stock pada branch (gabungan semua warehouse)
    public $check_quantity;

    // [product_id] => qty
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

            // ✅ support array/object (lockedFinancial dari controller itu array)
            $this->global_discount = (int) data_get($data, 'discount_percentage', 0);
            $this->global_tax      = (int) data_get($data, 'tax_percentage', 0);
            $this->shipping        = (int) data_get($data, 'shipping_amount', 0);
            $this->platform_fee    = (int) data_get($data, 'fee_amount', 0);

            // keep qty consistent
            $this->global_qty = Cart::instance($this->cart_instance)->count();

            // ✅ INI FIX UTAMA: set global ke Cart biar Cart::discount() & tax() hidup
            $this->updatedGlobalTax();
            $this->updatedGlobalDiscount();

            // init per-item states from cart
            $cart_items = Cart::instance($this->cart_instance)->content();
            foreach ($cart_items as $cart_item) {
                $pid = (int) $cart_item->id;

                $this->check_quantity[$pid] = (int) ($cart_item->options->stock ?? 0);
                $this->quantity[$pid] = (int) $cart_item->qty;

                $this->discount_type[$pid] = (string) ($cart_item->options->product_discount_type ?? 'fixed');
                $this->item_cost_konsyinasi[$pid] = (float) ($cart_item->options->product_cost ?? 0);

                if (($cart_item->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$pid] = (float) ($cart_item->options->product_discount ?? 0);
                } else {
                    $priceBase = ((float) $cart_item->price > 0) ? (float) $cart_item->price : 1;
                    $disc = (float) ($cart_item->options->product_discount ?? 0);
                    $this->item_discount[$pid] = round(100 * ($disc / $priceBase));
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

        // keep global_qty konsisten
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.product-cart-sale', [
            'cart_items' => $cart_items
        ]);
    }

    private function getTotalStockByBranch(int $productId): int
    {
        $branchId = (int) BranchContext::id();

        $q = Mutation::query()->where('product_id', $productId)->orderByDesc('id');

        if ($branchId > 0) {
            $q->where('branch_id', $branchId);
        }

        // ambil mutation terakhir per warehouse, lalu jumlah stock_last
        $rows = $q->get()->unique('warehouse_id');

        return (int) $rows->sum('stock_last');
    }

    public function productSelected($result)
    {
        $cart = Cart::instance($this->cart_instance);
        $product = $result;

        $stockTotal = $this->getTotalStockByBranch((int) $product['id']);

        if ($stockTotal <= 0 && ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return')) {
            session()->flash('message', 'The requested quantity is not available in stock (Branch Total Stock = 0).');
        }

        $calc = $this->calculate($product);

        $cart->add([
            'id'      => (int) $product['id'],
            'name'    => (string) $product['product_name'],
            'qty'     => 1,
            'price'   => (int) $calc['price'],
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => (int) $calc['sub_total'],
                'code'                  => (string) $product['product_code'],
                'stock'                 => (int) $stockTotal,
                'unit'                  => (string) $product['product_unit'],
                'warehouse_id'          => null,
                'product_tax'           => (int) $calc['product_tax'],
                'product_cost'          => (int) $calc['product_cost'],
                'unit_price'            => (int) $calc['unit_price']
            ]
        ]);

        $this->global_qty = $cart->count();
        $this->check_quantity[(int) $product['id']] = (int) $stockTotal;
        $this->quantity[(int) $product['id']] = 1;
        $this->discount_type[(int) $product['id']] = 'fixed';
        $this->item_discount[(int) $product['id']] = null;
        $this->item_cost_konsyinasi[(int) $product['id']] = 0;
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
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }

    public function updatedGlobalDiscount()
    {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id)
    {
        $product_id = (int) $product_id;

        // ✅ fallback kalau state qty kosong (penyebab utama blank)
        $qty = (int) ($this->quantity[$product_id] ?? 0);
        if ($qty <= 0) {
            $row = Cart::instance($this->cart_instance)->get($row_id);
            $qty = $row ? (int) $row->qty : 1;
            $this->quantity[$product_id] = $qty;
        }

        // ambil cart row terbaru
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);

        // ✅ preserve warehouse context dari cart options (kalau create from delivery)
        $warehouseId   = (int) ($cart_item->options->warehouse_id ?? 0);
        $warehouseName = (string) ($cart_item->options->warehouse_name ?? '');
        $stockScope    = (string) ($cart_item->options->stock_scope ?? '');

        // hitung stock sesuai mode:
        // - kalau ada warehouse_id -> warehouse stock
        // - kalau tidak -> branch total
        if ($warehouseId > 0) {
            $stock = (int) Mutation::where('product_id', $product_id)
                ->where('warehouse_id', $warehouseId)
                ->latest()
                ->value('stock_last') ?? 0;

            $stockScope = $stockScope ?: 'warehouse';
        } else {
            $stock = $this->getTotalStockByBranch($product_id);
            $stockScope = $stockScope ?: 'branch';
        }

        $this->check_quantity[$product_id] = (int) $stock;

        if ($this->cart_instance === 'sale' || $this->cart_instance === 'purchase_return') {
            if ((int) $stock < (int) $qty) {
                session()->flash('message', 'The requested quantity is not available in stock.');
                return;
            }
        }

        Cart::instance($this->cart_instance)->update($row_id, $qty);

        // refresh row after update
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);
        $this->global_qty = Cart::instance($this->cart_instance)->count();

        Cart::instance($this->cart_instance)->update($row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => (int) $stock,
                'stock_scope'           => $stockScope,
                'unit'                  => $cart_item->options->unit,

                // ✅ jangan di-null lagi, preserve
                'warehouse_id'          => $warehouseId ?: null,
                'warehouse_name'        => $warehouseName,

                'product_tax'           => $cart_item->options->product_tax,
                'product_cost'          => $cart_item->options->product_cost,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ]
        ]);

        // ✅ keep state konsisten
        $this->syncQuantityDefaults();
    }

    private function calculate($product): array
    {
        $price = (int) ($product['product_price'] ?? 0);
        $sub_total = $price;
        $product_tax = 0;
        $product_cost = (int) ($product['product_cost'] ?? 0);
        $unit_price = $price;

        return [
            'price' => $price,
            'sub_total' => $sub_total,
            'product_tax' => $product_tax,
            'product_cost' => $product_cost,
            'unit_price' => $unit_price,
        ];
    }

    private function syncQuantityDefaults(): void
    {
        $cart_items = Cart::instance($this->cart_instance)->content();

        foreach ($cart_items as $row) {
            $pid = (int) $row->id;
            if ($pid <= 0) continue;

            // ✅ inti fix: kalau state qty belum ada, isi dari cart qty
            if (!isset($this->quantity[$pid]) || $this->quantity[$pid] === null || $this->quantity[$pid] === '') {
                $this->quantity[$pid] = (int) $row->qty;
            }

            // safety: stock state
            if (!isset($this->check_quantity[$pid])) {
                $this->check_quantity[$pid] = (int) ($row->options->stock ?? 0);
            }

            // safety: discount type
            if (!isset($this->discount_type[$pid]) || !$this->discount_type[$pid]) {
                $this->discount_type[$pid] = (string) ($row->options->product_discount_type ?? 'fixed');
            }

            if (!isset($this->item_discount[$pid])) {
                if (($row->options->product_discount_type ?? 'fixed') === 'fixed') {
                    $this->item_discount[$pid] = (float) ($row->options->product_discount ?? 0);
                } else {
                    $priceBase = ((float)$row->price > 0) ? (float)$row->price : 1;
                    $this->item_discount[$pid] = round(100 * (((float)($row->options->product_discount ?? 0)) / $priceBase));
                }
            }

            if (!isset($this->item_cost_konsyinasi[$pid])) {
                $this->item_cost_konsyinasi[$pid] = (float) ($row->options->product_cost ?? 0);
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
