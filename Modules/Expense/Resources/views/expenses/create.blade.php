@extends('layouts.app')

@section('title', 'Create Expense / Petty Cash Transaction')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <form id="expense-form" action="{{ route('expenses.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-lg-12">
                @include('utils.alerts')
                <div class="form-group">
                    <button class="btn btn-primary">Save Transaction <i class="bi bi-check"></i></button>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">

                        <div class="form-row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="reference">Reference</label>
                                    <input type="text" class="form-control" name="reference" readonly value="AUTO (EXP-xxxxx)">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="date">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="type">Type <span class="text-danger">*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="credit">CREDIT (OUT) - Expense</option>
                                        <option value="debit">DEBIT (IN) - Cash In / Top Up</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="category_id">Category <span class="text-danger">*</span></label>
                                    <select name="category_id" id="category_id" class="form-control" required>
                                        <option value="" selected>Select Category</option>
                                        @foreach(\Modules\Expense\Entities\ExpenseCategory::orderBy('category_name')->get() as $category)
                                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        For CREDIT (OUT), category must have Expense Account mapping.
                                    </small>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="amount">Amount <span class="text-danger">*</span></label>
                                    <input id="amount" type="text" class="form-control" name="amount" required value="{{ old('amount') }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="payment_method">Cash/Bank Account <span class="text-danger">*</span></label>
                                    <select class="form-control" name="payment_method" id="payment_method" required>
                                        @foreach(
                                            \App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                                ->where('accounting_accounts.is_active', '=', '1')
                                                ->where('accounting_accounts.account_number', 3)
                                                ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                                ->get()
                                            as $account
                                        )
                                            <option value="{{ $account->subaccount_number }}">
                                                ({{ $account->subaccount_number }}) - {{ $account->subaccount_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        CREDIT: account used to pay. DEBIT: account that receives money.
                                    </small>
                                </div>
                            </div>

                            <div class="col-lg-6" id="wrap_from_account" style="display:none;">
                                <div class="form-group">
                                    <label for="from_account">Source Account (DEBIT only) <span class="text-danger">*</span></label>
                                    <select class="form-control" name="from_account" id="from_account">
                                        <option value="">Select Source Account</option>
                                        @foreach(
                                            \App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                                ->where('accounting_accounts.is_active', '=', '1')
                                                ->where('accounting_accounts.account_number', 3)
                                                ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                                ->get()
                                            as $account
                                        )
                                            <option value="{{ $account->subaccount_number }}">
                                                ({{ $account->subaccount_number }}) - {{ $account->subaccount_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Example: transfer from BCA to Cash.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="details">Details</label>
                            <textarea class="form-control" rows="5" name="details">{{ old('details') }}</textarea>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script src="{{ asset('js/jquery-mask-money.js') }}"></script>
<script>
    $(document).ready(function () {
        function toggleFromAccount() {
            const type = ($('#type').val() || '').toLowerCase();
            if (type === 'debit') {
                $('#wrap_from_account').show();
                $('#from_account').prop('required', true);
            } else {
                $('#wrap_from_account').hide();
                $('#from_account').prop('required', false);
                $('#from_account').val('');
            }
        }

        $('#type').on('change', toggleFromAccount);
        toggleFromAccount();

        $('#amount').maskMoney({
            prefix:'{{ settings()->currency->symbol }}',
            thousands:'{{ settings()->currency->thousand_separator }}',
            decimal:'{{ settings()->currency->decimal_separator }}',
            precision: 0
        });

        $('#expense-form').submit(function () {
            var amount = $('#amount').maskMoney('destroy')[0];
            var new_number = parseInt(amount.value.toString().replaceAll(/[Rp.]/g, ""));
            $('#amount').val(new_number);
        });
    });
</script>
@endpush