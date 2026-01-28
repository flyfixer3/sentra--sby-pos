@extends('layouts.app')

@section('title', "Sale Delivery #{$saleDelivery->reference}")

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item active">Details</li>
</ol>
@endsection

@section('content')
@php
    $st = strtolower((string)($saleDelivery->status ?? 'pending'));

    $badgeClass = match($st) {
        'pending' => 'bg-warning text-dark',
        'confirmed' => 'bg-success',
        'partial' => 'bg-info text-dark',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };

    $dateText = $saleDelivery->date
        ? (method_exists($saleDelivery->date, 'format') ? $saleDelivery->date->format('d M Y') : \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y'))
        : '-';

    $createdAt = $saleDelivery->created_at ? $saleDelivery->created_at->format('d M Y H:i') : '-';
    $confirmedAt = $saleDelivery->confirmed_at ? $saleDelivery->confirmed_at->format('d M Y H:i') : '-';

    $soRef = $saleDelivery->saleOrder?->reference ?? null;
@endphp

<div class="container-fluid">
    @include('utils.alerts')

    {{-- Header --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="mb-0">{{ $saleDelivery->reference }}</h4>
                        <span class="badge {{ $badgeClass }}">{{ strtoupper($st) }}</span>
                    </div>

                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong> •
                        Warehouse: <strong>{{ $saleDelivery->warehouse?->warehouse_name ?? '-' }}</strong>
                    </div>

                    <div class="text-muted small mt-1">
                        Created: <strong>{{ $createdAt }}</strong> by <strong>{{ $saleDelivery->creator?->name ?? '-' }}</strong>
                        @if($saleDelivery->sale_order_id)
                            • Sale Order: <a href="{{ route('sale-orders.show', $saleDelivery->sale_order_id) }}"><strong>{{ $soRef ?? ('SO#'.$saleDelivery->sale_order_id) }}</strong></a>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if(session('active_branch') && in_array($st, ['pending','partial'], true))
                        <a href="{{ route('sale-deliveries.confirm.form', $saleDelivery->id) }}"
                           class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Confirm Delivery
                        </a>
                    @endif

                    <a href="{{ route('sale-deliveries.print', $saleDelivery->id) }}"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-printer"></i> Print
                    </a>

                    <a href="{{ route('sale-deliveries.index') }}" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <hr class="my-3">

            {{-- Summary --}}
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-bold">
                            {{ $saleDelivery->customer?->customer_name ?? ($saleDelivery->customer_name ?? '-') }}
                        </div>
                        <div class="text-muted small mt-1">
                            Note: {{ $saleDelivery->note ?: '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="text-muted small">Confirmation</div>
                        <div class="fw-bold">Confirmed at: {{ $confirmedAt }}</div>
                        <div class="text-muted small mt-1">
                            Confirmed by: {{ $saleDelivery->confirmed_at ? ($saleDelivery->confirmer?->name ?? '-') : '-' }}
                        </div>
                        <div class="text-muted small mt-1">
                            Confirmation Note: {{ $saleDelivery->confirm_note ?: '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Items --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h6 class="mb-0">Items</h6>
                <div class="text-muted small">
                    * Breakdown Good/Defect/Damaged akan terisi setelah proses confirm.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width:120px;">Expected</th>
                            <th class="text-end" style="width:120px;">Good</th>
                            <th class="text-end" style="width:120px;">Defect</th>
                            <th class="text-end" style="width:120px;">Damaged</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saleDelivery->items as $it)
                            @php
                                $expected = (int)($it->quantity ?? 0);
                                $good = (int)($it->qty_good ?? 0);
                                $defect = (int)($it->qty_defect ?? 0);
                                $damaged = (int)($it->qty_damaged ?? 0);
                                $sum = $good + $defect + $damaged;
                                $isFilled = $sum > 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $it->product?->product_name ?? ($it->product_name ?? '-') }}</div>
                                    <div class="text-muted small">product_id: {{ (int)($it->product_id ?? 0) }}</div>
                                </td>

                                <td class="text-end">
                                    <span class="badge bg-secondary">{{ number_format($expected) }}</span>
                                </td>

                                <td class="text-end">
                                    <span class="badge {{ $isFilled ? 'bg-success' : 'bg-light text-dark border' }}">
                                        {{ number_format($good) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="badge {{ $isFilled ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">
                                        {{ number_format($defect) }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="badge {{ $isFilled ? 'bg-danger' : 'bg-light text-dark border' }}">
                                        {{ number_format($damaged) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection
