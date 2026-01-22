@extends('layouts.app')

@section('title', "Sale Delivery #{$saleDelivery->reference}")

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item active">Detail</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @include('utils.alerts')

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">{{ $saleDelivery->reference }}</h4>
                    <div class="text-muted small">
                        Date: {{ $saleDelivery->getAttributes()['date'] ?? $saleDelivery->date }} •
                        Warehouse: {{ optional($saleDelivery->warehouse)->warehouse_name ?? '-' }}
                    </div>
                </div>

                <div class="d-flex gap-2">
                    @php $st = strtolower($saleDelivery->status); @endphp
                    <span class="badge
                        {{ $st==='pending' ? 'bg-warning text-dark' : '' }}
                        {{ $st==='confirmed' ? 'bg-success' : '' }}
                        {{ $st==='cancelled' ? 'bg-danger' : '' }}
                    ">
                        {{ strtoupper($saleDelivery->status) }}
                    </span>

                    @if(session('active_branch') && $st==='pending')
                        <a href="{{ route('sale-deliveries.confirm.form', $saleDelivery->id) }}" class="btn btn-primary btn-sm">
                            Confirm <i class="bi bi-check-lg"></i>
                        </a>
                    @endif
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted">Customer</div>
                    <div class="fw-bold">{{ $saleDelivery->customer_name ?? optional($saleDelivery->customer)->customer_name }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Note</div>
                    <div>{{ $saleDelivery->note ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-2">Items</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saleDelivery->items as $it)
                            <tr>
                                <td>{{ $it->product_name ?? optional($it->product)->product_name }}</td>
                                <td class="text-end">{{ number_format((int)$it->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($saleDelivery->confirmed_at)
                <div class="small text-muted mt-2">
                    Confirmed at: {{ $saleDelivery->confirmed_at }} • Confirmed by: {{ $saleDelivery->confirmed_by ?? '-' }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
