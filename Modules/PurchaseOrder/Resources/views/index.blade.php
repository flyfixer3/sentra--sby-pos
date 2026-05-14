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

                        @include('includes.status-legend', [
                            'id' => 'purchaseOrderStatusLegend',
                            'title' => 'Purchase Order Status Meaning',
                            'items' => [
                                [
                                    'status' => 'Pending',
                                    'badge_class' => 'badge badge-info',
                                    'meaning' => 'No goods have been received yet.',
                                    'trigger' => 'Default status, or fulfilled quantity is zero.',
                                ],
                                [
                                    'status' => 'Partial',
                                    'badge_class' => 'badge badge-warning',
                                    'meaning' => 'Some goods have been received, but remaining quantity still exists.',
                                    'trigger' => 'Fulfilled quantity is greater than zero and at least one ordered quantity remains.',
                                ],
                                [
                                    'status' => 'Delivered',
                                    'badge_class' => 'badge badge-primary',
                                    'meaning' => 'All goods have been received, but the Purchase Order is not fully invoiced yet.',
                                    'trigger' => 'All ordered quantity is fulfilled while invoice coverage is still incomplete.',
                                ],
                                [
                                    'status' => 'Completed',
                                    'badge_class' => 'badge badge-success',
                                    'meaning' => 'All goods have been received and the Purchase Order is fully invoiced.',
                                    'trigger' => 'All ordered quantity is fulfilled and all active delivery/invoice requirements are satisfied.',
                                ],
                                [
                                    'status' => 'Sent to Supplier',
                                    'badge_class' => 'badge badge-secondary',
                                    'meaning' => 'Supplier communication marker, separate from fulfillment status.',
                                    'trigger' => 'The order is marked or emailed as sent to supplier; shown by the communication column/fields, not the main fulfillment status lifecycle.',
                                ],
                            ],
                        ])

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush
