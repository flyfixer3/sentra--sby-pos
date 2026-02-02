<div>
    <div class="d-flex align-items-center justify-content-between">
        <div><strong>Items</strong></div>
    </div>

    <div class="table-responsive mt-2">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 55%;">Product</th>
                    <th style="width: 20%;">Qty</th>
                    <th style="width: 20%;">Price</th>
                    <th style="width: 5%;" class="text-center">#</th>
                </tr>
            </thead>
            <tbody>
            @foreach($items as $i => $row)
                @php
                    $pid = (int)($row['product_id'] ?? 0);
                    $pname = (string)($row['product_name'] ?? '');
                    $pcode = (string)($row['product_code'] ?? '');
                @endphp

                <tr>
                    <td>
                        @if($pid > 0)
                            <div class="fw-semibold">{{ $pname ?: ('Product #' . $pid) }}</div>
                            @if(!empty($pcode))
                                <div class="text-muted small">{{ $pcode }}</div>
                            @endif
                        @else
                            <div class="text-muted small">Belum ada produk. Cari via search bar di atas.</div>
                        @endif

                        {{-- ✅ input yang akan dikirim ke controller --}}
                        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $pid }}">
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            min="1"
                            wire:model.lazy="items.{{ $i }}.quantity"
                            value="{{ (int)($row['quantity'] ?? 1) }}"
                            @if($pid <= 0) disabled @endif
                        >
                        <input type="hidden" name="items[{{ $i }}][quantity]" value="{{ (int)($row['quantity'] ?? 1) }}">
                    </td>

                    <td>
                        <input
                            type="number"
                            class="form-control"
                            min="0"
                            wire:model.lazy="items.{{ $i }}.price"
                            value="{{ (int)($row['price'] ?? 0) }}"
                            @if($pid <= 0) disabled @endif
                        >
                        <input type="hidden" name="items[{{ $i }}][price]" value="{{ (int)($row['price'] ?? 0) }}">
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
