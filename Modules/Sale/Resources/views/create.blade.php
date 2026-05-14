@extends('layouts.app')

@section('title', 'Create Sale')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@push('page_css')
<style>
    .sale-customer-autocomplete {
        position: relative;
    }
    .sale-customer-results {
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
    .sale-customer-results .list-group-item {
        cursor: pointer;
    }
    .sale-customer-results .list-group-item.active {
        background: #eef2ff;
        border-color: #d6dcff;
        color: #1d1d1d;
    }
    .sale-customer-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: flex-start;
    }
    .sale-customer-row .input-group {
        flex: 1 1 260px;
    }
    .sale-add-vehicle-trigger {
        height: calc(1.5em + .75rem + 2px);
        white-space: nowrap;
    }
</style>
@endpush

@section('content')
@php
    $saleDeliveryId = (int) request()->get('sale_delivery_id', (int) old('sale_delivery_id', 0));
    $fromDelivery = $saleDeliveryId > 0;

    $prefillCustomerId = (int) ($prefillCustomerId ?? old('customer_id', 0));

    $lockedFinancial = $lockedFinancial ?? null;
    $isLockedBySO = $fromDelivery && !empty($lockedFinancial) && isset($lockedFinancial['sale_order_id']);

    $dpTotal = (int) ($lockedFinancial['deposit_received_amount'] ?? 0);
    $dpAllocated = (int) ($lockedFinancial['dp_allocated_for_this_invoice'] ?? 0);
    $suggestedPayNow = (int) ($lockedFinancial['suggested_pay_now'] ?? 0);

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
@endphp

<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <livewire:search-product/>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @include('utils.alerts')

                    @if($fromDelivery)
                        <div class="alert alert-info">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <div class="font-weight-bold mb-1">
                                        Create Invoice from Sale Delivery
                                    </div>
                                    <div class="small">
                                        Items & quantities are prefilled from the delivery.
                                        Please complete invoice fields (payment, note, etc.) then submit.
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Untuk invoice dari Sale Delivery, quantity mengikuti <strong>Delivered / Remaining to Invoice</strong>.
                                        Current stock hanya ditampilkan sebagai referensi tambahan.
                                    </div>

                                    @if($isLockedBySO)
                                        <div class="small mt-2">
                                            <strong>Financial locked by Sale Order</strong>
                                            (SO: <strong>{{ $lockedFinancial['sale_order_reference'] }}</strong>)<br>
                                            Tax: <strong>{{ number_format((float)$lockedFinancial['tax_percentage'], 2, '.', '') }}%</strong> •
                                            Discount: <strong>{{ number_format((float)$lockedFinancial['discount_percentage'], 2, '.', '') }}%</strong> •
                                            Fee: <strong>{{ format_currency((int)$lockedFinancial['fee_amount']) }}</strong> •
                                            Shipping: <strong>{{ format_currency((int)$lockedFinancial['shipping_amount']) }}</strong>
                                            <div class="text-muted" style="font-size: 12px;">
                                                Catatan: server akan override financial invoice agar selalu sama dengan Sale Order (biar DP pro-rata konsisten).
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="small text-muted">
                                    Ref: SDO#{{ $saleDeliveryId }}
                                </div>
                            </div>
                        </div>

                        @if($isLockedBySO)
                            <div class="alert alert-warning">
                                <div class="small">
                                    <div class="font-weight-bold mb-1">Deposit (DP) from Sale Order</div>

                                    DP Received (SO): <strong>{{ format_currency($dpTotal) }}</strong><br>
                                    Allocated to this invoice (pro-rata): <strong>{{ format_currency($dpAllocated) }}</strong><br>

                                    @if($dpTotal > 0)
                                        Suggested “Amount Received” now (Remaining after DP): <strong>{{ format_currency($suggestedPayNow) }}</strong>
                                    @else
                                        <span class="text-muted">No DP received yet. DP allocation is <strong>{{ format_currency(0) }}</strong>.</span><br>
                                        Suggested “Amount Received” now: <strong>{{ format_currency($suggestedPayNow) }}</strong>
                                    @endif

                                    <div class="text-muted" style="font-size: 12px;">
                                        Tips: klik tombol ✓ di Amount Received untuk auto isi nilai suggested.
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                      <form id="sale-form" action="{{ route('sales.store') }}" method="POST"
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

                        @if($fromDelivery)
                            <input type="hidden" name="sale_delivery_id" value="{{ $saleDeliveryId }}">
                        @endif

                        @if($isLockedBySO)
                            <input type="hidden" name="tax_percentage" value="{{ number_format((float)$lockedFinancial['tax_percentage'], 2, '.', '') }}">
                            <input type="hidden" name="discount_percentage" value="{{ number_format((float)$lockedFinancial['discount_percentage'], 2, '.', '') }}">
                            <input type="hidden" name="discount_type" value="percentage">
                            <input type="hidden" name="header_discount_value" value="{{ number_format((float)$lockedFinancial['discount_percentage'], 2, '.', '') }}">
                            <input type="hidden" name="fee_amount" value="{{ (int)$lockedFinancial['fee_amount'] }}">
                            <input type="hidden" name="shipping_amount" value="{{ (int)$lockedFinancial['shipping_amount'] }}">

                            {{-- DP hints for JS --}}
                            <input type="hidden" id="so_dp_total" value="{{ (int)($lockedFinancial['deposit_received_amount'] ?? 0) }}">
                            <input type="hidden" id="so_dp_allocated" value="{{ (int)($lockedFinancial['dp_allocated_for_this_invoice'] ?? 0) }}">
                            <input type="hidden" id="so_suggested_pay_now" value="{{ (int)($lockedFinancial['suggested_pay_now'] ?? 0) }}">

                            {{-- locked numbers for JS set UI fields --}}
                            <input type="hidden" id="so_locked_tax_pct" value="{{ (int)($lockedFinancial['tax_percentage'] ?? 0) }}">
                            <input type="hidden" id="so_locked_disc_pct" value="{{ (int)($lockedFinancial['discount_percentage'] ?? 0) }}">
                            <input type="hidden" id="so_locked_fee" value="{{ (int)($lockedFinancial['fee_amount'] ?? 0) }}">
                            <input type="hidden" id="so_locked_ship" value="{{ (int)($lockedFinancial['shipping_amount'] ?? 0) }}">
                        @endif

                        <div class="form-row">
                            <div class="col-lg-2">
                                <div class="form-group">
                                    <label for="reference">Reference <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reference" readonly value="AUTO">
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="from-group">
                                    <div class="form-group">
                                        <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                        <div class="sale-customer-autocomplete">
                                            <div class="sale-customer-row">
                                                <div class="input-group">
                                                    <input type="text"
                                                           id="sale_customer_search"
                                                           name="customer_search"
                                                           class="form-control @error('customer_id') is-invalid @enderror"
                                                           placeholder="Search customer by name, phone, or email..."
                                                           autocomplete="off"
                                                           value="{{ $selectedCustomerLabel }}"
                                                           data-selected-id="{{ $selectedCustomerId }}"
                                                           data-selected-label="{{ $selectedCustomerLabel }}"
                                                           data-search-url="{{ route('customers.search') }}"
                                                           @if($fromDelivery) disabled @endif>
                                                    <button class="btn btn-outline-secondary" type="button" id="sale_customer_clear" aria-label="Clear customer" @if($fromDelivery) disabled @endif>&times;</button>
                                                </div>
                                                @can('edit_customers')
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-primary sale-add-vehicle-trigger"
                                                        id="sale_add_vehicle_btn"
                                                        data-toggle="modal"
                                                        data-target="#saleAddVehicleModal"
                                                        @if($fromDelivery || $selectedCustomerId <= 0 || trim($selectedCustomerLabel) === '') disabled @endif
                                                        @if($fromDelivery) data-locked-disabled="1" @endif
                                                    >
                                                        + Add Vehicle
                                                    </button>
                                                @endcan
                                            </div>
                                            <input type="hidden" name="customer_id" id="customer_id" value="{{ $selectedCustomerId }}">
                                            <div id="sale_customer_results" class="sale-customer-results list-group d-none"></div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-2">
                                <div class="form-group">
                                    <label for="sale_form">Sales From <span class="text-danger">*</span></label>
                                    <select class="form-control" name="sale_from" id="sale_from" required>
                                        <option value="Google Ads">Google Ads</option>
                                        <option value="Marketplace">Marketplace</option>
                                        <option value="Tokopedia">Tokopedia</option>
                                        <option value="Instragram">Instagram</option>
                                        <option value="Tiktok">Tiktok</option>
                                        <option value="Offline">Offline</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-2">
                                <div class="from-group">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="sale_vehicle_success" class="alert alert-success d-none mt-2" role="alert"></div>
                        <div id="sale_vehicle_error" class="alert alert-danger d-none mt-2" role="alert"></div>

                        <livewire:product-cart-sale
                            :cartInstance="'sale'"
                            :data="$lockedFinancial"
                            :customerId="(int) old('customer_id', $prefillCustomerId)"
                            :enableInstallationMetadata="true"
                            :warehouses="$warehouses"
                        />

                        <div class="form-row">
                            @php
                                $soDepositCode = trim((string) ($lockedFinancial['deposit_code'] ?? ''));
                                $lockDepositTo = $isLockedBySO && $soDepositCode !== '' && $soDepositCode !== '-';

                                // default selected: kalau locked pakai SO, kalau tidak pakai old()
                                $selectedDepositCode = $lockDepositTo
                                    ? $soDepositCode
                                    : trim((string) old('deposit_code', ''));
                            @endphp

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label for="deposit_code">Deposit To <span class="text-danger">*</span></label>

                                    <select
                                        class="form-control"
                                        name="deposit_code"
                                        id="deposit_code"
                                        @if($lockDepositTo) disabled @endif
                                    >
                                        <option value="" @if($selectedDepositCode === '') selected @endif disabled>-- Choose Deposit --</option>

                                        @foreach(
                                            \App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                            ->where('accounting_accounts.is_active', '=', '1')
                                            ->where('accounting_accounts.account_number', 3)
                                            ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                            ->get()
                                            as $account
                                        )
                                            @php
                                                $val = (string) $account->subaccount_number;
                                                $isSelected = $selectedDepositCode !== '' && $selectedDepositCode === $val;
                                            @endphp

                                            <option value="{{ $val }}" @if($isSelected) selected @endif>
                                                ({{ $val }}) - {{ $account->subaccount_name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @if($lockDepositTo)
                                        {{-- disabled select won't be submitted --}}
                                        <input type="hidden" name="deposit_code" value="{{ $soDepositCode }}">

                                        <small class="text-muted">
                                            Locked from Sale Order ({{ $lockedFinancial['sale_order_reference'] ?? 'SO' }})
                                        </small>
                                    @endif
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-control" name="payment_method" id="payment_method" required>
                                        <option value="Cash">Cash</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label for="paid_amount">
                                        Amount Received <span class="text-danger">*</span>
                                        @if($isLockedBySO)
                                            <span class="text-muted" style="font-size: 12px;">
                                                (suggested: {{ format_currency($suggestedPayNow) }})
                                            </span>
                                        @endif
                                    </label>
                                    <div class="input-group">
                                        <input id="paid_amount" type="text" class="form-control" name="paid_amount" required>
                                        <div class="input-group-append">
                                            <button id="getTotalAmount" class="btn btn-primary" type="button" title="Auto fill amount">
                                                <i class="bi bi-check-square"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="note">Note (If Needed)</label>
                            <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                Create Sale <i class="bi bi-check"></i>
                            </button>

                            @if($fromDelivery)
                                <a href="{{ route('sale-deliveries.show', $saleDeliveryId) }}" class="btn btn-outline-secondary ml-2">
                                    Back to Delivery
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@can('edit_customers')
    <div class="modal fade" id="saleAddVehicleModal" tabindex="-1" role="dialog" aria-labelledby="saleAddVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="#" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="saleAddVehicleModalLabel">Add Vehicle</h5>
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

@push('page_scripts')
<script src="{{ asset('js/jquery-mask-money.js') }}"></script>
<script>
    function parseMoneyToInt(val) {
        if (!val) return 0;
        var digits = val.toString().replace(/[^\d-]/g, '');
        return digits ? (parseInt(digits, 10) || 0) : 0;
    }

    function setLockedFinancialInputsIfAny() {
        var tax = $('#so_locked_tax_pct').val();
        var disc = $('#so_locked_disc_pct').val();
        var fee = $('#so_locked_fee').val();
        var ship = $('#so_locked_ship').val();

        // these inputs are rendered by Livewire product-cart-sale
        // names commonly: tax_percentage, discount_percentage, fee_amount, shipping_amount
        var $tax = $('input[name="tax_percentage"]');
        var $disc = $('input[name="discount_percentage"]');
        var $fee = $('input[name="fee_amount"]');
        var $ship = $('input[name="shipping_amount"]');

        if ($tax.length && typeof tax !== 'undefined') {
            $tax.prop('readonly', true);
        }
        if ($disc.length && typeof disc !== 'undefined') {
            $disc.prop('readonly', true);
        }
        if ($fee.length && typeof fee !== 'undefined') {
            $fee.prop('readonly', true);
        }
        if ($ship.length && typeof ship !== 'undefined') {
            $ship.prop('readonly', true);
        }
    }

    function saleGetSelectedCustomerIdForVehicle() {
        var input = document.getElementById('sale_customer_search');
        var hidden = document.getElementById('customer_id');

        var hiddenId = (hidden?.value || '').toString().trim();
        var selectedId = (input?.dataset.selectedId || '').toString().trim();
        var selectedLabel = (input?.dataset.selectedLabel || '').toString().trim();
        var visibleLabel = (input?.value || '').toString().trim();

        if (!hiddenId || !selectedId) return '';
        if (hiddenId !== selectedId) return '';
        if (!selectedLabel || !visibleLabel) return '';
        if (selectedLabel !== visibleLabel) return '';

        return hiddenId;
    }

    function notifySaleCustomerChanged() {
        var customerId = saleGetSelectedCustomerIdForVehicle();
        if (window.Livewire && typeof window.Livewire.emit === 'function') {
            window.Livewire.emit('saleCustomerChanged', customerId);
        }
    }

    function saleUpdateAddVehicleState() {
        var button = document.getElementById('sale_add_vehicle_btn');
        if (!button) return;
        var customerId = saleGetSelectedCustomerIdForVehicle();
        var enabled = customerId !== '';
        var locked = button.dataset.lockedDisabled === '1';
        button.disabled = locked || !enabled;
        if (!button.disabled) {
            button.removeAttribute('disabled');
        }
    }

    function saleClearVehicleSelections() {
        document.querySelectorAll('select[data-cart-sync-field="customer_vehicle_id"]').forEach(function (select) {
            if ((select.value || '') !== '') {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function saleCustomerHideResults() {
        var results = document.getElementById('sale_customer_results');
        if (!results) return;
        results.classList.add('d-none');
        results.innerHTML = '';
    }

    function saleCustomerRenderResults(items, state) {
        var results = document.getElementById('sale_customer_results');
        if (!results) return;

        if (state === 'loading') {
            results.innerHTML = '<div class="list-group-item d-flex align-items-center gap-2">'
                + '<span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>'
                + '<span>Searching...</span>'
                + '</div>';
            results.classList.remove('d-none');
            return;
        }

        if (!items || items.length === 0) {
            results.innerHTML = '<div class="list-group-item text-muted">No customers found.</div>';
            results.classList.remove('d-none');
            return;
        }

        results.innerHTML = items.map(function (item, index) {
            var text = (item.text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return '<button type="button" class="list-group-item list-group-item-action" data-index="' + index + '" data-id="' + item.id + '" data-text="' + text + '">' + text + '</button>';
        }).join('');
        results.classList.remove('d-none');
    }

    function saleCustomerSelect(item) {
        var input = document.getElementById('sale_customer_search');
        var hidden = document.getElementById('customer_id');
        if (input) {
            input.value = item.text || '';
            input.dataset.selectedId = String(item.id || '');
            input.dataset.selectedLabel = item.text || '';
            input.classList.remove('is-invalid');
        }

        if (hidden) {
            hidden.value = item.id || '';
        }

        saleCustomerHideResults();
        notifySaleCustomerChanged();
        saleUpdateAddVehicleState();
    }

    function saleClearCustomerSelection() {
        var input = document.getElementById('sale_customer_search');
        var hidden = document.getElementById('customer_id');

        if (input) {
            input.value = '';
            input.dataset.selectedId = '';
            input.dataset.selectedLabel = '';
        }

        if (hidden) {
            hidden.value = '';
        }

        saleCustomerHideResults();
        saleClearVehicleSelections();
        notifySaleCustomerChanged();
        saleUpdateAddVehicleState();
    }

    function saleInitCustomerAutocomplete() {
        var input = document.getElementById('sale_customer_search');
        var hidden = document.getElementById('customer_id');
        var results = document.getElementById('sale_customer_results');
        var clearBtn = document.getElementById('sale_customer_clear');
        if (!input || !hidden || !results) return;

        var activeFetch = null;
        var debounceTimer = null;
        var cache = new Map();
        var activeIndex = -1;
        var lastItems = [];
        var minLength = 2;

        function setActive(index) {
            var buttons = results.querySelectorAll('[data-index]');
            buttons.forEach(function (btn) { btn.classList.remove('active'); });
            if (index >= 0 && index < buttons.length) {
                buttons[index].classList.add('active');
                activeIndex = index;
            } else {
                activeIndex = -1;
            }
        }

        function fetchResults(query) {
            var url = input.getAttribute('data-search-url') || '';
            if (!url) return;

            if (cache.has(query)) {
                lastItems = cache.get(query) || [];
                saleCustomerRenderResults(lastItems);
                setActive(-1);
                return;
            }

            if (activeFetch) {
                activeFetch.abort();
            }
            activeFetch = new AbortController();

            fetch(url + '?q=' + encodeURIComponent(query), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeFetch.signal
            })
                .then(function (resp) { return resp.ok ? resp.json() : Promise.reject(resp); })
                .then(function (data) {
                    var currentValue = (input.value || '').toString().trim();
                    if (currentValue !== query) return;
                    lastItems = (data && data.results) ? data.results : [];
                    cache.set(query, lastItems);
                    saleCustomerRenderResults(lastItems);
                    setActive(-1);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    saleCustomerHideResults();
                });
        }

        input.addEventListener('input', function () {
            var value = (input.value || '').toString().trim();

            if (value === '') {
                if (hidden.value) {
                    hidden.value = '';
                    notifySaleCustomerChanged();
                    saleClearVehicleSelections();
                }
                input.dataset.selectedId = '';
                input.dataset.selectedLabel = '';
                saleUpdateAddVehicleState();
                saleCustomerHideResults();
                return;
            }

            if (input.dataset.selectedLabel && value !== input.dataset.selectedLabel) {
                if (hidden.value) {
                    hidden.value = '';
                    notifySaleCustomerChanged();
                    saleClearVehicleSelections();
                }
                input.dataset.selectedId = '';
                saleUpdateAddVehicleState();
            }

            if (value.length < minLength) {
                saleCustomerHideResults();
                return;
            }

            saleCustomerRenderResults([], 'loading');
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function () {
                fetchResults(value);
            }, 160);
        });

        input.addEventListener('focus', function () {
            var value = (input.value || '').toString().trim();
            if (value.length < minLength) return;
            saleCustomerRenderResults([], 'loading');
            fetchResults(value);
        });

        input.addEventListener('keydown', function (event) {
            if (results.classList.contains('d-none')) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(Math.min(activeIndex + 1, lastItems.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter') {
                if (activeIndex >= 0 && lastItems[activeIndex]) {
                    event.preventDefault();
                    saleCustomerSelect(lastItems[activeIndex]);
                }
            } else if (event.key === 'Escape') {
                saleCustomerHideResults();
            }
        });

        results.addEventListener('mousemove', function (event) {
            var button = event.target.closest('[data-index]');
            if (!button) return;
            setActive(parseInt(button.getAttribute('data-index'), 10));
        });

        results.addEventListener('click', function (event) {
            var button = event.target.closest('[data-id]');
            if (!button) return;
            saleCustomerSelect({
                id: button.getAttribute('data-id'),
                text: button.getAttribute('data-text')
            });
        });

        document.addEventListener('click', function (event) {
            if (event.target === input || results.contains(event.target)) return;
            saleCustomerHideResults();
        });

        clearBtn?.addEventListener('click', function () {
            saleClearCustomerSelection();
        });

        if (!input.disabled && !saleGetSelectedCustomerIdForVehicle()) {
            if (hidden.value) {
                hidden.value = '';
                notifySaleCustomerChanged();
                saleClearVehicleSelections();
            }
            input.dataset.selectedId = '';
            if ((input.value || '').toString().trim() === '') {
                input.dataset.selectedLabel = '';
            }
        }

        saleUpdateAddVehicleState();
    }

    function saleVehicleUrlFromTemplate(template, customerId) {
        return (template || '').replace('CUSTOMER_ID', customerId);
    }

    function saleFlashVehicleMessage(targetId, message) {
        var el = document.getElementById(targetId);
        if (!el) return;
        el.textContent = message || '';
        el.classList.remove('d-none');
        window.setTimeout(function () {
            el.classList.add('d-none');
        }, 3500);
    }

    function saleAutoSelectNewVehicle(newVehicleId) {
        if (!newVehicleId) return;
        var targetLineKey = window.__saleVehicleTargetLineKey || '';

        if (targetLineKey !== '') {
            var targetSelect = document.querySelector('select[data-cart-sync-field="customer_vehicle_id"][data-cart-line-key="' + targetLineKey + '"]');
            if (targetSelect && !targetSelect.disabled) {
                targetSelect.value = String(newVehicleId);
                targetSelect.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }

        var selects = document.querySelectorAll('select[data-cart-sync-field="customer_vehicle_id"]');
        Array.prototype.forEach.call(selects, function (select) {
            if (select.disabled) return;
            if ((select.value || '') !== '') return;
            select.value = String(newVehicleId);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function saleHideModal(modal) {
        if (!modal) return;
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            return;
        }
        if (window.jQuery) {
            window.jQuery(modal).modal('hide');
        }
    }

    function saleCleanupModalBackdrops() {
        if (document.querySelectorAll('.modal.show').length > 0) return;
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.parentNode?.removeChild(backdrop);
        });
    }

    function saleBindVehicleModal() {
        var modal = document.getElementById('saleAddVehicleModal');
        var saleForm = document.getElementById('sale-form');
        if (!saleForm || !modal) return;

        var modalForm = modal.querySelector('form');
        if (!modalForm) return;

        if (!modal.dataset.cleanupBound) {
            modal.addEventListener('hidden.bs.modal', saleCleanupModalBackdrops);
            modal.dataset.cleanupBound = '1';
        }

        if (modalForm.dataset.submitBound === '1') return;
        modalForm.dataset.submitBound = '1';

        modalForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var customerId = saleGetSelectedCustomerIdForVehicle();
            if (!customerId) {
                saleFlashVehicleMessage('sale_vehicle_error', 'Please select customer first.');
                return;
            }

            var template = saleForm.getAttribute('data-store-url-template') || '';
            var url = saleVehicleUrlFromTemplate(template, customerId);
            if (!url) return;

            var submitBtn = modalForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
                submitBtn.textContent = 'Saving...';
            }

            var formData = new FormData(modalForm);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: formData
            })
                .then(function (resp) { return resp.json().then(function (data) { return { ok: resp.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok) {
                        var msg = result.data && result.data.message ? result.data.message : 'Failed to create vehicle.';
                        saleFlashVehicleMessage('sale_vehicle_error', msg);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = submitBtn.dataset.originalText || 'Save Vehicle';
                        }
                        return;
                    }

                        saleHideModal(modal);
                        window.setTimeout(saleCleanupModalBackdrops, 150);
                    modalForm.reset();
                    saleFlashVehicleMessage('sale_vehicle_success', result.data.message || 'Vehicle created.');
                    if (window.Livewire && typeof window.Livewire.emit === 'function') {
                        window.Livewire.emit('saleCustomerChanged', customerId);
                    }
                    if (result.data && result.data.vehicle && result.data.vehicle.id) {
                        saleAutoSelectNewVehicle(result.data.vehicle.id);
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || 'Save Vehicle';
                    }
                })
                .catch(function () {
                    saleFlashVehicleMessage('sale_vehicle_error', 'Failed to create vehicle.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || 'Save Vehicle';
                    }
                });
        });
    }

    $(document).ready(function () {
        $('#paid_amount').maskMoney({
            prefix:'{{ settings()->currency->symbol }}',
            thousands:'{{ settings()->currency->thousand_separator }}',
            decimal:'{{ settings()->currency->decimal_separator }}',
            allowZero: true,
            precision: 0
        });

        // ✅ ensure locked values are applied into Livewire inputs
        setLockedFinancialInputsIfAny();
        document.addEventListener('livewire:load', function () {
            setLockedFinancialInputsIfAny();
            notifySaleCustomerChanged();
        });

        // fallback: if Livewire rerenders
        document.addEventListener('DOMContentLoaded', function(){ setLockedFinancialInputsIfAny(); });

        notifySaleCustomerChanged();

        saleInitCustomerAutocomplete();
        saleBindVehicleModal();

        document.addEventListener('click', function (event) {
            var button = event.target.closest('#sale_add_vehicle_btn');
            if (!button) return;
            window.__saleVehicleTargetLineKey = '';
        });

        $('#getTotalAmount').click(function () {
            // total_amount is produced by the Livewire component
            var $total = $('input[name="total_amount"]');
            if ($total.length === 0) $total = $('#total_amount');

            var totalVal = $total.length ? $total.val() : '0';
            var totalInt = parseMoneyToInt(totalVal);

            // ✅ If locked by SO and has DP suggestion, use "suggested pay now" (remaining after DP)
            var suggested = parseInt($('#so_suggested_pay_now').val() || '0', 10);
            if (!isNaN(suggested) && suggested > 0) {
                $('#paid_amount').maskMoney('mask', suggested);
                return;
            }

            // default behavior: fill total
            $('#paid_amount').maskMoney('mask', totalInt);
        });

        $('#sale-form').submit(function () {
            if (this.getAttribute('data-confirm-submit') === 'true' && this.getAttribute('data-confirmed-submit') !== 'true') {
                return;
            }

            var raw = $('#paid_amount').val();
            $('#paid_amount').val(parseMoneyToInt(raw));
        });
    });
</script>
@endpush
