<div>
    <div class="d-flex align-items-center justify-content-between">
        <div><strong>Items</strong></div>
    </div>

    <div class="table-responsive mt-2 position-relative">
        <div
            wire:loading.flex
            wire:target="items.*.quantity,items.*.price,items.*.discount_value,items.*.product_discount_type"
            class="position-absolute justify-content-center align-items-center"
            style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;"
        >
            <div class="d-flex align-items-center px-3 py-2 bg-white border rounded shadow-sm text-primary" style="gap: 8px;">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span>Updating cart...</span>
            </div>
        </div>

        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 40%;">Product</th>
                    <th style="width: 12%;">Qty</th>
                    <th style="width: 18%;">Net Price</th>
                    <th style="width: 25%;">Item Discount</th>
                    <th style="width: 5%;" class="text-center">#</th>
                </tr>
            </thead>
            <tbody>
            @foreach($items as $i => $row)
                @php
                    $pid = (int)($row['product_id'] ?? 0);
                    $pname = trim((string)($row['product_name'] ?? ''));
                    $pcode = trim((string)($row['product_code'] ?? ''));
                    $qty = (int)($row['quantity'] ?? 1);
                    $price = (int)($row['price'] ?? 0);
                    $orig = (int)($row['original_price'] ?? 0);
                    $unit = max($orig, $price);
                    $discountType = (string)($row['product_discount_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
                    $discountValue = $row['discount_value'] ?? ($discountType === 'percentage' ? 0 : $itemDiscount);
                    $itemDiscount = max(0, $unit - $price);
                    $subTotal = max(0, $qty) * max(0, $price);
                @endphp

                <tr
                    wire:loading.class.delay.shortest="opacity-50"
                    wire:target="items.{{ $i }}.quantity,items.{{ $i }}.price,items.{{ $i }}.discount_value,items.{{ $i }}.product_discount_type"
                >
                    <td>
                        @if($pid > 0)
                            <div class="fw-semibold">{{ $pname !== '' ? $pname : ('Product #' . $pid) }}</div>
                            <div class="text-muted small">
                                @if($pcode !== '')
                                    <span class="badge bg-light text-dark border">{{ $pcode }}</span>
                                @endif
                                <span class="ms-1">ID: {{ $pid }}</span>

                                @if($orig > 0)
                                    <span class="ms-2">• Master: <strong>{{ format_currency($orig) }}</strong></span>
                                @endif
                            </div>
                        @else
                            <div class="text-muted small">Belum ada produk. Cari via search bar di atas.</div>
                        @endif

                        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $pid }}">
                        <input type="hidden" name="items[{{ $i }}][original_price]" value="{{ $orig }}">
                        <input type="hidden" name="items[{{ $i }}][unit_price]" value="{{ $unit }}">
                        <input type="hidden" name="items[{{ $i }}][product_discount_amount]" value="{{ $itemDiscount }}">
                        <input type="hidden" name="items[{{ $i }}][sub_total]" value="{{ $subTotal }}">
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            min="1"
                            wire:model.lazy="items.{{ $i }}.quantity"
                            name="items[{{ $i }}][quantity]"
                            value="{{ $qty }}"
                            @if($pid <= 0) disabled @endif
                        >
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            min="0"
                            wire:model.lazy="items.{{ $i }}.price"
                            name="items[{{ $i }}][price]"
                            value="{{ $price }}"
                            @if($pid <= 0) disabled @endif
                        >
                        @if($pid > 0)
                            <div class="text-muted small mt-1">
                                Unit: <strong>{{ format_currency($unit) }}</strong>
                                • Item Discount: <strong>{{ format_currency($itemDiscount) }}/pcs</strong>
                            </div>
                            <div class="text-muted small">
                                Line Subtotal: <strong>{{ format_currency($subTotal) }}</strong>
                            </div>
                        @endif
                    </td>

                    <td>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control"
                                min="0"
                                step="{{ $discountType === 'percentage' ? '0.01' : '1' }}"
                                @if($discountType === 'percentage') max="100" @endif
                                wire:model.lazy="items.{{ $i }}.discount_value"
                                name="items[{{ $i }}][discount_value]"
                                value="{{ $discountValue }}"
                                style="flex: 0 0 70%; max-width: 70%;"
                                @if($pid <= 0) disabled @endif
                            >
                            <select
                                class="form-control"
                                wire:model.lazy="items.{{ $i }}.product_discount_type"
                                name="items[{{ $i }}][product_discount_type]"
                                style="flex: 0 0 30%; max-width: 30%;"
                                @if($pid <= 0) disabled @endif
                            >
                                <option value="fixed" {{ $discountType === 'fixed' ? 'selected' : '' }}>Rp.</option>
                                <option value="percentage" {{ $discountType === 'percentage' ? 'selected' : '' }}>%</option>
                            </select>
                        </div>
                        @if($pid > 0)
                            <!-- <div class="text-muted small mt-1">
                                {{ $discountType === 'percentage' ? 'Discount %' : 'Fixed net sell price' }}
                            </div> -->
                            <div class="text-muted small mt-1">
                                @if($discountType === 'percentage')
                                    Input % di sini adalah % discount. Contoh: harga akhir 70% dari master = isi 30.
                                @else
                                    Input di sini adalah nominal discount. Contoh: harga 1.295.000 jadi 795.000 = isi 500.000.
                                @endif
                            </div>
                        @endif
                    </td>

                    <td class="text-center">
                        <button type="button"
                                class="btn btn-sm btn-danger"
                                wire:click="removeRow({{ $i }})"
                                title="Remove">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
