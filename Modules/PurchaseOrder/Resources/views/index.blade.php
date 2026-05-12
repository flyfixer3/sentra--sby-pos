@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Purchase Orders</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid purchase-orders-page">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
                            Add Purchase Order <i class="bi bi-plus"></i>
                        </a>

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_css')
<style>
    .purchase-orders-page .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
    }

    .purchase-orders-page table.dataTable,
    .purchase-orders-page table.dataTable td,
    .purchase-orders-page table.dataTable th {
        overflow: visible;
    }

    .purchase-orders-page .po-action-dropdown .dropdown-menu {
        z-index: 2000;
    }

    .purchase-orders-page .po-dropdown-open {
        position: relative;
        z-index: 2000;
    }
</style>
@endpush

@push('page_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $(document).on('shown.bs.dropdown', '.purchase-orders-page .po-action-dropdown', function () {
                $(this).closest('tr').addClass('po-dropdown-open');
            });

            $(document).on('hidden.bs.dropdown', '.purchase-orders-page .po-action-dropdown', function () {
                $(this).closest('tr').removeClass('po-dropdown-open');
            });
        });
    </script>
    {!! $dataTable->scripts() !!}
@endpush
