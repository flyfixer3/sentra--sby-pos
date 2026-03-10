@extends('layouts.app')

@section('title', 'Sales')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@push('page_css')
<style>
    .index-filter-card {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: .75rem;
        background: linear-gradient(180deg, #fbfcfe 0%, #f5f7fb 100%);
        padding: 1rem 1rem .9rem;
        margin-bottom: 1rem;
    }
    .index-filter-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .75rem;
    }
    .index-filter-card .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: .35rem;
    }
    .index-filter-card .form-control {
        border-radius: .65rem;
        min-height: 38px;
    }
    .index-filter-actions {
        display: flex;
        gap: .5rem;
        align-items: end;
        justify-content: flex-end;
    }
</style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Sales</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('sales.create') }}" class="btn btn-primary">
                            Add Sale <i class="bi bi-plus"></i>
                        </a>

                        <hr>

                        <div class="index-filter-card">
                            <div class="index-filter-title">Table Filter</div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4 col-lg-3">
                                    <label for="sale-deleted-filter" class="form-label">Soft Delete Status</label>
                                    <select id="sale-deleted-filter" class="form-control">
                                        <option value="all">All</option>
                                        <option value="active">Not Deleted</option>
                                        <option value="trashed">Deleted</option>
                                    </select>
                                </div>

                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label">Filter Summary</label>
                                    <div class="form-control d-flex align-items-center bg-white">
                                        <i class="bi bi-funnel mr-2 text-muted"></i>
                                        <span class="text-muted small">Choose which soft delete state to show in the table.</span>
                                    </div>
                                </div>

                                <div class="col-md-4 col-lg-6">
                                    <div class="index-filter-actions">
                                        <button type="button" class="btn btn-outline-secondary" id="sale-deleted-filter-reset">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            {!! $dataTable->table(['class' => 'table table-striped table-bordered w-100'], true) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}

    <script>
    (function () {
        function dt() {
            return window.LaravelDataTables && window.LaravelDataTables["sales-table"]
                ? window.LaravelDataTables["sales-table"]
                : null;
        }

        function bind() {
            var table = dt();
            if (!table) return;

            table.on('preXhr.dt', function (e, settings, data) {
                data.deleted_filter = document.getElementById('sale-deleted-filter')?.value || 'all';
            });

            document.getElementById('sale-deleted-filter')?.addEventListener('change', function () {
                table.ajax.reload(null, true);
            });

            document.getElementById('sale-deleted-filter-reset')?.addEventListener('click', function () {
                var filter = document.getElementById('sale-deleted-filter');
                if (filter) {
                    filter.value = 'all';
                }
                table.ajax.reload(null, true);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                if (dt()) {
                    clearInterval(timer);
                    bind();
                }
                if (tries > 20) clearInterval(timer);
            }, 100);
        });
    })();
    </script>
@endpush
