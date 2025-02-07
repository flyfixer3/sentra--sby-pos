@extends('layouts.app')

@section('title', 'Create Sale From Quotation')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
        <li class="breadcrumb-item active">Make Sale</li>
    </ol>
@endsection

@section('content')
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
                        <form id="sale-form" action="{{ route('sales.store') }}" method="POST">
                            @csrf

                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="B">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="reference">Car Plate<span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="car_number_plate" id="car_number_plate" required >
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                            <select class="form-control" name="customer_id" id="customer_id" required>
                                                @foreach(\Modules\People\Entities\Customer::all() as $customer)
                                                    <option {{ $sale->customer_id == $customer->id ? 'selected' : '' }} value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                                                @endforeach
                                            </select>
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
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <livewire:product-cart-sale :cartInstance="'sale'" :data=$sale  :warehouses="\Modules\Product\Entities\Warehouse::all()"/>

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
                                        <select class="form-control" name="deposit_code" id="deposit_code" required>
                                            <option selected>-</option>
                                            @foreach(\App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                            ->where('accounting_accounts.is_active', '=', '1')->where('accounting_accounts.account_number', 3)
                                            ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')->get(); as $account)
                                                <option value="{{ $account->id }}">({{$account->subaccount_number }}) - {{ $account->subaccount_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="from-group">
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
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="paid_amount">Amount Received <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input id="paid_amount" type="text" class="form-control" name="paid_amount" placeholder=0 required>
                                            <div class="input-group-append">
                                                <button id="getTotalAmount" class="btn btn-primary" type="button">
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

                            <input type="hidden" name="quotation_id" value="{{ $quotation_id }}">

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Create Sale <i class="bi bi-check"></i>
                                </button>
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
        $(document).ready(function () {
            $('#paid_amount').maskMoney({
                prefix:'{{ settings()->currency->symbol }}',
                thousands:'{{ settings()->currency->thousand_separator }}',
                decimal:'{{ settings()->currency->decimal_separator }}',
                allowZero: true,
                precision: 0
            });

            $('#getTotalAmount').click(function () {
                $('#paid_amount').maskMoney('mask', {{ Cart::instance('sale')->total() }});
            });

            $('#sale-form').submit(function () {
                var paid_amount = $('#paid_amount').maskMoney('destroy')[0];
                var new_number = parseInt(paid_amount.value.toString().replaceAll(/[Rp.]/g, ""));
                $('#paid_amount').val(new_number);
            });
        });
    </script>
@endpush
