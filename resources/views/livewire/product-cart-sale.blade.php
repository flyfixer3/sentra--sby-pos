<div>
    <div>
        @if (session()->has('message'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="alert-body">
                    <span>{{ session('message') }}</span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            </div>
        @endif

        <div class="table-responsive position-relative">
            <div
                wire:loading.flex
                class="col-12 position-absolute justify-content-center align-items-center"
                style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;"
            >
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>

            <div>
                <h6>Total Quantity: {{ (int)($global_qty ?? 0) }} Unit</h6>
            </div>

            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th class="align-middle">Product</th>
                        <th class="align-middle">Sell Unit Price</th>
                        <th class="align-middle">Stock</th>
                        <th class="align-middle">Quantity</th>
                        <th class="align-middle">Discount</th>
                        <th class="align-middle">Tax</th>
                        <th class="align-middle">Sub Total</th>
                        <th class="align-middle">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @if(isset($cart_items) && $cart_items->isNotEmpty())
                        @foreach($cart_items as $cart_item)
                            @php
                                $scope = (string)($cart_item->options->stock_scope ?? 'warehouse'); // warehouse | branch
                                $whName = (string)($cart_item->options->warehouse_name ?? '');
                                $scopeNote = $scope === 'branch'
                                    ? 'Stock shown is total from ALL warehouses (active branch).'
                                    : ('Stock shown is from warehouse' . ($whName ? (': ' . $whName) : '.') );
                            @endphp

                            <tr>
                                <td class="align-middle">
                                    {{ $cart_item->name }} <br>
                                    <span class="badge badge-success">
                                        {{ $cart_item->options->code ?? '-' }}
                                    </span>
                                    @include('livewire.includes.product-cart-modal-sale')
                                </td>

                                <td class="align-middle">
                                    {{ format_currency((float)($cart_item->options->unit_price ?? 0)) }}
                                </td>

                                <td class="align-middle text-center">
                                    <span class="badge badge-info">
                                        {{ (int)($cart_item->options->stock ?? 0) . ' ' . (string)($cart_item->options->unit ?? '') }}
                                    </span>
                                    <div class="mt-1">
                                        <small class="text-muted">{{ $scopeNote }}</small>
                                    </div>
                                </td>

                                <td class="align-middle">
                                    @include('livewire.includes.product-cart-quantity')
                                </td>

                                <td class="align-middle">
                                    {{ format_currency((float)($cart_item->options->product_discount ?? 0)) }}
                                </td>

                                <td class="align-middle">
                                    {{ format_currency((float)($cart_item->options->product_tax ?? 0)) }}
                                </td>

                                <td class="align-middle">
                                    {{ format_currency((float)($cart_item->options->sub_total ?? 0)) }}
                                </td>

                                <td class="align-middle text-center">
                                    <a href="#" wire:click.prevent="removeItem('{{ $cart_item->rowId }}')">
                                        <i class="bi bi-x-circle font-2xl text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="8" class="text-center">
                                <span class="text-danger">
                                    Please search &amp; select products!
                                </span>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="row justify-content-md-end">
        <div class="col-md-4">
            <div class="table-responsive">
                <table class="table table-striped">
                    @php
                        $isLockedBySO = !empty(data_get($data, 'sale_order_id'));

                        /**
                        * IMPORTANT:
                        * Cart::subtotal()/tax()/discount() umumnya RETURN STRING berformat.
                        * Jangan cast (float) langsung karena bisa salah parse ("1,295,000" jadi 1).
                        */
                        $cartSubtotalRaw = (string) Cart::instance($cart_instance)->subtotal(0, '.', ''); // "1295000"
                        $cartTaxRaw      = (string) Cart::instance($cart_instance)->tax(0, '.', '');
                        $cartDiscRaw     = (string) Cart::instance($cart_instance)->discount(0, '.', '');

                        $itemsSubtotal = (int) $cartSubtotalRaw;
                        $taxAmount     = (int) $cartTaxRaw;
                        $discAmount    = (int) $cartDiscRaw;

                        $shipAmount = (int) ($shipping ?? 0);
                        $feeAmount  = (int) ($cart_instance === 'sale' ? ($platform_fee ?? 0) : 0);

                        // ====== LOCKED BY SO: 1 sumber kebenaran (controller) ======
                        $lockedGrand = (int) data_get($data, 'invoice_estimated_grand_total', 0);
                        $dpAllocated = (int) data_get($data, 'dp_allocated_for_this_invoice', 0);
                        $suggested   = (int) data_get($data, 'suggested_pay_now', 0);

                        // Diskon info dari SO (amount)
                        $discInfoAmt = (int) data_get($data, 'discount_info_amount', 0);
                        $discInfoPct = (float) data_get($data, 'discount_info_percentage', 0);

                        if ($isLockedBySO && $lockedGrand > 0) {
                            // ✅ grand & payNow langsung pakai hasil controller
                            $grandTotal = max(0, $lockedGrand);
                            $payNow     = max(0, $suggested);

                            // ✅ hard validation (opsional buat debug)
                            // if ($payNow !== max(0, $grandTotal - $dpAllocated)) { ... }
                        } else {
                            // ====== NON-LOCKED: hitung dari cart (numeric safe) ======
                            $grandTotal = max(0, ($itemsSubtotal + $taxAmount - $discAmount + $shipAmount + $feeAmount));
                            $payNow     = $grandTotal; // non-locked biasanya nggak ada dpAllocated
                        }
                    @endphp

                    @if($cart_instance == 'sale')
                        <tr>
                            <th>Platform Fee</th>
                            <td>(+) {{ format_currency((float)($platform_fee ?? 0)) }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Order Tax ({{ (float)($global_tax ?? 0) }}%)</th>
                        <td>(+) {{ format_currency((float) Cart::instance($cart_instance)->tax()) }}</td>
                    </tr>

                    @if($isLockedBySO)
                        <tr>
                            <!-- <th>
                                Discount (Applied)
                                @if($discInfoPct > 0)
                                    <div class="text-muted" style="font-size:12px;">
                                        ({{ $discInfoPct }}% locked from SO)
                                    </div>
                                @endif
                            </th> -->
                            <!-- <td>(-) {{ format_currency((float) Cart::instance($cart_instance)->discount()) }}</td> -->
                        </tr>

                        @if($discInfoAmt > 0)
                            <tr>
                                <th>
                                    Discount Info (Diff)
                                    <div class="text-muted" style="font-size:12px;">
                                        (reference)
                                    </div>
                                </th>
                                <td>{{ format_currency((float)$discInfoAmt) }}</td>
                            </tr>
                        @endif
                    @else
                        <tr>
                            <th>Discount ({{ (float)($global_discount ?? 0) }}%)</th>
                            <td>(-) {{ format_currency((float) Cart::instance($cart_instance)->discount()) }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Shipping</th>
                        <td>(+) {{ format_currency((float)($shipping ?? 0)) }}</td>
                    </tr>

                    <tr>
                        <th>Grand Total</th>
                        <th>(=) {{ format_currency($grandTotal) }}</th>
                    </tr>

                    @if($cart_instance === 'sale' && (int)($so_dp_allocated ?? 0) > 0)
                        <tr>
                            <th>
                                Deposit from SO
                                @if(!empty($so_sale_order_reference))
                                    <div class="text-muted" style="font-size:12px;">
                                        ({{ $so_sale_order_reference }})
                                    </div>
                                @endif
                            </th>
                            <td>
                                (-) {{ format_currency((float)($so_dp_allocated ?? 0)) }}
                                @if((int)($so_dp_total ?? 0) > 0)
                                    <div class="text-muted" style="font-size:12px;">
                                        DP Received: {{ format_currency((float)($so_dp_total ?? 0)) }}
                                    </div>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>
                                Amount to Receive Now
                                <div class="text-muted" style="font-size:12px;">
                                    (Grand Total - Allocated DP)
                                </div>
                            </th>
                            <th>(=) {{ format_currency((float)$payNow) }}</th>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <input type="hidden" name="total_amount" value="{{ (int) round($grandTotal) }}">
    <input type="hidden" name="total_quantity" value="{{ (int)($global_qty ?? 0) }}">

    <div class="form-row">
        <div class="{{ $cart_instance == 'sale' ? 'col-lg-3' : 'col-lg-4' }}">
            <div class="form-group">
                <label for="tax_percentage">Order Tax (%)</label>
                <input
                    wire:model.lazy="global_tax"
                    type="number"
                    class="form-control"
                    name="tax_percentage"
                    min="0"
                    max="100"
                    required
                >
            </div>
        </div>

        <div class="{{ $cart_instance == 'sale' ? 'col-lg-3' : 'col-lg-4' }}">
            <div class="form-group">
                <label for="discount_percentage">Discount (%)</label>
                <input
                    wire:model.lazy="global_discount"
                    type="number"
                    class="form-control"
                    name="discount_percentage"
                    min="0"
                    max="100"
                    required
                    @if($isLockedBySO) readonly @endif
                >
            </div>
        </div>

        @if($cart_instance == 'sale')
            <div class="col-lg-3">
                <div class="form-group">
                    <label for="fee_amount">Platform Fee</label>
                    <input
                        wire:model.lazy="platform_fee"
                        type="number"
                        class="form-control"
                        name="fee_amount"
                        min="0"
                        required
                    >
                </div>
            </div>
        @endif

        <div class="col-lg-3">
            <div class="form-group">
                <label for="shipping_amount">Shipping</label>
                <input
                    wire:model.lazy="shipping"
                    type="number"
                    class="form-control"
                    name="shipping_amount"
                    min="0"
                    required
                    step="0.01"
                >
            </div>
        </div>
    </div>
</div>
