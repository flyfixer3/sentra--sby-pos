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
                            $scope = (string)($cart_item->options->stock_scope ?? 'warehouse');
                            $whName = (string)($cart_item->options->warehouse_name ?? '');
                            $reservedStock = (int) ($cart_item->options->reserved_stock ?? 0);
                            $sellableStock = (int) ($cart_item->options->sellable_stock ?? ($cart_item->options->stock ?? 0));
                            $invoiceSource = (string) ($cart_item->options->invoice_source ?? '');
                            $deliveredQty = (int) ($cart_item->options->delivered_qty ?? 0);
                            $alreadyInvoicedQty = (int) ($cart_item->options->already_invoiced_qty ?? 0);
                            $remainingInvoiceableQty = (int) ($cart_item->options->remaining_invoiceable_qty ?? ($cart_item->options->stock ?? 0));
                            $currentStockQty = (int) ($cart_item->options->current_stock_qty ?? 0);
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
                                {{ format_currency((float)($cart_item->price ?? 0)) }}
                            </td>

                            <td class="align-middle text-center">
                                @if($invoiceSource === 'sale_delivery')
                                    <div class="d-inline-block text-center" style="min-width: 170px;">
                                        <div class="d-flex flex-wrap align-items-center mt-1" style="gap:6px; justify-content: space-evenly;">
                                            <span class="badge badge-light border text-dark">
                                                Remaining to Invoice: {{ $remainingInvoiceableQty . ' ' . (string)($cart_item->options->unit ?? '') }}
                                            </span>
                                            <span class="badge badge-light border text-dark">
                                                Delivered: {{ $deliveredQty }}
                                            </span>
                                            <span class="badge badge-light border text-dark">
                                                Invoiced: {{ $alreadyInvoicedQty }}
                                            </span>
                                        </div>

                                        <div class="mt-1 small text-muted">
                                            Stock now: {{ $currentStockQty . ' ' . (string)($cart_item->options->unit ?? '') }}
                                            <span class="ml-1">(reference only)</span>
                                        </div>
                                    </div>
                                @elseif($scope === 'branch')
                                    <div>
                                        <span class="badge badge-warning">
                                            Reserved: {{ $reservedStock . ' ' . (string)($cart_item->options->unit ?? '') }}
                                        </span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge badge-info">
                                            Sellable: {{ $sellableStock . ' ' . (string)($cart_item->options->unit ?? '') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="badge badge-info">
                                        {{ (int)($cart_item->options->stock ?? 0) . ' ' . (string)($cart_item->options->unit ?? '') }}
                                    </span>
                                @endif
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

                        $cartSubtotalRaw = (string) Cart::instance($cart_instance)->subtotal(0, '.', '');
                        $itemsSubtotal = (int) $cartSubtotalRaw;

                        // ===== locked numbers from controller =====
                        $lockedGrand = (int) data_get($data, 'invoice_estimated_grand_total', 0);
                        $allocTax    = (int) data_get($data, 'tax_invoice_est', 0);
                        $allocShip   = (int) data_get($data, 'ship_invoice_est', 0);
                        $allocFee    = (int) data_get($data, 'fee_invoice_est', 0);

                        // ✅ discount allocation (minus grand)
                        $discAlloc   = (int) data_get($data, 'discount_info_invoice_est', 0);

                        // dp & pay now
                        $dpAllocated = (int) data_get($data, 'dp_allocated_for_this_invoice', 0);
                        $suggested   = (int) data_get($data, 'suggested_pay_now', 0);

                        $discInfoTotal = (float) data_get($data, 'discount_info_amount', 0);
                        $discInfoPct   = (float) data_get($data, 'discount_info_percentage', 0);
                        $depositPct    = (float) data_get($data, 'deposit_percentage', 0);

                        // non-locked fallback
                        $cartTaxRaw  = (string) Cart::instance($cart_instance)->tax(0, '.', '');
                        $cartDiscRaw = (string) Cart::instance($cart_instance)->discount(0, '.', '');

                        $taxAmount  = (int) $cartTaxRaw;
                        $discAmount = (int) $cartDiscRaw;

                        $shipInputTotal = (int) ($shipping ?? 0);
                        $feeInputTotal  = (int) ($cart_instance === 'sale' ? ($platform_fee ?? 0) : 0);

                        if ($isLockedBySO) {
                            $summaryTax  = max(0, $allocTax);
                            $summaryShip = max(0, $allocShip);
                            $summaryFee  = max(0, $allocFee);
                            $summaryDisc = max(0, $discAlloc);

                            // ✅ controller sudah hitung grand setelah discount
                            $grandTotal = max(0, $lockedGrand);
                            $payNow     = max(0, $suggested);
                        } else {
                            $summaryTax  = max(0, $taxAmount);
                            $summaryShip = max(0, $shipInputTotal);
                            $summaryFee  = max(0, $feeInputTotal);
                            if (($header_discount_type ?? 'percentage') === 'fixed') {
                                $maxFixedDiscount = $itemsSubtotal + $summaryTax + $summaryShip + $summaryFee;
                                $summaryDisc = min(max(0, (int) round((float) ($header_discount_value ?? 0))), max(0, $maxFixedDiscount));
                            } else {
                                $summaryDisc = max(0, $discAmount);
                            }

                            $grandTotal = max(0, ($itemsSubtotal + $summaryTax - $summaryDisc + $summaryShip + $summaryFee));
                            $payNow = $grandTotal;
                        }
                    @endphp

                    @if($cart_instance == 'sale')
                        <tr>
                            <th>Platform Fee</th>
                            <td>(+) {{ format_currency((float)$summaryFee) }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Order Tax ({{ number_format((float)($global_tax ?? 0), 2, '.', '') }}%)</th>
                        <td>(+) {{ format_currency((float)$summaryTax) }}</td>
                    </tr>

                    {{-- ✅ DISCOUNT ROW: always shown (locked uses discAlloc) --}}
                    @if($isLockedBySO)
                        @if($summaryDisc > 0)
                            <tr>
                                <th>
                                    Discount (Locked by SO)
                                    <div class="text-muted" style="font-size:12px;">
                                        (based on delivery items / unit price diff{{ $discInfoPct > 0 ? ', SO %: ' . number_format($discInfoPct, 2, '.', '') . '%' : '' }})
                                    </div>
                                </th>
                                <td>(-) {{ format_currency((float)$summaryDisc) }}</td>
                            </tr>
                        @endif
                    @else
                        <tr>
                            <th>
                                Discount
                                @if(($header_discount_type ?? 'percentage') === 'percentage')
                                    ({{ number_format((float)($global_discount ?? 0), 2, '.', '') }}%)
                                @else
                                    (Rp)
                                @endif
                            </th>
                            <td>(-) {{ format_currency((float)$summaryDisc) }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Shipping</th>
                        <td>(+) {{ format_currency((float)$summaryShip) }}</td>
                    </tr>

                    <tr>
                        <th>Grand Total</th>
                        <th>(=) {{ format_currency((float)$grandTotal) }}</th>
                    </tr>

                    @if($cart_instance === 'sale' && $isLockedBySO)
                        <tr>
                            <th>
                                Deposit from SO
                                @if(!empty($so_sale_order_reference))
                                    <div class="text-muted" style="font-size:12px;">
                                        ({{ $so_sale_order_reference }})
                                    </div>
                                @endif
                                @if($depositPct > 0)
                                    <div class="text-muted" style="font-size:12px;">
                                        ({{ number_format((float)$depositPct, 2, '.', '') }}% of invoice grand total)
                                    </div>
                                @endif
                            </th>
                            <td>
                                (-) {{ format_currency((float) max(0, (int)($dpAllocated ?? 0))) }}
                                @if((int)($so_dp_total ?? 0) > 0)
                                    <div class="text-muted" style="font-size:12px;">
                                        DP Received: {{ format_currency((float)($so_dp_total ?? 0)) }}
                                    </div>
                                @else
                                    <div class="text-muted" style="font-size:12px;">
                                        DP Received: {{ format_currency(0) }}
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
                            <th>(=) {{ format_currency((float) max(0, (int)($payNow ?? 0))) }}</th>
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
                    step="0.01"
                    @if($isLockedBySO) readonly @endif
                >
            </div>
        </div>

        <div class="{{ $cart_instance == 'sale' ? 'col-lg-3' : 'col-lg-4' }}">
            <div class="form-group">
                <label for="header_discount_value">Discount</label>
                <div class="input-group">
                    <input
                        wire:model.lazy="header_discount_value"
                        type="number"
                        class="form-control"
                        name="header_discount_value"
                        min="0"
                        @if(($header_discount_type ?? 'percentage') === 'percentage') max="100" @endif
                        required
                        step="0.01"
                        @if($isLockedBySO) readonly @endif
                    >

                     <div class="input-group-append">
                        <select
                            wire:model.lazy="header_discount_type"
                            class="form-control"
                            name="discount_type"
                            style="max-width: 90px;"
                            @if($isLockedBySO) disabled @endif
                        >
                            <option value="fixed">Rp</option>
                            <option value="percentage">%</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="discount_percentage" value="{{ number_format((float)($global_discount ?? 0), 2, '.', '') }}">
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
                        step="0.01"
                        @if($isLockedBySO) readonly @endif
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
                    @if($isLockedBySO) readonly @endif
                >
            </div>
        </div>
    </div>
</div>
