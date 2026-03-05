@extends('layouts.app')

@section('title', 'Expense Categories')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">Expenses</a></li>
        <li class="breadcrumb-item active">Categories</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            @include('utils.alerts')
            <div class="card">
                <div class="card-body">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#categoryCreateModal">
                        Add Category <i class="bi bi-plus"></i>
                    </button>

                    <hr>

                    <div class="table-responsive">
                        {!! $dataTable->table() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="categoryCreateModal" tabindex="-1" role="dialog" aria-labelledby="categoryCreateModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryCreateModalLabel">Create Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ route('expense-categories.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Category Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="category_name" required>
                    </div>

                    <div class="form-group">
                        <label for="subaccount_number">Expense Account (Subaccount)</label>
                        <select class="form-control" name="subaccount_number">
                            <option value="">-- Optional (but REQUIRED for CREDIT expense posting) --</option>

                            @foreach(
                                \App\Models\AccountingSubaccount::join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
                                    ->where('accounting_accounts.is_active', '=', '1')
                                    ->whereIn('accounting_accounts.account_number', [16,17])  // Beban & Beban Lainnya
                                    ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
                                    ->orderBy('accounting_subaccounts.subaccount_number')
                                    ->get()
                                as $acc
                            )
                                <option value="{{ $acc->subaccount_number }}">
                                    ({{ $acc->subaccount_number }}) - {{ $acc->subaccount_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            Map category to expense account so CREDIT (OUT) can post correctly.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="category_description">Description</label>
                        <textarea class="form-control" name="category_description" id="category_description" rows="4"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create <i class="bi bi-check"></i></button>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush