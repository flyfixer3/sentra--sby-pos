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
            <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center"
                 style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;">
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
                    <th class="align-middle">Placement</th>
                    <th class="align-middle">Current Stock</th>
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
                            $scope = (string)($cart_item->options->stock_scope ?? 'branch'); // branch | warehouse
                            $scopeNote = $scope === 'branch'
                                ? 'Stock shown is total from ALL warehouses (active branch).'
                                : 'Stock shown is from the selected/assigned warehouse.';

                            $placementText = $scope === 'branch'
                                ? 'ALL WAREHOUSES'
                                : ($loading_warehouse ? ($loading_warehouse->warehouse_name ?? '-') : '-');
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
                                {{ $placementText }}
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
                        <th>Order Tax ({{ (float)($global_tax ?? 0) }}%)</th>
                        <td>(+) {{ format_currency(Cart::instance($cart_instance)->tax()) }}</td>
                    </tr>
                    <tr>
                        <th>Discount ({{ (float)($global_discount ?? 0) }}%)</th>
                        <td>(-) {{ format_currency(Cart::instance($cart_instance)->discount()) }}</td>
                    </tr>
                    <tr>
                        <th>Shipping</th>
                        <input type="hidden" value="{{ (float)($shipping ?? 0) }}" name="shipping_amount">
                        <td>(+) {{ format_currency((float)($shipping ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <th>Grand Total</th>
                        @php
                            $total_with_shipping = (float)Cart::instance($cart_instance)->total()
                                + (float)($shipping ?? 0);
                        @endphp
                        <th>
                            (=) {{ format_currency($total_with_shipping) }}
                        </th>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <input type="hidden" name="total_amount" value="{{ $total_with_shipping }}">
    <input type="hidden" name="total_quantity" value="{{ (int)($global_qty ?? 0) }}">

    <div class="form-row">
        <div class="col-lg-4">
            <div class="form-group">
                <label for="tax_percentage">Order Tax (%)</label>
                <input wire:model.lazy="global_tax"
                       type="number"
                       class="form-control"
                       name="tax_percentage"
                       min="0" max="100"
                       value="{{ (float)($global_tax ?? 0) }}"
                       required>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label for="discount_percentage">Discount (%)</label>
                <input wire:model.lazy="global_discount"
                       type="number"
                       class="form-control"
                       name="discount_percentage"
                       min="0" max="100"
                       value="{{ (float)($global_discount ?? 0) }}"
                       required>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-group">
                <label for="shipping_amount">Shipping</label>
                <input wire:model.lazy="shipping"
                       type="number"
                       class="form-control"
                       name="shipping_amount"
                       min="0"
                       value="0"
                       required
                       step="0.01">
            </div>
        </div>
    </div>
</div>