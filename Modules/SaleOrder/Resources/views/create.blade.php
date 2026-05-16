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
    .so-customer-autocomplete {
        position: relative;
    }
    .so-customer-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 240px;
        overflow-y: auto;
        border: 1px solid rgba(0,0,0,.125);
        background: #fff;
        border-radius: .375rem;
        margin-top: 4px;
        box-shadow: 0 8px 18px rgba(0,0,0,.08);
    }
    .so-customer-results .list-group-item {
        cursor: pointer;
    }
    .so-customer-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: flex-start;
    }
    .so-customer-row .input-group {
        flex: 1 1 260px;
    }
    .so-add-vehicle-trigger {
        height: calc(1.5em + .75rem + 2px);
        white-space: nowrap;
    }
</style>
@endpush

@section('third_party_scripts')
    <script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
@endsection

@section('content')
@php
    $items = old('items', $prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) $items = [];
    $requiredMark = '<span class="text-danger">*</span>';

    $oldTax = old('tax_percentage', $prefillTaxPercentage ?? 0);
    $oldDiscountType = old('discount_type', $prefillDiscountType ?? 'percentage');
    $oldHeaderDiscountValue = old('header_discount_value', $prefillHeaderDiscountValue ?? old('discount_percentage', 0));
    $oldShipping = old('shipping_amount', $prefillShippingAmount ?? 0);
    $oldFee = old('fee_amount', $prefillFeeAmount ?? 0);
    $oldEstimatedArrivalDays = old('estimated_arrival_days');

    // ✅ deposit default 0 (NOT empty)
    $oldDepositPct = old('deposit_percentage', 0);
    $oldDepositAmt = old('deposit_amount', 0);
    $oldDepositMethod = old('deposit_payment_method', '');
    $oldDepositCode = old('deposit_code', '');

    // ✅ dp received default 0 (NOT empty)
    $oldDpReceived = old('deposit_received_amount', 0);
    $oldUseMaxDpReceived = old('deposit_received_use_max', '');

    $selectedCustomerId = (int) old('customer_id', $prefillCustomerId);
    $selectedCustomerLabel = '';
    if ($selectedCustomerId > 0 && isset($customers)) {
        $selectedCustomer = $customers->firstWhere('id', $selectedCustomerId);
        if ($selectedCustomer) {
            $selectedCustomerLabel = (string) $selectedCustomer->customer_name;
            $selectedSecondary = $selectedCustomer->customer_phone ?: $selectedCustomer->customer_email;
            if (!empty($selectedSecondary)) {
                $selectedCustomerLabel .= ' - ' . $selectedSecondary;
            }
        }
    }
    $oldCustomerSearch = old('customer_search');
    if (!is_null($oldCustomerSearch) && $oldCustomerSearch !== '') {
        $selectedCustomerLabel = $oldCustomerSearch;
    }

    $itemsErrorMessage = $errors->first('items') ?: $errors->first('items.*');

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

                      <form action="{{ route('sale-orders.store') }}" method="POST" id="soForm" novalidate
                          data-confirm-submit="true"
                          data-confirm-title="Confirm Submit?"
                          data-confirm-message="Please review all data and item rows carefully before submitting. This action may affect inventory, delivery, payment, or accounting records."
                          data-confirm-confirm-text="Yes, submit"
                          data-confirm-cancel-text="Cancel"
                          data-confirm-icon="warning"
                          data-confirm-require-items="true"
                          data-vehicles-url-template="{{ route('customers.vehicles.json', ['customer' => 'CUSTOMER_ID']) }}"
                          data-store-url-template="{{ route('customers.vehicles.store-ajax', ['customer' => 'CUSTOMER_ID']) }}">
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
                                <label class="form-label" for="so_date">Date {!! $requiredMark !!}</label>
                                <input type="date" name="date" id="so_date" class="form-control @error('date') is-invalid @enderror"
                                       value="{{ old('date', $prefillDate) }}" required>
                                @error('date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div id="so_date_client_error" class="invalid-feedback d-none">Date is required.</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="so_estimated_arrival_days">Estimated Arrival (Days)</label>
                                <input type="number"
                                       min="1"
                                       step="1"
                                       name="estimated_arrival_days"
                                       id="so_estimated_arrival_days"
                                       class="form-control @error('estimated_arrival_days') is-invalid @enderror"
                                       value="{{ $oldEstimatedArrivalDays }}"
                                       placeholder="30">
                                @error('estimated_arrival_days')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="small text-muted mt-1">
                                    Required only when this Sale Order has pending stock / shortage. Leave empty for ready-stock orders.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="so_customer_id">Customer {!! $requiredMark !!}</label>
                                <div class="so-customer-autocomplete">
                                    <div class="so-customer-row">
                                        <div class="input-group">
                                            <input type="text"
                                                   id="so_customer_search"
                                                   name="customer_search"
                                                   class="form-control @error('customer_id') is-invalid @enderror"
                                                   placeholder="Search customer by name, phone, or email..."
                                                   autocomplete="off"
                                                   value="{{ $selectedCustomerLabel }}"
                                                   data-selected-id="{{ $selectedCustomerId }}"
                                                   data-selected-label="{{ $selectedCustomerLabel }}"
                                                   data-search-url="{{ route('customers.search') }}">
                                            <button class="btn btn-outline-secondary" type="button" id="so_customer_clear" aria-label="Clear customer">&times;</button>
                                        </div>
                                        @can('edit_customers')
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary so-add-vehicle-trigger"
                                                id="so_add_vehicle_btn"
                                                data-toggle="modal"
                                                data-target="#soAddVehicleModal"
                                                @if($selectedCustomerId <= 0 || trim($selectedCustomerLabel) === '') disabled @endif
                                            >
                                                + Add Vehicle
                                            </button>
                                        @endcan
                                    </div>
                                    <input type="hidden" name="customer_id" id="so_customer_id" value="{{ $selectedCustomerId }}">
                                    <div id="so_customer_results" class="so-customer-results list-group d-none"></div>
                                </div>
                                @error('customer_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div id="so_customer_client_error" class="invalid-feedback d-none">Customer is required.</div>
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
                            <livewire:sale-order.product-table
                                :prefillItems="$items"
                                :customerId="(int) old('customer_id', $prefillCustomerId)"
                            />
                        </div>
                        <div id="so_vehicle_success" class="alert alert-success d-none mt-2" role="alert"></div>
                        <div id="so_vehicle_error" class="alert alert-danger d-none mt-2" role="alert"></div>
                        <input type="hidden" name="sale_order_items_json" id="so_items_json" value="[]">
                        <div id="so_items_client_error"
                             class="alert alert-danger mt-2 {{ $itemsErrorMessage ? '' : 'd-none' }}"
                             role="alert"
                             data-default-message="Please add at least one product.">
                            {{ $itemsErrorMessage ?: 'Please add at least one product.' }}
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
                                    Grand Total dihitung dari item subtotal setelah item discount + tax + fee + shipping.<br>
                                    Header discount mengurangi Grand Total secara terpisah.
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="so_deposit_percentage">Deposit (%)</label>

                                <input type="text"
                                       inputmode="decimal"
                                       placeholder="0"
                                       name="deposit_percentage" id="so_deposit_percentage"
                                       class="form-control" value="{{ $oldDepositPct }}" required>

                                <div class="small text-muted">Kalau diisi, Deposit Amount otomatis ikut Grand Total.</div>
                            </div>

                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="so_deposit_amount">Deposit Amount</label>
                                <input type="number" min="0" step="1"
                                       name="deposit_amount" id="so_deposit_amount"
                                       class="form-control" value="{{ $oldDepositAmt }}" required>
                                <div class="small text-muted">Kalau diisi manual, akan override persen.</div>
                            </div>

                            {{-- ✅ DP RECEIVED --}}
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="so_deposit_received_amount">DP Received Amount</label>

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
                                <label class="form-label" for="so_deposit_payment_method">Deposit Payment Method <span class="text-danger d-none" data-dp-required-marker>*</span></label>
                                <select class="form-control @error('deposit_payment_method') is-invalid @enderror" name="deposit_payment_method" id="so_deposit_payment_method">
                                    <option value="">-- Choose --</option>
                                    <option value="Cash" {{ $oldDepositMethod === 'Cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="Bank Transfer" {{ $oldDepositMethod === 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                    <option value="Credit Card" {{ $oldDepositMethod === 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                                    <option value="Other" {{ $oldDepositMethod === 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('deposit_payment_method')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="small text-muted">Wajib diisi hanya jika DP (planned/received) > 0.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="so_deposit_code">Deposit To <span class="text-danger d-none" data-dp-required-marker>*</span></label>
                                <select class="form-control @error('deposit_code') is-invalid @enderror" name="deposit_code" id="so_deposit_code">
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
                                @error('deposit_code')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
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

@can('edit_customers')
    <div class="modal fade" id="soAddVehicleModal" tabindex="-1" role="dialog" aria-labelledby="soAddVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="#" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="soAddVehicleModalLabel">Add Vehicle</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @include('people::customers.partials.vehicle-form', ['vehicle' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Vehicle</button>
                </div>
            </form>
        </div>
    </div>
@endcan
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
            const discountValueInput = document.querySelector(`[name="items[${idx}][discount_value]"]`);
            const discountTypeInput = document.querySelector(`[name="items[${idx}][product_discount_type]"]`);

            const qty  = qtyInput ? soParseInt(qtyInput.value) : 0;
            const sellPrice= priceInput ? soParseInt(priceInput.value) : 0;
            const orig = origInput ? soParseInt(origInput.value) : 0;
            const discountType = discountTypeInput?.value === 'percentage' ? 'percentage' : 'fixed';
            const discountAmount = discountType === 'percentage'
                ? Math.round(Math.max(0, sellPrice) * (soClamp(soParseFloat(discountValueInput?.value), 0, 100) / 100))
                : Math.min(Math.max(0, soParseInt(discountValueInput?.value)), Math.max(0, sellPrice));
            const netPrice = Math.max(0, sellPrice - discountAmount);

            if (qty > 0) rows.push({ pid, qty, price: netPrice, sellPrice: Math.max(0, sellPrice), orig: Math.max(0, orig) });
        });

        return rows;
    }

    function soFinalizeRowInputsBeforeSubmit(form) {
        if (!form) return;

        form.querySelectorAll('input[name^="items"][name$="[product_id]"]').forEach((pidInput) => {
            const name = pidInput.getAttribute('name') || '';
            const idxMatch = name.match(/items\[(\d+)\]\[product_id\]/);
            if (!idxMatch) return;

            const idx = idxMatch[1];
            const getField = (field) => form.querySelector(`[name="items[${idx}][${field}]"]`);

            const qtyInput = getField('quantity');
            const priceInput = getField('price');
            const originalInput = getField('original_price');
            const unitInput = getField('unit_price');
            const discountInput = getField('product_discount_amount');
            const discountValueInput = getField('discount_value');
            const discountTypeInput = getField('product_discount_type');
            const subTotalInput = getField('sub_total');

            const qty = Math.max(1, soParseInt(qtyInput?.value));
            const price = Math.max(0, soParseInt(priceInput?.value));
            const original = Math.max(0, soParseInt(originalInput?.value || price));
            const unit = price;
            const discountType = discountTypeInput?.value === 'percentage' ? 'percentage' : 'fixed';
            const discountAmount = discountType === 'percentage'
                ? Math.round(unit * (soClamp(soParseFloat(discountValueInput?.value), 0, 100) / 100))
                : Math.min(Math.max(0, soParseInt(discountValueInput?.value)), unit);
            const netPrice = Math.max(0, unit - discountAmount);
            const subTotal = qty * netPrice;

            if (qtyInput) qtyInput.value = String(qty);
            if (priceInput) priceInput.value = String(price);
            if (originalInput) originalInput.value = String(original);
            if (unitInput) unitInput.value = String(unit);
            if (discountInput) discountInput.value = String(discountAmount);
            if (subTotalInput) subTotalInput.value = String(subTotal);

            if (discountValueInput && discountType === 'fixed') discountValueInput.value = String(discountAmount);
            if (discountValueInput && discountType === 'percentage') discountValueInput.value = soToFixed2(soClamp(soParseFloat(discountValueInput.value), 0, 100));
        });
    }

    function soSyncItemsJsonFallback(form) {
        const target = document.getElementById('so_items_json');
        if (!target) return;

        const rows = [];
        document.querySelectorAll('input[name^="items"][name$="[product_id]"]').forEach((pidInput) => {
            const name = pidInput.getAttribute('name') || '';
            const idxMatch = name.match(/items\[(\d+)\]\[product_id\]/);
            if (!idxMatch) return;

            const idx = idxMatch[1];
            const getField = (field) => document.querySelector(`[name="items[${idx}][${field}]"]`);
            const productId = soParseInt(pidInput.value);

            if (productId <= 0) return;

            rows.push({
                product_id: productId,
                product_name: getField('product_name')?.value || null,
                product_code: getField('product_code')?.value || null,
                quantity: soParseInt(getField('quantity')?.value || 0),
                price: soParseInt(getField('price')?.value || 0),
                original_price: soParseInt(getField('original_price')?.value || getField('unit_price')?.value || 0),
                unit_price: soParseInt(getField('unit_price')?.value || getField('price')?.value || 0),
                product_discount_amount: soParseInt(getField('product_discount_amount')?.value || 0),
                discount_value: getField('discount_value')?.value || 0,
                product_discount_type: getField('product_discount_type')?.value || 'fixed',
                sub_total: soParseInt(getField('sub_total')?.value || 0),
                installation_type: getField('installation_type')?.value || 'item_only',
                customer_vehicle_id: getField('customer_vehicle_id')?.value || null
            });
        });

        target.value = JSON.stringify(rows);
    }

    function soSetItemsError(show, message) {
        const itemError = document.getElementById('so_items_client_error');
        if (!itemError) return;

        const defaultMessage = itemError.getAttribute('data-default-message') || 'Please add at least one product.';
        itemError.textContent = message || defaultMessage;

        if (show) {
            itemError.classList.remove('d-none');
        } else {
            itemError.classList.add('d-none');
        }
    }

    function soUpdateItemsErrorState() {
        const hasRows = soGetRows().length > 0;
        const shouldShow = !!window.__soSubmitAttempted && !hasRows;
        soSetItemsError(shouldShow);
    }

    function soValidateRequiredBeforeSubmit() {
        let valid = true;
        const dateInput = document.getElementById('so_date');
        const customerInput = document.getElementById('so_customer_id');
        const customerSearch = document.getElementById('so_customer_search');
        const dateError = document.getElementById('so_date_client_error');
        const customerError = document.getElementById('so_customer_client_error');

        if (!dateInput?.value) {
            valid = false;
            dateInput?.classList.add('is-invalid');
            dateError?.classList.remove('d-none');
        } else {
            dateInput?.classList.remove('is-invalid');
            dateError?.classList.add('d-none');
        }

        if (!customerInput?.value) {
            valid = false;
            customerSearch?.classList.add('is-invalid');
            customerError?.classList.remove('d-none');
        } else {
            customerSearch?.classList.remove('is-invalid');
            customerError?.classList.add('d-none');
        }

        return valid;
    }

    function soComputeSubtotalSell(rows) {
        return rows.reduce((sum, r) => sum + (r.qty * r.price), 0);
    }
    function soComputeHeaderDiscount(subtotalSell) {
        const type = document.getElementById('so_discount_type')?.value === 'fixed' ? 'fixed' : 'percentage';
        const valueEl = document.getElementById('so_header_discount_value');
        const pctEl = document.getElementById('so_discount_percentage');
        const rawValue = soParseFloat(valueEl?.value);

        if (type === 'fixed') {
            const base = Math.max(0, subtotalSell);
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

    function soToggleDepositRequiredMarkers() {
        const depositAmount = soParseInt(document.getElementById('so_deposit_amount')?.value);
        const dpReceived = soParseInt(document.getElementById('so_deposit_received_amount')?.value);
        const required = depositAmount > 0 || dpReceived > 0;

        document.querySelectorAll('[data-dp-required-marker]').forEach((marker) => {
            marker.classList.toggle('d-none', !required);
        });
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

        const discAmt = soComputeHeaderDiscount(subtotalSell);
        const taxable = Math.max(0, subtotalSell - discAmt);
        const taxAmt = Math.round(taxable * (taxPct / 100));

        const grand = Math.max(0, Math.round(taxable + taxAmt + fee + shipping));

        document.getElementById('so_subtotal_text').innerText = soFormatRupiah(subtotalSell);
        document.getElementById('so_tax_text').innerText = soFormatRupiah(taxAmt);
        document.getElementById('so_discount_text').innerText = soFormatRupiah(discAmt);
        document.getElementById('so_fee_text').innerText = soFormatRupiah(fee);
        document.getElementById('so_shipping_text').innerText = soFormatRupiah(shipping);
        document.getElementById('so_grand_text').innerText = soFormatRupiah(grand);

        soSyncDeposit(grand);
        soSyncDpReceived();
        soToggleDepositRequiredMarkers();
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
                id === 'so_deposit_amount' ||
                id === 'so_deposit_received_amount'
            ) {
                soRecalc();
                soUpdateItemsErrorState();
            }
        });

        document.addEventListener('change', function (e) {
            const id = e.target?.id || '';
            if (id === 'so_discount_type') soRecalc();
            if (id === 'so_deposit_received_use_max') soRecalc();
            soUpdateItemsErrorState();
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
        soFinalizeRowInputsBeforeSubmit(form);
        soSyncItemsJsonFallback(form);
        soRecalc();

        if (form.getAttribute('data-confirmed-submit') === 'true') {
            return true;
        }

        const validRows = soGetRows();
        const requiredFieldsValid = soValidateRequiredBeforeSubmit();
        soSetItemsError(validRows.length === 0);

        if (!requiredFieldsValid || validRows.length === 0) {
            const firstError = document.querySelector('.is-invalid, #so_items_client_error:not(.d-none)');
            firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        if (form.dataset.soReadyToSubmit === '1') return true;
        if (form.dataset.soSyncing === '1') return false;

        const componentId = soGetProductTableComponentId(form);
        if (!componentId || !window.Livewire || typeof window.Livewire.find !== 'function') {
            return true;
        }

        const component = window.Livewire.find(componentId);
        if (!component || typeof component.call !== 'function') {
            return true;
        }

        form.dataset.soSyncing = '1';
        soSetSubmitState(form, true);
        const syncResult = component.call('syncAllRowsBeforeSubmit');
        if (syncResult && typeof syncResult.catch === 'function') {
            syncResult.catch(function () {
                form.dataset.soSyncing = '0';
                soSetSubmitState(form, false);
            });
        }

        return false;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('soForm');
        const initialItemErrorVisible = !!(document.getElementById('so_items_client_error') && !document.getElementById('so_items_client_error').classList.contains('d-none'));
        window.__soSubmitAttempted = window.__soSubmitAttempted || initialItemErrorVisible;
        soBindEventsOnce();
        soBindDepositInputs();
        soBindDpReceivedInputs();
        soInitDpMode();
        soRecalc();

        function soGetSelectedCustomerIdForVehicle() {
            const input = document.getElementById('so_customer_search');
            const hidden = document.getElementById('so_customer_id');

            const hiddenId = (hidden?.value || '').toString().trim();
            const selectedId = (input?.dataset.selectedId || '').toString().trim();
            const selectedLabel = (input?.dataset.selectedLabel || '').toString().trim();
            const visibleLabel = (input?.value || '').toString().trim();

            if (!hiddenId || !selectedId) return '';
            if (hiddenId !== selectedId) return '';
            if (!selectedLabel || !visibleLabel) return '';
            if (selectedLabel !== visibleLabel) return '';

            return hiddenId;
        }

        function notifySaleOrderCustomerChanged() {
            const customerId = soGetSelectedCustomerIdForVehicle();
            if (window.Livewire && typeof window.Livewire.emit === 'function') {
                window.Livewire.emit('saleOrderCustomerChanged', customerId);
            }
        }

        document.getElementById('so_customer_id')?.addEventListener('change', notifySaleOrderCustomerChanged);
        document.addEventListener('livewire:load', notifySaleOrderCustomerChanged);
        notifySaleOrderCustomerChanged();

        function soCustomerHideResults() {
            const results = document.getElementById('so_customer_results');
            if (!results) return;
            results.classList.add('d-none');
            results.innerHTML = '';
        }

        function soCustomerRenderResults(items) {
            const results = document.getElementById('so_customer_results');
            if (!results) return;

            if (!items || items.length === 0) {
                results.classList.add('d-none');
                results.innerHTML = '';
                return;
            }

            results.innerHTML = items.map((item) => {
                const text = (item.text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return '<button type="button" class="list-group-item list-group-item-action" data-id="' + item.id + '" data-text="' + text + '">' + text + '</button>';
            }).join('');
            results.classList.remove('d-none');
        }

        function soCustomerSelect(item) {
            const input = document.getElementById('so_customer_search');
            const hidden = document.getElementById('so_customer_id');

            if (input) {
                input.value = item.text || '';
                input.dataset.selectedId = String(item.id || '');
                input.dataset.selectedLabel = item.text || '';
                input.classList.remove('is-invalid');
            }

            if (hidden) {
                hidden.value = item.id || '';
            }

            const customerError = document.getElementById('so_customer_client_error');
            customerError?.classList.add('d-none');

            soCustomerHideResults();
            notifySaleOrderCustomerChanged();
            soUpdateItemsErrorState();
            soUpdateAddVehicleState();
        }

        function soClearCustomerSelection() {
            const input = document.getElementById('so_customer_search');
            const hidden = document.getElementById('so_customer_id');

            if (input) {
                input.value = '';
                input.dataset.selectedId = '';
                input.dataset.selectedLabel = '';
            }

            if (hidden) {
                hidden.value = '';
            }

            notifySaleOrderCustomerChanged();
            soUpdateItemsErrorState();
            soUpdateAddVehicleState();
        }

        function soInitCustomerAutocomplete() {
            const input = document.getElementById('so_customer_search');
            const hidden = document.getElementById('so_customer_id');
            const results = document.getElementById('so_customer_results');
            const clearBtn = document.getElementById('so_customer_clear');
            if (!input || !hidden || !results) return;

            let activeFetch = null;

            function fetchResults(query) {
                const url = input.getAttribute('data-search-url') || '';
                if (!url) return;
                if (activeFetch) {
                    activeFetch.abort();
                }
                activeFetch = new AbortController();

                fetch(url + '?q=' + encodeURIComponent(query), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: activeFetch.signal
                })
                    .then((resp) => resp.ok ? resp.json() : Promise.reject(resp))
                    .then((data) => {
                        soCustomerRenderResults(data.results || []);
                    })
                    .catch(() => {
                        soCustomerHideResults();
                    });
            }

            input.addEventListener('input', function () {
                const value = (input.value || '').toString().trim();

                if (value === '') {
                    if (hidden.value) {
                        hidden.value = '';
                        notifySaleOrderCustomerChanged();
                    }
                    input.dataset.selectedId = '';
                    input.dataset.selectedLabel = '';
                    soUpdateAddVehicleState();
                    soCustomerHideResults();
                    return;
                }

                if (input.dataset.selectedLabel && value !== input.dataset.selectedLabel) {
                    hidden.value = '';
                    input.dataset.selectedId = '';
                    soUpdateAddVehicleState();
                }

                if (value.length < 2) {
                    soCustomerHideResults();
                    return;
                }

                fetchResults(value);
            });

            input.addEventListener('focus', function () {
                const value = (input.value || '').toString().trim();
                if (value.length >= 2 && (!results || results.classList.contains('d-none'))) {
                    fetchResults(value);
                }
            });

            results.addEventListener('click', function (event) {
                const button = event.target.closest('[data-id]');
                if (!button) return;
                soCustomerSelect({
                    id: button.getAttribute('data-id'),
                    text: button.getAttribute('data-text')
                });
            });

            document.addEventListener('click', function (event) {
                if (event.target === input || results.contains(event.target)) return;
                soCustomerHideResults();
            });

            clearBtn?.addEventListener('click', function () {
                soClearCustomerSelection();
            });

            if (!input.disabled && !soGetSelectedCustomerIdForVehicle()) {
                if (hidden.value) {
                    hidden.value = '';
                    notifySaleOrderCustomerChanged();
                }
                input.dataset.selectedId = '';
                if ((input.value || '').toString().trim() === '') {
                    input.dataset.selectedLabel = '';
                }
            }

            soUpdateAddVehicleState();
        }

        function soUpdateAddVehicleState() {
            const button = document.getElementById('so_add_vehicle_btn');
            if (!button) return;
            const customerId = soGetSelectedCustomerIdForVehicle();
            button.disabled = customerId === '';
            if (!button.disabled) {
                button.removeAttribute('disabled');
            }
        }

        function soVehicleUrlFromTemplate(template, customerId) {
            return (template || '').replace('CUSTOMER_ID', customerId);
        }

        function soFlashVehicleMessage(targetId, message) {
            const el = document.getElementById(targetId);
            if (!el) return;
            el.textContent = message || '';
            el.classList.remove('d-none');
            window.setTimeout(() => {
                el.classList.add('d-none');
            }, 3500);
        }

        function soAutoSelectNewVehicle(newVehicleId) {
            if (!newVehicleId) return;

            const targetIndex = window.__soVehicleTargetIndex || '';
            if (targetIndex !== '') {
                const select = document.querySelector(`select[name="items[${targetIndex}][customer_vehicle_id]"]`);
                if (select && !select.disabled) {
                    select.value = String(newVehicleId);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
            }

            const saleOrderSelects = document.querySelectorAll('select[name^="items"][name$="[customer_vehicle_id]"]');
            saleOrderSelects.forEach((select) => {
                if (select.disabled) return;
                if ((select.value || '') !== '') return;
                select.value = String(newVehicleId);
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        function soBindVehicleModal() {
            const form = document.getElementById('soForm');
            const modal = document.getElementById('soAddVehicleModal');
            if (!form || !modal) return;

            const modalForm = modal.querySelector('form');
            if (!modalForm) return;

            if (!modal.dataset.cleanupBound) {
                modal.addEventListener('hidden.bs.modal', soCleanupModalBackdrops);
                modal.dataset.cleanupBound = '1';
            }

            modalForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const customerId = soGetSelectedCustomerIdForVehicle();
                if (!customerId) {
                    soFlashVehicleMessage('so_vehicle_error', 'Please select customer first.');
                    return;
                }

                const template = form.getAttribute('data-store-url-template') || '';
                const url = soVehicleUrlFromTemplate(template, customerId);
                if (!url) return;

                const formData = new FormData(modalForm);

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: formData
                })
                    .then((resp) => resp.json().then((data) => ({ ok: resp.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok) {
                            const msg = data && data.message ? data.message : 'Failed to create vehicle.';
                            soFlashVehicleMessage('so_vehicle_error', msg);
                            return;
                        }

                        soHideModal(modal);
                        window.setTimeout(soCleanupModalBackdrops, 150);

                        modalForm.reset();
                        soFlashVehicleMessage('so_vehicle_success', data.message || 'Vehicle created.');

                        if (window.Livewire && typeof window.Livewire.emit === 'function') {
                            window.Livewire.emit('saleOrderCustomerChanged', customerId);
                        }

                        if (data.vehicle && data.vehicle.id) {
                            soAutoSelectNewVehicle(data.vehicle.id);
                        }
                    })
                    .catch(() => {
                        soFlashVehicleMessage('so_vehicle_error', 'Failed to create vehicle.');
                    });
            });
        }

        function soHideModal(modal) {
            if (!modal) return;
            if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                return;
            }
            if (window.jQuery) {
                window.jQuery(modal).modal('hide');
            }
        }

        function soCleanupModalBackdrops() {
            if (document.querySelectorAll('.modal.show').length > 0) return;
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.parentNode?.removeChild(backdrop);
            });
        }

        // ✅ Livewire re-render safe hook
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('message.processed', () => {
                soBindDepositInputs();
                soBindDpReceivedInputs();
                soRecalc();
                soUpdateItemsErrorState();
            });
        }

        soInitCustomerAutocomplete();
        soUpdateItemsErrorState();
        soBindVehicleModal();
        soUpdateAddVehicleState();

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.so-add-vehicle-btn');
            if (!button) return;
            window.__soVehicleTargetIndex = button.getAttribute('data-row-index') || '';
        });

        form?.addEventListener('submit', function (event) {
            window.__soSubmitAttempted = true;
            if (!soPrepareBeforeSubmit(form)) {
                event.preventDefault();
            }
        });

        form?.addEventListener('confirm-submit:before', function (event) {
            window.__soSubmitAttempted = true;
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
