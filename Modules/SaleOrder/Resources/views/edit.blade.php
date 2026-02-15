@extends('layouts.app')

@section('title', "Edit Sale Order #{$saleOrder->reference}")

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.show', $saleOrder->id) }}">Details</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
@php
    $items = old('items', $items ?? []);
    if (!is_array($items)) $items = [];

    $oldTax = old('tax_percentage', (float)($saleOrder->tax_percentage ?? 0));
    $oldDiscount = old('discount_percentage', (float)($saleOrder->discount_percentage ?? 0));
    $oldShipping = old('shipping_amount', (int)($saleOrder->shipping_amount ?? 0));
    $oldFee = old('fee_amount', (int)($saleOrder->fee_amount ?? 0));

    $oldAutoDiscount = old('auto_discount', '1');

    // deposit readonly display
    $dpPct = (float)($saleOrder->deposit_percentage ?? 0);
    $dpAmt = (int)($saleOrder->deposit_amount ?? 0);
    $dpRec = (int)($saleOrder->deposit_received_amount ?? 0);

    $dpMethod = (string)($saleOrder->deposit_payment_method ?? '');
    $dpCode = (string)($saleOrder->deposit_code ?? '');
@endphp

<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <livewire:search-product />
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div>
                        <strong>Edit Sale Order</strong>
                        <div class="text-muted small">
                            Reference: <span class="fw-bold">{{ $saleOrder->reference }}</span>
                            • Status: <span class="badge bg-warning text-dark">{{ ucfirst($saleOrder->status) }}</span>
                        </div>
                    </div>

                    <div class="mfs-auto d-flex gap-2">
                        <a href="{{ route('sale-orders.show', $saleOrder->id) }}" class="btn btn-sm btn-light">Back</a>
                    </div>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    <div class="alert alert-info">
                        <div class="small">
                            <strong>Deposit (DP) terkunci.</strong>
                            DP hanya bisa diinput saat Create Sale Order untuk menjaga audit & accounting tetap rapi.
                            <br><br>

                            DP Planned (Max): <strong>{{ format_currency($dpAmt) }}</strong>
                            @if($dpPct > 0)
                                ({{ number_format($dpPct, 2) }}%)
                            @endif
                            <br>
                            DP Received: <strong>{{ format_currency($dpRec) }}</strong>

                            @if(!empty($dpMethod))
                                <br>Method: <strong>{{ $dpMethod }}</strong>
                            @endif
                            @if(!empty($dpCode))
                                <br>Deposit To: <strong>{{ $dpCode }}</strong>
                            @endif

                            <br>
                            <span class="text-muted">
                                DP Received inilah yang dipakai untuk catatan DP di Invoice (allocated pro-rata).
                            </span>
                        </div>
                    </div>

                    <form action="{{ route('sale-orders.update', $saleOrder->id) }}" method="POST" id="soEditForm">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control"
                                       value="{{ old('date', (string) $saleOrder->getRawOriginal('date')) }}" required>
                            </div>

                            <div class="col-md-9 mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">-- Choose --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (int) old('customer_id', $saleOrder->customer_id) === (int) $c->id ? 'selected' : '' }}>
                                            {{ $c->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" rows="2">{{ old('note', $saleOrder->note) }}</textarea>
                                <div class="small text-muted mt-1">
                                    Warehouse tidak diatur di Sale Order (dipilih saat membuat Sale Delivery).
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="mt-2">
                            <livewire:sale-order.product-table :prefillItems="$items" />
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Order Tax (%)</label>
                                <input type="number" min="0" max="100" step="1"
                                       name="tax_percentage" id="so_tax_percentage"
                                       class="form-control" value="{{ $oldTax }}" required>
                            </div>

                            <div class="col-lg-4 mb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <label class="form-label mb-0">Discount (%)</label>

                                    <label class="mb-0 small d-flex align-items-center gap-2">
                                        <input type="checkbox" name="auto_discount" id="so_auto_discount" value="1"
                                            {{ (string)$oldAutoDiscount === '1' ? 'checked' : '' }}>
                                        Auto (from price diff)
                                    </label>
                                </div>

                                <input type="text"
                                       inputmode="decimal"
                                       placeholder="0"
                                       name="discount_percentage" id="so_discount_percentage"
                                       class="form-control" value="{{ $oldDiscount }}" required>

                                <div class="small text-muted">
                                    Auto: % dihitung dari (Master - Sell). Manual: % akan mengubah Sell Price.
                                </div>
                            </div>

                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Platform Fee</label>
                                <input type="number" min="0" step="1"
                                       name="fee_amount" id="so_fee_amount"
                                       class="form-control" value="{{ $oldFee }}" required>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Shipping</label>
                                <input type="number" min="0" step="1"
                                       name="shipping_amount" id="so_shipping_amount"
                                       class="form-control" value="{{ $oldShipping }}" required>
                            </div>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-lg-4">
                                <table class="table table-striped">
                                    <tbody>
                                        <tr>
                                            <th>Items Subtotal</th>
                                            <td class="text-end" id="so_subtotal_text">Rp0</td>
                                        </tr>
                                        <tr>
                                            <th>Tax</th>
                                            <td class="text-end" id="so_tax_text">Rp0</td>
                                        </tr>
                                        <tr>
                                            <th>Discount</th>
                                            <td class="text-end" id="so_discount_text">Rp0</td>
                                        </tr>
                                        <tr>
                                            <th>Platform Fee</th>
                                            <td class="text-end" id="so_fee_text">Rp0</td>
                                        </tr>
                                        <tr>
                                            <th>Shipping</th>
                                            <td class="text-end" id="so_shipping_text">Rp0</td>
                                        </tr>
                                        <tr>
                                            <th>Grand Total</th>
                                            <th class="text-end" id="so_grand_text">Rp0</th>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="small text-muted">
                                    Grand Total dihitung dari item (qty × sell price) + tax + fee + shipping.<br>
                                    Discount hanya informasi (selisih Master vs Sell), tidak mengurangi lagi saat Auto ON.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Changes
                            </button>

                            <a href="{{ route('sale-orders.show', $saleOrder->id) }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>

                    <div class="small text-muted mt-3">
                        Tips: cari produk lewat search bar di atas, lalu klik hasilnya untuk menambahkan ke tabel item.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
    function soFormatRupiah(num) {
        const n = Math.round(Number(num) || 0);
        return 'Rp' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function soParseInt(v) {
        const n = parseInt((v ?? '').toString().replace(/[^\d-]/g, ''), 10);
        return Number.isFinite(n) ? n : 0;
    }
    function soNormalizeDecimalString(v) {
        let s = (v ?? '').toString().trim();
        s = s.replace(',', '.');
        s = s.replace(/[^\d.\-]/g, '');
        const parts = s.split('.');
        if (parts.length > 2) s = parts.shift() + '.' + parts.join('');
        return s;
    }
    function soParseFloat(v) {
        const n = parseFloat(soNormalizeDecimalString(v));
        return Number.isFinite(n) ? n : 0;
    }
    function soClamp(num, min, max) {
        return Math.max(min, Math.min(max, num));
    }
    function soToFixed2(n) {
        return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    }

    function soGetRows() {
        const productInputs = document.querySelectorAll('input[name^="items"][name$="[product_id]"]');
        const rows = [];

        productInputs.forEach((pidInput) => {
            const name = pidInput.getAttribute('name') || '';
            const idxMatch = name.match(/items\[(\d+)\]\[product_id\]/);
            if (!idxMatch) return;

            const idx = idxMatch[1];
            const pid = soParseInt(pidInput.value);
            if (!pid || pid <= 0) return;

            const qtyInput  = document.querySelector(`input[name="items[${idx}][quantity]"]`);
            const priceInput= document.querySelector(`input[name="items[${idx}][price]"]`);
            const origInput = document.querySelector(`input[name="items[${idx}][original_price]"]`);

            const qty  = qtyInput ? soParseInt(qtyInput.value) : 0;
            const price= priceInput ? soParseInt(priceInput.value) : 0;
            const orig = origInput ? soParseInt(origInput.value) : 0;

            if (qty > 0) rows.push({ pid, qty, price: Math.max(0, price), orig: Math.max(0, orig) });
        });

        return rows;
    }

    function soComputeSubtotalSell(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * r.price), 0);
    }
    function soComputeDiffDiscount(rows) {
        return rows.reduce((sum, r) => {
            const base = r.orig > 0 ? r.orig : r.price;
            const diff = Math.max(0, base - r.price);
            return sum + (r.qty * diff);
        }, 0);
    }
    function soComputeOriginalSubtotal(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * (r.orig > 0 ? r.orig : r.price)), 0);
    }

    function soIsAutoDiscount() {
        return !!document.getElementById('so_auto_discount')?.checked;
    }
    function soApplyAutoDiscountPercentFromPriceDiff(rows) {
        const discEl = document.getElementById('so_discount_percentage');
        if (!discEl) return;

        const originalSubtotal = soComputeOriginalSubtotal(rows);
        if (originalSubtotal <= 0) {
            discEl.value = soToFixed2(0);
            return;
        }

        const diffDiscount = soComputeDiffDiscount(rows);
        let pct = (diffDiscount / originalSubtotal) * 100;
        pct = soClamp(pct, 0, 100);
        discEl.value = soToFixed2(pct);
    }

    function soRecalc() {
        const rows = soGetRows();

        const subtotalSell = soComputeSubtotalSell(rows);
        const diffDiscount = soComputeDiffDiscount(rows);

        if (soIsAutoDiscount()) {
            soApplyAutoDiscountPercentFromPriceDiff(rows);
        }

        const taxPct  = soClamp(soParseFloat(document.getElementById('so_tax_percentage')?.value), 0, 100);
        const discPct = soClamp(soParseFloat(document.getElementById('so_discount_percentage')?.value), 0, 100);

        const fee      = Math.max(0, soParseInt(document.getElementById('so_fee_amount')?.value));
        const shipping = Math.max(0, soParseInt(document.getElementById('so_shipping_amount')?.value));

        const taxAmt = Math.round(subtotalSell * (taxPct / 100));

        let discAmt = 0;
        if (soIsAutoDiscount()) discAmt = Math.round(diffDiscount);
        else discAmt = Math.round(subtotalSell * (discPct / 100));

        const grand = Math.round(subtotalSell + taxAmt + fee + shipping - (soIsAutoDiscount() ? 0 : discAmt));

        document.getElementById('so_subtotal_text').innerText = soFormatRupiah(subtotalSell);
        document.getElementById('so_tax_text').innerText = soFormatRupiah(taxAmt);
        document.getElementById('so_discount_text').innerText = soFormatRupiah(discAmt);
        document.getElementById('so_fee_text').innerText = soFormatRupiah(fee);
        document.getElementById('so_shipping_text').innerText = soFormatRupiah(shipping);
        document.getElementById('so_grand_text').innerText = soFormatRupiah(grand);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const discEl = document.getElementById('so_discount_percentage');
        discEl?.addEventListener('input', function () {
            discEl.value = soNormalizeDecimalString(discEl.value);
        });

        document.addEventListener('input', function (e) {
            const id = e.target?.id || '';
            const n  = e.target?.getAttribute('name') || '';

            if (
                n.includes('[quantity]') ||
                n.includes('[price]') ||
                id === 'so_tax_percentage' ||
                id === 'so_discount_percentage' ||
                id === 'so_fee_amount' ||
                id === 'so_shipping_amount'
            ) {
                if (id === 'so_discount_percentage') {
                    const chk = document.getElementById('so_auto_discount');
                    if (chk && chk.checked) chk.checked = false;
                }
                soRecalc();
            }
        });

        soRecalc();
    });
</script>
@endpush
