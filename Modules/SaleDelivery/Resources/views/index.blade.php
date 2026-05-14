@extends('layouts.app')

@section('title', 'Sale Deliveries')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item active">Sale Deliveries</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0">Sale Deliveries</h4>
      <small class="text-muted">Manage pending deliveries and confirm stock-out.</small>
    </div>
  </div>

  @include('utils.alerts')

  <div class="card">
    <div class="card-body">
      @include('includes.status-legend', [
        'id' => 'saleDeliveryStatusLegend',
        'title' => 'Sale Delivery Status Meaning',
        'items' => [
          [
            'status' => 'pending',
            'badge_class' => 'badge badge-warning',
            'meaning' => 'Delivery is planned but not confirmed yet.',
            'trigger' => 'Created from a Sale, Sale Order, or Quotation flow before stock-out confirmation.',
          ],
          [
            'status' => 'confirmed',
            'badge_class' => 'badge badge-success',
            'meaning' => 'Delivery has been confirmed and stock-out processing is finalized.',
            'trigger' => 'Confirm Sale Delivery is submitted successfully.',
          ],
          [
            'status' => 'cancelled',
            'badge_class' => 'badge badge-danger',
            'meaning' => 'Delivery was cancelled.',
            'trigger' => 'Supported by existing status badges/migrations; cancellation flow, if used, keeps it out of active completion checks.',
          ],
        ],
      ])

      {!! $dataTable->table(['class' => 'table table-bordered table-striped align-middle'], true) !!}
    </div>
  </div>
</div>
@endsection

@push('page_scripts')
{!! $dataTable->scripts() !!}
@endpush
