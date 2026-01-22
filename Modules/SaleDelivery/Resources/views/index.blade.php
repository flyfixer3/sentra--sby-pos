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
      {!! $dataTable->table(['class' => 'table table-bordered table-striped align-middle'], true) !!}
    </div>
  </div>
</div>
@endsection

@push('page_scripts')
{!! $dataTable->scripts() !!}
@endpush
