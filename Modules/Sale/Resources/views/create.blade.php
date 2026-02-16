@extends('layouts.app')

@section('title', 'Create Sale')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

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

                        @if($isLockedBySO && $dpTotal > 0)
                            <div class="alert alert-warning">
                                <div class="small">
                                    <div class="font-weight-bold mb-1">Deposit (DP) from Sale Order</div>
                                    DP Received (SO): <strong>{{ format_currency($dpTotal) }}</strong><br>
                                    Allocated to this invoice (pro-rata): <strong>{{ format_currency($dpAllocated) }}</strong><br>
                                    Suggested “Amount Received” now (Remaining after DP): <strong>{{ format_currency($suggestedPayNow) }}</strong>
                                    <div class="text-muted" style="font-size: 12px;">
                                        Tips: klik tombol ✓ di Amount Received untuk auto isi “remaining after DP”.
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    <form id="sale-form" action="{{ route('sales.store') }}" method="POST">
                        @csrf

                        @if($fromDelivery)
                            <input type="hidden" name="sale_delivery_id" value="{{ $saleDeliveryId }}">
                        @endif

                        @if($isLockedBySO)
                            <input type="hidden" name="tax_percentage" value="{{ number_format((float)$lockedFinancial['tax_percentage'], 2, '.', '') }}">
                            <input type="hidden" name="discount_percentage" value="{{ number_format((float)$lockedFinancial['discount_percentage'], 2, '.', '') }}">
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

                            <div class="col-lg-2">
                                <div class="form-group">
                                    <label for="reference">Car Plate<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="car_number_plate" id="car_number_plate" required>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="from-group">
                                    <div class="form-group">
                                        <label for="customer_id">Customer <span class="text-danger">*</span></label>

                                        <select class="form-control" name="customer_id" id="customer_id" required @if($fromDelivery) disabled @endif>
                                            @foreach($customers as $customer)
                                                @php
                                                    $selected = (int) old('customer_id', $prefillCustomerId ?: (int)($customers->first()->id ?? 0)) === (int)$customer->id;
                                                @endphp
                                                <option value="{{ $customer->id }}" @if($selected) selected @endif>
                                                    {{ $customer->customer_name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @if($fromDelivery)
                                            <input type="hidden" name="customer_id" value="{{ (int) old('customer_id', $prefillCustomerId ?: (int)($customers->first()->id ?? 0)) }}">
                                        @endif
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

                        <livewire:product-cart-sale
                            :cartInstance="'sale'"
                            :data="$lockedFinancial"
                            :warehouses="$warehouses"
                        />

                        <div class="form-row">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label for="status">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="status" id="status" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Shipped">Shipped</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label for="deposit_code">Deposit To <span class="text-danger">*</span></label>
                                    <select class="form-control" name="deposit_code" id="deposit_code">
                                        <option value="" selected disabled>-- Choose Deposit --</option>
                                        @foreach(\App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                        ->where('accounting_accounts.is_active', '=', '1')->where('accounting_accounts.account_number', 3)
                                        ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')->get(); as $account)
                                            <option value="{{ $account->subaccount_number }}">({{$account->subaccount_number }}) - {{ $account->subaccount_name }}</option>
                                        @endforeach
                                    </select>
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
                                        @if($isLockedBySO && $dpTotal > 0)
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

@push('page_scripts')
<script src="{{ asset('js/jquery-mask-money.js') }}"></script>
<script>
    function parseMoneyToInt(val) {
        if (!val) return 0;
        var digits = val.toString().replace(/[^\d]/g, '');
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
        });

        // fallback: if Livewire rerenders
        document.addEventListener('DOMContentLoaded', function(){ setLockedFinancialInputsIfAny(); });

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
            var raw = $('#paid_amount').val();
            $('#paid_amount').val(parseMoneyToInt(raw));
        });
    });
</script>
@endpush
