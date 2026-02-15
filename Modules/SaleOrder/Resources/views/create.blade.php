@extends('layouts.app')

@section('title', 'Create Sale Order')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
@endsection

@section('content')
@php
    $items = old('items', $prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) $items = [];

    $oldTax = old('tax_percentage', 0);
    $oldDiscount = old('discount_percentage', 0);
    $oldShipping = old('shipping_amount', 0);
    $oldFee = old('fee_amount', 0);

    $oldDepositPct = old('deposit_percentage', '');
    $oldDepositAmt = old('deposit_amount', '');
    $oldDepositMethod = old('deposit_payment_method', '');
    $oldDepositCode = old('deposit_code', '');

    // ✅ NEW
    $oldDpReceived = old('deposit_received_amount', '');
    $oldUseMaxDpReceived = old('deposit_received_use_max', '');

    $oldAutoDiscount = old('auto_discount', '1'); // default ON
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

                        <hr>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit (%) (optional)</label>

                                <input type="text"
                                       inputmode="decimal"
                                       placeholder="0"
                                       name="deposit_percentage" id="so_deposit_percentage"
                                       class="form-control" value="{{ $oldDepositPct }}">

                                <div class="small text-muted">Kalau diisi, Deposit Amount otomatis ikut Grand Total.</div>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit Amount (optional)</label>
                                <input type="number" min="0" step="1"
                                       name="deposit_amount" id="so_deposit_amount"
                                       class="form-control" value="{{ $oldDepositAmt }}">
                                <div class="small text-muted">Kalau diisi manual, akan override persen.</div>
                            </div>

                            {{-- ✅ NEW: DP RECEIVED --}}
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">DP Received Amount (optional)</label>
                                <div class="d-flex gap-2">
                                    <input type="number" min="0" step="1"
                                           name="deposit_received_amount" id="so_deposit_received_amount"
                                           class="form-control" value="{{ $oldDpReceived }}">
                                    <label class="d-flex align-items-center gap-2 small mb-0" style="white-space:nowrap;">
                                        <input type="checkbox" name="deposit_received_use_max" id="so_deposit_received_use_max" value="1"
                                            {{ (string)$oldUseMaxDpReceived === '1' ? 'checked' : '' }}>
                                        Use Max
                                    </label>
                                </div>
                                <div class="small text-muted">
                                    Jika dicentang, DP Received otomatis = Deposit Amount.
                                </div>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Deposit Payment Method (optional)</label>
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
                                <label class="form-label">Deposit To (optional)</label>
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
    function soComputeOriginalSubtotal(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * (r.orig > 0 ? r.orig : r.price)), 0);
    }
    function soComputeDiffDiscount(rows) {
        return rows.reduce((sum, r) => {
            const base = r.orig > 0 ? r.orig : r.price;
            const diff = Math.max(0, base - r.price);
            return sum + (r.qty * diff);
        }, 0);
    }

    // =========================
    // Discount logic
    // =========================
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
                    pctEl.value = '';
                } else {
                    let pct = (amt / grand) * 100;
                    pct = soClamp(pct, 0, 100);
                    pctEl.value = soToFixed2(pct);
                }
            } else {
                let pct = soParseFloat(pctEl.value);
                pct = soClamp(pct, 0, 100);

                if (pct <= 0 || grand <= 0) {
                    amtEl.value = '';
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
                recEl.value = depAmt > 0 ? String(depAmt) : '';
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

            if (id === 'so_discount_percentage') {
                const el = document.getElementById('so_discount_percentage');
                if (el) el.value = soNormalizeDecimalString(el.value);

                const chk = document.getElementById('so_auto_discount');
                if (chk && chk.checked) chk.checked = false;
            }

            if (id === 'so_deposit_percentage') {
                const el = document.getElementById('so_deposit_percentage');
                if (el) el.value = soNormalizeDecimalString(el.value);
                window.__soDpMode = 'pct';
            }

            if (
                n.includes('[quantity]') ||
                n.includes('[price]') ||
                id === 'so_tax_percentage' ||
                id === 'so_discount_percentage' ||
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
            if (id === 'so_auto_discount') soRecalc();
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

    document.addEventListener('DOMContentLoaded', function () {
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
    });
</script>
@endpush
