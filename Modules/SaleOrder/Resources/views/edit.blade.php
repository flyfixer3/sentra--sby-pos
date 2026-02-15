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

    // financial old values
    $oldTax = (int) old('tax_percentage', (int)($saleOrder->tax_percentage ?? 0));
    $oldDiscount = (int) old('discount_percentage', (int)($saleOrder->discount_percentage ?? 0));
    $oldShipping = (int) old('shipping_amount', (int)($saleOrder->shipping_amount ?? 0));
    $oldFee = (int) old('fee_amount', (int)($saleOrder->fee_amount ?? 0));

    // deposit readonly display
    $dpPct = (int)($saleOrder->deposit_percentage ?? 0);
    $dpAmt = (int)($saleOrder->deposit_amount ?? 0);
    $dpMethod = (string)($saleOrder->deposit_payment_method ?? '');
    $dpCode = (string)($saleOrder->deposit_code ?? '');
@endphp

<div class="container-fluid mb-4">
    {{-- ✅ search bar (komponen yang sama seperti Transfer / Create) --}}
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
                        <a href="{{ route('sale-orders.show', $saleOrder->id) }}" class="btn btn-sm btn-light">
                            Back
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    {{-- ✅ DP readonly info --}}
                    <div class="alert alert-info">
                        <div class="small">
                            <strong>Deposit (DP) terkunci.</strong>
                            DP hanya bisa diinput saat Create Sale Order untuk menjaga audit & accounting tetap rapi.
                            <br>
                            @if($dpAmt > 0)
                                DP saat ini:
                                <strong>{{ format_currency($dpAmt) }}</strong>
                                @if($dpPct > 0)
                                    ({{ $dpPct }}%)
                                @endif
                                @if(!empty($dpMethod))
                                    • Method: <strong>{{ $dpMethod }}</strong>
                                @endif
                                @if(!empty($dpCode))
                                    • Deposit To: <strong>{{ $dpCode }}</strong>
                                @endif
                                <br>
                                <span class="text-muted">
                                    DP ini tidak masuk ke tabel Payments pada Invoice. Saat invoice dibuat dari Sale Delivery,
                                    DP hanya ditampilkan sebagai keterangan (allocated pro-rata).
                                </span>
                            @else
                                DP saat ini: <strong>Rp0</strong>
                            @endif
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

                        {{-- ✅ Items table: Livewire (selaras dengan Create) --}}
                        <div class="mt-2">
                            <livewire:sale-order.product-table :prefillItems="$items" />
                        </div>

                        <hr>

                        {{-- =========================
                             Financial (mirip Create)
                             ========================= --}}
                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Order Tax (%)</label>
                                <input type="number" min="0" max="100" name="tax_percentage" id="so_tax_percentage"
                                       class="form-control" value="{{ $oldTax }}" required>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Discount (%)</label>
                                <input type="number" min="0" max="100" name="discount_percentage" id="so_discount_percentage"
                                       class="form-control" value="{{ $oldDiscount }}" required>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Platform Fee</label>
                                <input type="number" min="0" name="fee_amount" id="so_fee_amount"
                                       class="form-control" value="{{ $oldFee }}" required>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Shipping</label>
                                <input type="number" min="0" name="shipping_amount" id="so_shipping_amount"
                                       class="form-control" value="{{ $oldShipping }}" required>
                            </div>
                        </div>

                        {{-- Summary --}}
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
                                    Grand Total dihitung dari item (qty × price) + tax - discount + fee + shipping.
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
        try {
            const n = Math.round(Number(num) || 0);
            return 'Rp' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        } catch (e) {
            return 'Rp0';
        }
    }

    function soGetRowMeta() {
        const pidInputs = document.querySelectorAll('input[name^="items"][name$="[product_id]"]');
        const rows = [];

        pidInputs.forEach((pidInput) => {
            const name = pidInput.getAttribute('name') || '';
            const idxMatch = name.match(/items\[(\d+)\]\[product_id\]/);
            if (!idxMatch) return;

            const idx = idxMatch[1];
            const pid = parseInt(pidInput.value || '0', 10);
            if (!pid || pid <= 0) return;

            const qtyInput = document.querySelector(`input[name="items[${idx}][quantity]"]`);
            const priceInput = document.querySelector(`input[name="items[${idx}][price]"]`);
            const origInput = document.querySelector(`input[name="items[${idx}][original_price]"]`);

            const qty = qtyInput ? parseInt(qtyInput.value || '0', 10) : 0;
            const price = priceInput ? parseInt(priceInput.value || '0', 10) : 0;
            const orig = origInput ? parseInt(origInput.value || '0', 10) : 0;

            rows.push({
                idx,
                pid,
                qty: Math.max(0, qty),
                price: Math.max(0, price),
                orig: Math.max(0, orig),
                qtyInput,
                priceInput,
                origInput,
            });
        });

        return rows;
    }

    function soComputeSubtotalBySell(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * r.price), 0);
    }

    function soComputeOriginalSubtotal(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * (r.orig > 0 ? r.orig : r.price)), 0);
    }

    function soComputeDiffDiscount(rows) {
        // Σ qty * max(0, orig - price)
        return rows.reduce((sum, r) => {
            const base = (r.orig > 0 ? r.orig : r.price);
            const diff = Math.max(0, base - r.price);
            return sum + (r.qty * diff);
        }, 0);
    }

    let soIsApplyingPrice = false;

    function soApplyManualDiscountToPrices(pct) {
        // pct: 0..100
        const rows = soGetRowMeta();
        if (rows.length === 0) return;

        soIsApplyingPrice = true;

        rows.forEach((r) => {
            const base = (r.orig > 0 ? r.orig : r.price);
            const newPrice = Math.max(0, Math.round(base * (1 - (pct / 100))));

            if (r.priceInput) {
                r.priceInput.value = String(newPrice);

                // trigger Livewire binding
                r.priceInput.dispatchEvent(new Event('input', { bubbles: true }));
                r.priceInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // release flag shortly after DOM events
        setTimeout(() => { soIsApplyingPrice = false; }, 0);
    }

    function soRecalc() {
        const rows = soGetRowMeta();
        const subtotalSell = soComputeSubtotalBySell(rows);

        const taxPct = parseFloat(document.getElementById('so_tax_percentage')?.value || '0') || 0;
        let discPct = parseFloat(document.getElementById('so_discount_percentage')?.value || '0') || 0;

        const fee = parseInt(document.getElementById('so_fee_amount')?.value || '0', 10) || 0;
        const shipping = parseInt(document.getElementById('so_shipping_amount')?.value || '0', 10) || 0;

        const auto = document.getElementById('so_auto_discount')?.checked === true;

        // ===== Discount amount rules =====
        let discountAmount = 0;

        if (auto) {
            const originalSubtotal = soComputeOriginalSubtotal(rows);
            const diffDiscount = soComputeDiffDiscount(rows);

            discountAmount = Math.round(diffDiscount);

            // set % with 2 decimals (NOT rounded to int)
            if (originalSubtotal > 0) {
                const pct = (diffDiscount / originalSubtotal) * 100;
                const fixed = Math.min(100, Math.max(0, pct));
                // show 2 decimals
                document.getElementById('so_discount_percentage').value = fixed.toFixed(2);
                discPct = fixed;
            }
        } else {
            // manual: amount from subtotalSell * discPct
            discountAmount = Math.round(subtotalSell * (discPct / 100));
        }

        const taxAmount = Math.round(subtotalSell * (taxPct / 100));
        const grand = Math.round(subtotalSell + taxAmount - discountAmount + fee + shipping);

        document.getElementById('so_subtotal_text').innerText = soFormatRupiah(subtotalSell);
        document.getElementById('so_tax_text').innerText = soFormatRupiah(taxAmount);
        document.getElementById('so_discount_text').innerText = soFormatRupiah(discountAmount);
        document.getElementById('so_fee_text').innerText = soFormatRupiah(fee);
        document.getElementById('so_shipping_text').innerText = soFormatRupiah(shipping);
        document.getElementById('so_grand_text').innerText = soFormatRupiah(grand);

        // Deposit auto
        const dpPctEl = document.getElementById('so_deposit_percentage');
        const dpAmtEl = document.getElementById('so_deposit_amount');

        if (dpPctEl && dpAmtEl) {
            const dpPct = parseInt(dpPctEl.value || '0', 10) || 0;
            const dpAmtNow = parseInt(dpAmtEl.value || '0', 10) || 0;

            if (dpPct > 0 && (!dpAmtEl.value || dpAmtNow === 0)) {
                const autoDp = Math.round(grand * (dpPct / 100));
                dpAmtEl.value = autoDp;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const discountEl = document.getElementById('so_discount_percentage');
        const autoEl = document.getElementById('so_auto_discount');

        // input changes trigger recalc
        document.addEventListener('input', function (e) {
            if (soIsApplyingPrice) return;

            const n = e.target?.getAttribute('name') || '';
            if (
                n.includes('[quantity]') ||
                n.includes('[price]') ||
                e.target?.id === 'so_tax_percentage' ||
                e.target?.id === 'so_fee_amount' ||
                e.target?.id === 'so_shipping_amount' ||
                e.target?.id === 'so_deposit_percentage'
            ) {
                soRecalc();
            }
        });

        // toggle auto/manual
        autoEl?.addEventListener('change', function () {
            soRecalc();
        });

        // manual discount: when user edits % and auto=false => update prices
        discountEl?.addEventListener('input', function () {
            const auto = autoEl?.checked === true;
            if (auto) {
                soRecalc();
                return;
            }

            const pct = parseFloat(discountEl.value || '0') || 0;
            const safe = Math.min(100, Math.max(0, pct));

            // apply discount to all row prices (from original_price)
            soApplyManualDiscountToPrices(safe);

            // recalc totals after price updates
            soRecalc();
        });

        soRecalc();
    });
</script>
@endpush
