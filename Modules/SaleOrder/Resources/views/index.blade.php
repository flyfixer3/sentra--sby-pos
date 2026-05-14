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
                    @include('includes.status-legend', [
                        'id' => 'saleOrderStatusLegend',
                        'title' => 'Sale Order Status Meaning',
                        'items' => [
                            [
                                'status' => 'pending',
                                'badge_class' => 'badge badge-warning',
                                'meaning' => 'Sale Order is created, but no confirmed delivery quantity has been fulfilled yet.',
                                'trigger' => 'Default status, or fulfillment returns to no delivered quantity.',
                            ],
                            [
                                'status' => 'partial_delivered',
                                'badge_class' => 'badge badge-info',
                                'meaning' => 'Some ordered quantity has been confirmed as delivered, with remaining quantity still open.',
                                'trigger' => 'Confirmed delivery quantity is greater than zero but less than total ordered quantity.',
                            ],
                            [
                                'status' => 'delivered',
                                'badge_class' => 'badge badge-primary',
                                'meaning' => 'All ordered quantity has been delivered, but not all confirmed deliveries are invoiced yet.',
                                'trigger' => 'All quantities are delivered, while at least one confirmed delivery is not linked to a Sales Invoice.',
                            ],
                            [
                                'status' => 'completed',
                                'badge_class' => 'badge badge-success',
                                'meaning' => 'Sale Order is fully delivered and all confirmed deliveries are invoiced.',
                                'trigger' => 'All ordered quantity is delivered and every confirmed delivery is linked to a Sales Invoice.',
                            ],
                            [
                                'status' => 'cancelled',
                                'badge_class' => 'badge badge-danger',
                                'meaning' => 'Sale Order was cancelled.',
                                'trigger' => 'Existing status display supports cancelled orders.',
                            ],
                        ],
                    ])

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
