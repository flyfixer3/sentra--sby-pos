@extends('layouts.app')

@section('title', 'Sale Orders')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Sale Orders</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div class="text-muted">All Sale Orders</div>
                    <div class="mfs-auto d-flex gap-2">
                        @can('create_sale_orders')
                            <a href="{{ route('sale-orders.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-journal-plus"></i> Create
                            </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    {!! $dataTable->table() !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
{!! $dataTable->scripts() !!}
@endpush
