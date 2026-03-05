@extends('layouts.app')

@section('title', 'Expenses / Petty Cash')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Expenses (Petty Cash)</li>
    </ol>
@endsection

@section('content')
@php
    $totalDebit = \Modules\Expense\Entities\Expense::query()->where('type','debit')->sum('amount');
    $totalCredit = \Modules\Expense\Entities\Expense::query()->where('type','credit')->sum('amount');
    $balance = (int)$totalDebit - (int)$totalCredit;
@endphp

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted">Saldo Masuk (DEBIT)</div>
                            <div class="h4 mb-0">{{ format_currency($totalDebit) }}</div>
                        </div>
                        <div class="h3 mb-0 text-success"><i class="bi bi-arrow-down-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted">Saldo Keluar (CREDIT)</div>
                            <div class="h4 mb-0">{{ format_currency($totalCredit) }}</div>
                        </div>
                        <div class="h3 mb-0 text-danger"><i class="bi bi-arrow-up-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted">Balance (Debit - Credit)</div>
                            <div class="h4 mb-0">{{ format_currency($balance) }}</div>
                        </div>
                        <div class="h3 mb-0 text-primary"><i class="bi bi-wallet2"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('utils.alerts')

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                    Add Transaction <i class="bi bi-plus"></i>
                </a>

                <!-- <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary">
                    Manage Categories <i class="bi bi-tags"></i>
                </a> -->
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="mb-1">Type</label>
                    <select id="filter_type" class="form-control">
                        <option value="">All</option>
                        <option value="debit">DEBIT (IN)</option>
                        <option value="credit">CREDIT (OUT)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Category</label>
                    <select id="filter_category" class="form-control">
                        <option value="">All</option>
                        @foreach(\Modules\Expense\Entities\ExpenseCategory::orderBy('category_name')->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Date From</label>
                    <input id="filter_date_from" type="date" class="form-control" value="">
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Date To</label>
                    <input id="filter_date_to" type="date" class="form-control" value="">
                </div>
            </div>

            <div class="table-responsive">
                {!! $dataTable->table() !!}
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reload = () => {
                window.LaravelDataTables?.['expenses-table']?.ajax?.reload();
            };

            document.getElementById('filter_type').addEventListener('change', reload);
            document.getElementById('filter_category').addEventListener('change', reload);
            document.getElementById('filter_date_from').addEventListener('change', reload);
            document.getElementById('filter_date_to').addEventListener('change', reload);
        });
    </script>
@endpush