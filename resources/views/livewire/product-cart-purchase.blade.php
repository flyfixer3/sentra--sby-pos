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
            <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center" style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>

            <div>
                <h6>Total Quantity: {{ $global_qty }} Unit</h6>
            </div>

            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th class="align-middle">Product</th>
                        <th class="align-middle">Gross Price</th>
                        <th class="align-middle">Net Price</th>
                        <th class="align-middle">Placement</th>
                        <th class="align-middle">Current Stock</th>
                        <th class="align-middle">Quantity</th>
                        <th class="align-middle">Discount</th>
                        <th class="align-middle">Sub Total</th>
                        <th class="align-middle">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if($cart_items->isNotEmpty())
                        @foreach($cart_items as $cart_item)
                            @php
                                $productCode = $cart_item->options->code ?? 'UNKNOWN';

                                $displayGrossPrice = (float) ($cart_item->options->unit_price ?? 0);
                                if ($displayGrossPrice <= 0) {
                                    $displayGrossPrice = (float) ($cart_item->price ?? 0) + (float) ($cart_item->options->product_discount ?? 0);
                                }

                                $displayNetPrice = (float) ($cart_item->price ?? 0);
                                $displaySubTotal = round($displayNetPrice * (int) ($cart_item->qty ?? 0), 2);
                                $currentDiscountType = $discount_type[$cart_item->id] ?? ($cart_item->options->product_discount_type ?? 'fixed');

                                $stockScope = (string) ($cart_item->options->stock_scope ?? 'branch');
                                $isWarehouseScope = $stockScope === 'warehouse';

                                $warehouseName = null;
                                if ($isWarehouseScope && !empty($cart_item->options->warehouse_id)) {
                                    $w = \Modules\Product\Entities\Warehouse::find((int) $cart_item->options->warehouse_id);
                                    $warehouseName = $w?->warehouse_name ?? $w?->warehouse_code ?? ('Warehouse #' . $cart_item->options->warehouse_id);
                                }

                                $displayStock = (int) ($cart_item->options->stock ?? 0);
                            @endphp

                            <tr>
                                <td class="align-middle">
                                    {{ $cart_item->name }} <br>
                                    <span class="badge badge-success">
                                        {{ $productCode }}
                                    </span>
                                </td>

                                <td class="align-middle">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control"
                                        wire:model.defer="gross_price.{{ $cart_item->id }}"
                                        wire:change="updatePricing('{{ $cart_item->rowId }}', '{{ $cart_item->id }}')"
                                        @if($cart_instance === 'purchase' && $lock_purchase_price_edit) disabled @endif
                                    >
                                </td>

                                <td class="align-middle">
                                    {{ format_currency($displayNetPrice) }}
                                </td>

                                <td class="align-middle text-center">
                                    @if($isWarehouseScope)
                                        <div style="font-weight: 600;">
                                            {{ strtoupper($warehouseName ?? 'WAREHOUSE') }}
                                        </div>
                                    @else
                                        <div style="font-weight: 600;">
                                            ALL WAREHOUSES
                                        </div>
                                    @endif
                                </td>

                                <td class="align-middle text-center">
                                    <span class="badge badge-primary">
                                        {{ $displayStock }}
                                    </span>
                                    <div class="mt-2 text-muted" style="font-size: 12px;">
                                        @if($isWarehouseScope)
                                            Stock shown is from selected warehouse.
                                        @else
                                            Stock shown is total from ALL warehouses (active branch).
                                        @endif
                                    </div>
                                </td>

                                <td class="align-middle">
                                    @include('livewire.includes.product-cart-quantity')
                                </td>

                                <td class="align-middle" style="min-width: 210px;">
                                    <div class="input-group input-group-sm flex-nowrap">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            max="{{ $currentDiscountType === 'percentage' ? 100 : $displayGrossPrice }}"
                                            class="form-control"
                                            wire:model.defer="item_discount.{{ $cart_item->id }}"
                                            wire:change="updatePricing('{{ $cart_item->rowId }}', '{{ $cart_item->id }}')"
                                            @if($cart_instance === 'purchase' && $lock_purchase_price_edit) disabled @endif
                                        >
                                        <div class="input-group-append">
                                            <select
                                                class="custom-select"
                                                style="width: 86px;"
                                                wire:model="discount_type.{{ $cart_item->id }}"
                                                wire:change="changeDiscountType('{{ $cart_item->rowId }}', '{{ $cart_item->id }}', $event.target.value)"
                                                @if($cart_instance === 'purchase' && $lock_purchase_price_edit) disabled @endif
                                            >
                                                <option value="fixed">Fixed</option>
                                                <option value="percentage">%</option>
                                            </select>
                                        </div>
                                    </div>
                                    <small class="d-block text-muted mt-1" style="font-size: 11px;">
                                        Amount: {{ format_currency((float) ($cart_item->options->product_discount ?? 0)) }}
                                    </small>
                                    @if(session()->has('discount_message' . $cart_item->id))
                                        <small class="d-block text-info" style="font-size: 11px;">{{ session('discount_message' . $cart_item->id) }}</small>
                                    @endif
                                </td>

                                <td class="align-middle">
                                    {{ format_currency($displaySubTotal) }}
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
                            <td colspan="9" class="text-center">
                                <span class="text-danger">
                                    Please search & select products!
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
                    <tr>
                        <th>Order Tax ({{ $global_tax }}%)</th>
                        <td>(+) {{ format_currency(Cart::instance($cart_instance)->tax()) }}</td>
                    </tr>
                    <tr>
                        <th>
                            Discount
                            @if($global_discount_type === 'fixed')
                                (Fixed)
                            @else
                                ({{ $global_discount }}%)
                            @endif
                        </th>
                        <td>(-) {{ format_currency(Cart::instance($cart_instance)->discount()) }}</td>
                    </tr>
                    <tr>
                        <th>Shipping</th>
                        <input type="hidden" value="{{ $shipping }}" name="shipping_amount">
                        <td>(+) {{ format_currency($shipping) }}</td>
                    </tr>
                    <tr>
                        <th>Grand Total</th>
                        @php
                            $cartTotal = (float) str_replace(',', '', Cart::instance($cart_instance)->total());
                            $total_with_shipping = $cartTotal + (float) $shipping;
                        @endphp
                        <th>(=) {{ format_currency($total_with_shipping) }}</th>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <input type="hidden" name="total_amount" value="{{ $total_with_shipping }}">
    <input type="hidden" name="total_quantity" value="{{ $global_qty }}">

    <div class="form-row">
        <div class="col-lg-4">
            <div class="form-group">
                <label for="tax_percentage">Order Tax (%)</label>
                <input wire:model.lazy="global_tax" type="number" class="form-control" name="tax_percentage" min="0" max="100" value="{{ $global_tax }}" required>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label for="discount_value">Discount</label>
                <div class="input-group">
                    <input
                        wire:model.lazy="global_discount"
                        type="number"
                        class="form-control"
                        id="discount_value"
                        min="0"
                        step="0.01"
                        max="{{ $global_discount_type === 'percentage' ? 100 : '' }}"
                        value="{{ $global_discount }}"
                        required
                    >
                    <div class="input-group-append">
                        <select class="custom-select" wire:model="global_discount_type">
                            <option value="fixed">Fixed</option>
                            <option value="percentage">%</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="discount_percentage" value="{{ $global_discount_type === 'percentage' ? $global_discount : 0 }}">
                <input type="hidden" name="discount_amount" value="{{ Cart::instance($cart_instance)->discount() }}">
                <input type="hidden" name="discount_type" value="{{ $global_discount_type }}">
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label for="shipping_amount">Shipping</label>
                <input wire:model.lazy="shipping" type="number" class="form-control" name="shipping_amount" min="0" value="0" required step="0.01">
            </div>
        </div>
    </div>
</div>
