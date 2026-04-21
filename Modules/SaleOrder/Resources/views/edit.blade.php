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
    $oldDiscountType = old('discount_type', 'percentage');
    $oldHeaderDiscountValue = old('header_discount_value', (float)($saleOrder->discount_percentage ?? 0));
    $oldShipping = old('shipping_amount', (int)($saleOrder->shipping_amount ?? 0));
    $oldFee = old('fee_amount', (int)($saleOrder->fee_amount ?? 0));

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

                        <div class="mt-2" data-so-product-table-host>
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
                                <label class="form-label">Header Discount</label>
                                <div class="input-group">
                                    <input type="text"
                                           inputmode="decimal"
                                           placeholder="0"
                                           name="header_discount_value" id="so_header_discount_value"
                                           class="form-control"
                                           style="flex: 0 0 70%; max-width: 70%;"
                                           value="{{ $oldHeaderDiscountValue }}" required>
                                    <select name="discount_type" id="so_discount_type"
                                            class="form-control"
                                            style="flex: 0 0 30%; max-width: 30%;"
                                            required>
                                        <option value="fixed" {{ $oldDiscountType === 'fixed' ? 'selected' : '' }}>Rp</option>
                                        <option value="percentage" {{ $oldDiscountType !== 'fixed' ? 'selected' : '' }}>%</option>
                                    </select>
                                </div>
                                <input type="hidden" name="discount_percentage" id="so_discount_percentage"
                                       value="{{ $oldDiscountType === 'fixed' ? 0 : $oldHeaderDiscountValue }}">

                                <div class="small text-muted">
                                    Header discount terpisah dari item discount per baris.
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
                                    Item discount sudah masuk ke Net Price per baris. Header discount mengurangi Grand Total.
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
    function soComputeHeaderDiscount(subtotalSell, taxAmt, fee, shipping) {
        const type = document.getElementById('so_discount_type')?.value === 'fixed' ? 'fixed' : 'percentage';
        const valueEl = document.getElementById('so_header_discount_value');
        const pctEl = document.getElementById('so_discount_percentage');
        const rawValue = soParseFloat(valueEl?.value);

        if (type === 'fixed') {
            const base = Math.max(0, subtotalSell + taxAmt + fee + shipping);
            const amount = Math.min(Math.max(0, Math.round(rawValue)), base);
            if (pctEl) pctEl.value = subtotalSell > 0 ? soToFixed2((amount / subtotalSell) * 100) : '0';
            return amount;
        }

        const pct = soClamp(rawValue, 0, 100);
        if (valueEl) valueEl.value = soNormalizeDecimalString(valueEl.value);
        if (pctEl) pctEl.value = soToFixed2(pct);
        return Math.round(subtotalSell * (pct / 100));
    }

    function soRecalc() {
        const rows = soGetRows();

        const subtotalSell = soComputeSubtotalSell(rows);

        const taxPct  = soClamp(soParseFloat(document.getElementById('so_tax_percentage')?.value), 0, 100);
        const fee      = Math.max(0, soParseInt(document.getElementById('so_fee_amount')?.value));
        const shipping = Math.max(0, soParseInt(document.getElementById('so_shipping_amount')?.value));

        const taxAmt = Math.round(subtotalSell * (taxPct / 100));
        const discAmt = soComputeHeaderDiscount(subtotalSell, taxAmt, fee, shipping);

        const grand = Math.max(0, Math.round(subtotalSell + taxAmt + fee + shipping - discAmt));

        document.getElementById('so_subtotal_text').innerText = soFormatRupiah(subtotalSell);
        document.getElementById('so_tax_text').innerText = soFormatRupiah(taxAmt);
        document.getElementById('so_discount_text').innerText = soFormatRupiah(discAmt);
        document.getElementById('so_fee_text').innerText = soFormatRupiah(fee);
        document.getElementById('so_shipping_text').innerText = soFormatRupiah(shipping);
        document.getElementById('so_grand_text').innerText = soFormatRupiah(grand);
    }

    function soGetProductTableComponentId(form) {
        const host = form?.querySelector('[data-so-product-table-host]');
        const componentRoot = host ? host.querySelector('[wire\\:id]') : null;
        return componentRoot ? componentRoot.getAttribute('wire:id') : null;
    }

    function soSetSubmitState(form, syncing) {
        const submitButtons = form?.querySelectorAll('button[type="submit"]') || [];
        submitButtons.forEach((button) => {
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }

            button.disabled = !!syncing;
            button.innerHTML = syncing
                ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Finalizing...'
                : button.dataset.originalHtml;
        });
    }

    function soFlushCartFieldChanges(form) {
        const selectors = [
            'input[name^="items["][name$="[quantity]"]',
            'input[name^="items["][name$="[price]"]',
            'input[name^="items["][name$="[discount_value]"]',
            'select[name^="items["][name$="[product_discount_type]"]'
        ];

        form.querySelectorAll(selectors.join(',')).forEach((element) => {
            element.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function soPrepareBeforeSubmit(form) {
        if (!form) return true;
        if (form.dataset.soReadyToSubmit === '1') return true;
        if (form.dataset.soSyncing === '1') return false;

        const componentId = soGetProductTableComponentId(form);
        if (!componentId || !window.Livewire || typeof window.Livewire.find !== 'function') {
            return true;
        }

        form.dataset.soSyncing = '1';
        soSetSubmitState(form, true);
        soFlushCartFieldChanges(form);

        window.setTimeout(() => {
            const component = window.Livewire.find(componentId);
            if (!component) {
                form.dataset.soSyncing = '0';
                soSetSubmitState(form, false);
                return;
            }

            component.call('syncAllRowsBeforeSubmit');
        }, 0);

        return false;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('soEditForm');
        const discEl = document.getElementById('so_header_discount_value');
        discEl?.addEventListener('input', function () {
            discEl.value = soNormalizeDecimalString(discEl.value);
        });

        document.addEventListener('input', function (e) {
            const id = e.target?.id || '';
            const n  = e.target?.getAttribute('name') || '';

            if (
                n.includes('[quantity]') ||
                n.includes('[price]') ||
                n.includes('[discount_value]') ||
                id === 'so_tax_percentage' ||
                id === 'so_header_discount_value' ||
                id === 'so_fee_amount' ||
                id === 'so_shipping_amount'
            ) {
                soRecalc();
            }
        });

        document.addEventListener('change', function (e) {
            const id = e.target?.id || '';
            if (id === 'so_discount_type') soRecalc();
        });

        soRecalc();

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('message.processed', () => {
                soRecalc();
            });
        }

        form?.addEventListener('submit', function (event) {
            if (!soPrepareBeforeSubmit(form)) {
                event.preventDefault();
            }
        });

        window.addEventListener('sale-order-cart-synced', function () {
            const activeForm = document.getElementById('soEditForm');
            if (!activeForm || activeForm.dataset.soSyncing !== '1') {
                return;
            }

            activeForm.dataset.soReadyToSubmit = '1';
            activeForm.dataset.soSyncing = '0';
            soSetSubmitState(activeForm, false);
            activeForm.requestSubmit();
        });
    });
</script>
@endpush
