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
                        Date: {{ $saleDelivery->date ? $saleDelivery->date->format('d-m-Y') : ($saleDelivery->getAttributes()['date'] ?? '-') }} •
                        Warehouse: {{ optional($saleDelivery->warehouse)->warehouse_name ?? '-' }}
                    </div>

                    <div class="text-muted small mt-1">
                        Created at: {{ $saleDelivery->created_at ? $saleDelivery->created_at->format('d-m-Y H:i') : '-' }} •
                        Created by: {{ optional($saleDelivery->creator)->name ?? '-' }}
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    @php $st = strtolower((string)($saleDelivery->status ?? '')); @endphp

                    <span class="badge
                        {{ $st==='pending' ? 'bg-warning text-dark' : '' }}
                        {{ $st==='confirmed' ? 'bg-success' : '' }}
                        {{ $st==='partial' ? 'bg-info text-dark' : '' }}
                        {{ $st==='cancelled' ? 'bg-danger' : '' }}
                    ">
                        {{ strtoupper((string) $saleDelivery->status) }}
                    </span>

                    @if(session('active_branch') && in_array($st, ['pending','partial'], true))
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
                    <div class="fw-bold">{{ optional($saleDelivery->customer)->customer_name ?? ($saleDelivery->customer_name ?? '-') }}</div>
                </div>

                <div class="col-md-6">
                    <div class="small text-muted">Note</div>
                    <div>{{ $saleDelivery->note ?: '-' }}</div>
                </div>
            </div>

            <hr class="my-3">

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted">Confirmed At</div>
                    <div class="fw-bold">
                        {{ $saleDelivery->confirmed_at ? $saleDelivery->confirmed_at->format('d-m-Y H:i') : '-' }}
                    </div>
                    <div class="small text-muted mt-1">
                        Confirmed by: {{ $saleDelivery->confirmed_at ? (optional($saleDelivery->confirmer)->name ?? '-') : '-' }}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="small text-muted">Confirmation Note</div>
                    <div>{{ $saleDelivery->confirm_note ?: '-' }}</div>
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
                            <th class="text-end" style="width:120px;">Qty</th>
                            <th class="text-end" style="width:120px;">Good</th>
                            <th class="text-end" style="width:120px;">Defect</th>
                            <th class="text-end" style="width:120px;">Damaged</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saleDelivery->items as $it)
                            <tr>
                                <td>{{ optional($it->product)->product_name ?? ($it->product_name ?? '-') }}</td>
                                <td class="text-end">{{ number_format((int)($it->quantity ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int)($it->qty_good ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int)($it->qty_defect ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int)($it->qty_damaged ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-2">
                * Breakdown Good/Defect/Damaged akan terisi setelah proses confirm.
            </div>
        </div>
    </div>
</div>
@endsection
