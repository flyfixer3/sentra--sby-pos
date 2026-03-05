@extends('layouts.app')

@section('title', 'Edit Expense Category')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
        <li class="breadcrumb-item"><a href="{{ route('expense-categories.index') }}">Categories</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-7">
            @include('utils.alerts')
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('expense-categories.update', $expenseCategory) }}" method="POST">
                        @csrf
                        @method('patch')

                        <div class="form-group">
                            <label for="category_name">Category Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="category_name" required value="{{ $expenseCategory->category_name }}">
                        </div>

                        <div class="form-group">
                            <label for="subaccount_number">Expense Account (Subaccount)</label>
                            <select class="form-control" name="subaccount_number">
                                <option value="">-- Optional (but REQUIRED for CREDIT expense posting) --</option>

                                @foreach(
                                    \App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                        ->where('accounting_accounts.is_active', '=', '1')
                                        ->whereIn('accounting_accounts.account_number', [16,17])
                                        ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                        ->orderBy('accounting_subaccounts.subaccount_number')
                                        ->get()
                                    as $acc
                                )
                                    <option value="{{ $acc->subaccount_number }}"
                                        {{ (string)$expenseCategory->subaccount_number === (string)$acc->subaccount_number ? 'selected' : '' }}>
                                        ({{ $acc->subaccount_number }}) - {{ $acc->subaccount_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="category_description">Description</label>
                            <textarea class="form-control" name="category_description" id="category_description" rows="4">{{ $expenseCategory->category_description }}</textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update <i class="bi bi-check"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection