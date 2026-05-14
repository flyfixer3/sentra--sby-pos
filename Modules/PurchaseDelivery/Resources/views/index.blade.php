@extends('layouts.app')

@section('title', 'Purchase Deliveries')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Purchase Deliveries</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">
                            Add Purchase Deliveries <i class="bi bi-plus"></i>
                        </a> -->

                        <hr>

                        @include('includes.status-legend', [
                            'id' => 'purchaseDeliveryStatusLegend',
                            'title' => 'Purchase Delivery Status Meaning',
                            'items' => [
                                [
                                    'status' => 'pending',
                                    'badge_class' => 'badge badge-warning',
                                    'meaning' => 'Delivery has been created but receiving has not been finalized.',
                                    'trigger' => 'Default status before any receiving confirmation is locked.',
                                ],
                                [
                                    'status' => 'partial',
                                    'badge_class' => 'badge badge-info',
                                    'meaning' => 'Some quantities have been received or confirmed, but remaining quantity still exists.',
                                    'trigger' => 'A receiving batch is confirmed and at least one expected quantity remains open.',
                                ],
                                [
                                    'status' => 'received',
                                    'badge_class' => 'badge badge-success',
                                    'meaning' => 'All expected quantities have been received or confirmed.',
                                    'trigger' => 'Receiving confirmation leaves no remaining expected quantity.',
                                ],
                                [
                                    'status' => 'cancelled',
                                    'badge_class' => 'badge badge-danger',
                                    'meaning' => 'Delivery was cancelled.',
                                    'trigger' => 'Existing badge fallback supports cancelled/canceled values if they appear.',
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
