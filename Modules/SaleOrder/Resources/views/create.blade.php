@extends('layouts.app')

@section('title', 'Create Sale Order')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
@endsection

@push('page_css')
<style>
    /* ========= DP Received modern control ========= */
    .so-inputgroup-tight .input-group-text{
        background:#f8f9fa;
        border-left:0;
    }
    .so-switch-wrap{
        display:flex;
        align-items:center;
        gap:10px;
        padding:0 12px;
        min-height:38px;
        border:1px solid rgba(0,0,0,.125);
        border-left:0;
        border-top-right-radius:.375rem;
        border-bottom-right-radius:.375rem;
        background:#f8f9fa;
        user-select:none;
        white-space:nowrap;
    }
    .so-switch-wrap .form-check{
        margin:0;
        padding-left:0;
        display:flex;
        align-items:center;
        gap:8px;
    }
    .so-switch-wrap .form-check-input{
        margin-left:0;
        cursor:pointer;
    }
    .so-switch-wrap .form-check-label{
        cursor:pointer;
        font-size:12px;
        color:#495057;
    }
    .so-help-hint{
        display:flex;
        align-items:flex-start;
        gap:8px;
    }
    .so-help-dot{
        width:8px;height:8px;border-radius:999px;
        background:rgba(13,110,253,.35);
        margin-top:6px;
        flex:0 0 auto;
    }
</style>
@endpush

@section('content')
@php
    $items = old('items', $prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) $items = [];

    $oldTax = old('tax_percentage', 0);
    $oldDiscountType = old('discount_type', 'percentage');
    $oldHeaderDiscountValue = old('header_discount_value', old('discount_percentage', 0));
    $oldShipping = old('shipping_amount', 0);
    $oldFee = old('fee_amount', 0);

    // ✅ deposit default 0 (NOT empty)
    $oldDepositPct = old('deposit_percentage', 0);
    $oldDepositAmt = old('deposit_amount', 0);
    $oldDepositMethod = old('deposit_payment_method', '');
    $oldDepositCode = old('deposit_code', '');

    // ✅ dp received default 0 (NOT empty)
    $oldDpReceived = old('deposit_received_amount', 0);
    $oldUseMaxDpReceived = old('deposit_received_use_max', '');

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
                        <strong>Create Sale Order</strong>
                        @if(!empty($prefillRefText))
                            <div class="text-muted small">{{ $prefillRefText }}</div>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    <form action="{{ route('sale-orders.store') }}" method="POST" id="soForm">
                        @csrf

                        <input type="hidden" name="source" value="{{ $source }}">

                        @if($source === 'quotation')
                            <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                        @endif

                        @if($source === 'sale')
                            <input type="hidden" name="sale_id" value="{{ request('sale_id') }}">
                        @endif

                        @if($source === 'lead')
                            <input type="hidden" name="lead_id" value="{{ request('lead_id') }}">
                            <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control"
                                       value="{{ old('date', $prefillDate) }}" required>
                            </div>

                            <div class="col-md-9 mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">-- Choose --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (int) old('customer_id', $prefillCustomerId) === (int) $c->id ? 'selected' : '' }}>
                                            {{ $c->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" rows="2">{{ old('note', $prefillNote) }}</textarea>
                                <div class="small text-muted mt-1">
                                    Warehouse tidak dipilih di Sale Order. Warehouse ditentukan saat membuat Sale Delivery (Stock Out).
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
                                            class="form-control input-group-append"
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

                        <hr>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit (%)</label>

                                <input type="text"
                                       inputmode="decimal"
                                       placeholder="0"
                                       name="deposit_percentage" id="so_deposit_percentage"
                                       class="form-control" value="{{ $oldDepositPct }}" required>

                                <div class="small text-muted">Kalau diisi, Deposit Amount otomatis ikut Grand Total.</div>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit Amount</label>
                                <input type="number" min="0" step="1"
                                       name="deposit_amount" id="so_deposit_amount"
                                       class="form-control" value="{{ $oldDepositAmt }}" required>
                                <div class="small text-muted">Kalau diisi manual, akan override persen.</div>
                            </div>

                            {{-- ✅ DP RECEIVED --}}
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">DP Received Amount</label>

                                <div class="input-group">
                                    <input type="number" min="0" step="1"
                                        name="deposit_received_amount" id="so_deposit_received_amount"
                                        class="form-control" value="{{ $oldDpReceived }}" required>

                                    <span class="input-group-text">
                                        <label class="form-check mb-0 d-flex align-items-center gap-2" style="white-space:nowrap;">
                                            <input class="form-check-input mt-0"
                                                type="checkbox"
                                                name="deposit_received_use_max"
                                                id="so_deposit_received_use_max"
                                                value="1"
                                                {{ (string)$oldUseMaxDpReceived === '1' ? 'checked' : '' }}>
                                            <span class="form-check-label small">Use Max</span>
                                        </label>
                                    </span>
                                </div>

                                <div class="small text-muted mt-1">
                                    Jika dicentang, DP Received otomatis = Deposit Amount.
                                </div>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit Payment Method</label>
                                <select class="form-control" name="deposit_payment_method" id="so_deposit_payment_method">
                                    <option value="">-- Choose --</option>
                                    <option value="Cash" {{ $oldDepositMethod === 'Cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="Bank Transfer" {{ $oldDepositMethod === 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="Credit Card" {{ $oldDepositMethod === 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                    <option value="Other" {{ $oldDepositMethod === 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                <div class="small text-muted">Wajib diisi hanya jika DP (planned/received) > 0.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit To</label>
                                <select class="form-control" name="deposit_code" id="so_deposit_code">
                                    <option value="">-- Choose Deposit --</option>
                                    @foreach(\App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                        ->where('accounting_accounts.is_active', '=', '1')
                                        ->where('accounting_accounts.account_number', 3)
                                        ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                        ->get(); as $account)
                                        <option value="{{ $account->subaccount_number }}" {{ $oldDepositCode === $account->subaccount_number ? 'selected' : '' }}>
                                            ({{ $account->subaccount_number }}) - {{ $account->subaccount_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="small text-muted">Wajib diisi hanya jika DP (planned/received) > 0.</div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <div class="small">
                                <strong>Catatan DP:</strong>
                                DP Planned = rencana / maksimal DP.
                                DP Received = DP yang benar-benar diterima (yang masuk accounting via SalePayment).
                                Saat Invoice dibuat dari Sale Delivery, sistem menampilkan DP sebagai <strong>keterangan (allocated pro-rata)</strong>.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Sale Order
                            </button>

                            <a href="{{ route('sale-orders.index') }}" class="btn btn-secondary">
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
    // =========================
    // Helpers
    // =========================
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

    // =========================
    // Read rows
    // =========================
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

    // =========================
    // Deposit sync (robust for Livewire re-render)
    // =========================
    window.__soDpMode = window.__soDpMode || 'pct'; // 'pct' or 'amt'
    window.__soDpSyncing = false;

    function soSyncDeposit(grandTotal) {
        const pctEl = document.getElementById('so_deposit_percentage');
        const amtEl = document.getElementById('so_deposit_amount');
        if (!pctEl || !amtEl) return;
        if (window.__soDpSyncing) return;

        const grand = Math.max(0, soParseInt(grandTotal));
        window.__soDpSyncing = true;

        try {
            if (window.__soDpMode === 'amt') {
                const amt = Math.max(0, soParseInt(amtEl.value));
                if (grand <= 0) {
                    pctEl.value = '0';
                } else {
                    let pct = (amt / grand) * 100;
                    pct = soClamp(pct, 0, 100);
                    pctEl.value = soToFixed2(pct);
                }
            } else {
                let pct = soParseFloat(pctEl.value);
                pct = soClamp(pct, 0, 100);

                if (pct <= 0 || grand <= 0) {
                    amtEl.value = '0';
                } else {
                    const amt = Math.round(grand * (pct / 100));
                    amtEl.value = String(Math.max(0, amt));
                }
            }
        } finally {
            window.__soDpSyncing = false;
        }
    }

    // =========================
    // DP Received logic
    // =========================
    window.__soDpReceivedSyncing = false;

    function soSyncDpReceived() {
        const recEl = document.getElementById('so_deposit_received_amount');
        const useEl = document.getElementById('so_deposit_received_use_max');
        const depAmtEl = document.getElementById('so_deposit_amount');
        if (!recEl || !useEl || !depAmtEl) return;
        if (window.__soDpReceivedSyncing) return;

        if (useEl.checked) {
            window.__soDpReceivedSyncing = true;
            try {
                const depAmt = Math.max(0, soParseInt(depAmtEl.value));
                recEl.value = String(depAmt);
            } finally {
                window.__soDpReceivedSyncing = false;
            }
        }
    }

    // =========================
    // Recalc summary
    // =========================
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

        soSyncDeposit(grand);
        soSyncDpReceived();
    }

    // =========================
    // Bind events (safe)
    // =========================
    function soBindEventsOnce() {
        if (window.__soBoundOnce) return;
        window.__soBoundOnce = true;

        document.addEventListener('input', function (e) {
            const id = e.target?.id || '';
            const n  = e.target?.getAttribute('name') || '';

            if (id === 'so_header_discount_value') {
                const el = document.getElementById('so_header_discount_value');
                if (el) el.value = soNormalizeDecimalString(el.value);
            }

            if (id === 'so_deposit_percentage') {
                const el = document.getElementById('so_deposit_percentage');
                if (el) el.value = soNormalizeDecimalString(el.value);
                window.__soDpMode = 'pct';
            }

            if (
                n.includes('[quantity]') ||
                n.includes('[price]') ||
                n.includes('[discount_value]') ||
                id === 'so_tax_percentage' ||
                id === 'so_header_discount_value' ||
                id === 'so_fee_amount' ||
                id === 'so_shipping_amount' ||
                id === 'so_deposit_percentage' ||
                id === 'so_deposit_amount'
            ) {
                soRecalc();
            }
        });

        document.addEventListener('change', function (e) {
            const id = e.target?.id || '';
            if (id === 'so_discount_type') soRecalc();
            if (id === 'so_deposit_received_use_max') soRecalc();
        });
    }

    function soBindDepositInputs() {
        const dpPctEl = document.getElementById('so_deposit_percentage');
        const dpAmtEl = document.getElementById('so_deposit_amount');

        if (dpPctEl && !dpPctEl.__bound) {
            dpPctEl.__bound = true;
            dpPctEl.addEventListener('input', function () {
                if (window.__soDpSyncing) return;
                window.__soDpMode = 'pct';
                soRecalc();
            });
        }

        if (dpAmtEl && !dpAmtEl.__bound) {
            dpAmtEl.__bound = true;
            dpAmtEl.addEventListener('input', function () {
                if (window.__soDpSyncing) return;
                window.__soDpMode = 'amt';
                soRecalc();
            });
        }
    }

    function soBindDpReceivedInputs() {
        const recEl = document.getElementById('so_deposit_received_amount');
        const useEl = document.getElementById('so_deposit_received_use_max');

        if (useEl && !useEl.__bound) {
            useEl.__bound = true;
            useEl.addEventListener('change', function () {
                soRecalc();
            });
        }

        if (recEl && !recEl.__bound) {
            recEl.__bound = true;
            recEl.addEventListener('input', function () {
                const u = document.getElementById('so_deposit_received_use_max');
                if (u && u.checked) u.checked = false;
            });
        }
    }

    function soInitDpMode() {
        const dpPctEl = document.getElementById('so_deposit_percentage');
        const dpAmtEl = document.getElementById('so_deposit_amount');
        if (dpAmtEl && soParseInt(dpAmtEl.value) > 0 && (!dpPctEl || dpPctEl.value === '' || soParseFloat(dpPctEl.value) === 0)) {
            window.__soDpMode = 'amt';
        } else {
            window.__soDpMode = 'pct';
        }
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
        const form = document.getElementById('soForm');
        soBindEventsOnce();
        soBindDepositInputs();
        soBindDpReceivedInputs();
        soInitDpMode();
        soRecalc();

        // ✅ Livewire re-render safe hook
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('message.processed', () => {
                soBindDepositInputs();
                soBindDpReceivedInputs();
                soRecalc();
            });
        }

        form?.addEventListener('submit', function (event) {
            if (!soPrepareBeforeSubmit(form)) {
                event.preventDefault();
            }
        });

        window.addEventListener('sale-order-cart-synced', function () {
            const activeForm = document.getElementById('soForm');
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
