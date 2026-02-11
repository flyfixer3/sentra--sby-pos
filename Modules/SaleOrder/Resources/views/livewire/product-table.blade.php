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
                    $pname = trim((string)($row['product_name'] ?? ''));
                    $pcode = trim((string)($row['product_code'] ?? ''));
                    $qty = (int)($row['quantity'] ?? 1);
                    $price = (int)($row['price'] ?? 0);
                @endphp

                <tr>
                    <td>
                        @if($pid > 0)
                            <div class="fw-semibold">{{ $pname !== '' ? $pname : ('Product #' . $pid) }}</div>
                            <div class="text-muted small">
                                @if($pcode !== '')
                                    <span class="badge bg-light text-dark border">{{ $pcode }}</span>
                                @endif
                                <span class="ms-1">ID: {{ $pid }}</span>
                            </div>
                        @else
                            <div class="text-muted small">Belum ada produk. Cari via search bar di atas.</div>
                        @endif

                        {{-- ✅ yang dikirim ke controller --}}
                        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $pid }}">
                    </td>

                    <td>
                        {{-- ✅ FIX: input ini yang dikirim (punya name) --}}
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
                        {{-- ✅ FIX: input ini yang dikirim (punya name) --}}
                        <input
                            type="number"
                            class="form-control"
                            min="0"
                            wire:model.lazy="items.{{ $i }}.price"
                            name="items[{{ $i }}][price]"
                            value="{{ $price }}"
                            @if($pid <= 0) disabled @endif
                        >
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
