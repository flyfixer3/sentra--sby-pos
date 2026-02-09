@extends('layouts.app')

@section('title', 'Sale Order Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
@php
    $status = strtolower((string)($saleOrder->status ?? 'pending'));
    $statusBadge = match($status) {
        'pending' => 'bg-warning text-dark',
        'partial_delivered' => 'bg-info text-dark',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };

    $dateText = $saleOrder->date ? \Carbon\Carbon::parse($saleOrder->date)->format('d M Y') : '-';

    // ✅ RemainingMap (confirmed-based) masih boleh kamu pakai untuk tampilan status
    $remainingConfirmedMap = $remainingMap ?? [];

    // ✅ Kalau kamu sudah implement planned map di controller:
    $plannedRemainingMap = $plannedRemainingMap ?? $remainingConfirmedMap;

    $hasPlannedRemaining = false;
    foreach(($plannedRemainingMap ?? []) as $rem){
        if ((int)$rem > 0) { $hasPlannedRemaining = true; break; }
    }

    // ✅ Link Quotation + Sale (Invoice)
    $quotationId = $saleOrder->quotation_id ? (int) $saleOrder->quotation_id : null;
    $saleId      = $saleOrder->sale_id ? (int) $saleOrder->sale_id : null;

    // route names yang mungkin dipakai project:
    // - Quotation: quotations.show / quotation.show / sale-quotations.show / etc
    // - Sale Invoice: sales.show / sale.show / invoices.show / etc
    // Kita pakai route() hanya kalau route exists, supaya tidak error.

    $quotationUrl = null;
    if ($quotationId) {
        if (\Illuminate\Support\Facades\Route::has('quotations.show')) {
            $quotationUrl = route('quotations.show', $quotationId);
        } elseif (\Illuminate\Support\Facades\Route::has('quotation.show')) {
            $quotationUrl = route('quotation.show', $quotationId);
        } elseif (\Illuminate\Support\Facades\Route::has('sale-quotations.show')) {
            $quotationUrl = route('sale-quotations.show', $quotationId);
        }
    }

    $saleUrl = null;
    if ($saleId) {
        if (\Illuminate\Support\Facades\Route::has('sales.show')) {
            $saleUrl = route('sales.show', $saleId);
        } elseif (\Illuminate\Support\Facades\Route::has('sale.show')) {
            $saleUrl = route('sale.show', $saleId);
        } elseif (\Illuminate\Support\Facades\Route::has('invoices.show')) {
            $saleUrl = route('invoices.show', $saleId);
        } elseif (\Illuminate\Support\Facades\Route::has('sale-invoices.show')) {
            $saleUrl = route('sale-invoices.show', $saleId);
        }
    }
@endphp

<div class="container-fluid">

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="mb-0">{{ $saleOrder->reference }}</h4>
                        <span class="badge {{ $statusBadge }}">{{ strtoupper($saleOrder->status ?? 'PENDING') }}</span>
                    </div>

                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong>
                        • Customer: <strong>{{ $saleOrder->customer?->customer_name ?? '-' }}</strong>
                    </div>

                    {{-- ✅ LINKABLE Quotation + Invoice --}}
                    <div class="text-muted small mt-1">
                        Quotation:
                        <strong>
                            @if($quotationId)
                                @if($quotationUrl)
                                    <a href="{{ $quotationUrl }}" class="text-decoration-none">
                                        #{{ $quotationId }}
                                    </a>
                                @else
                                    #{{ $quotationId }}
                                @endif
                            @else
                                -
                            @endif
                        </strong>

                        • Invoice (Sale):
                        <strong>
                            @if($saleId)
                                @if($saleUrl)
                                    <a href="{{ $saleUrl }}" class="text-decoration-none">
                                        #{{ $saleId }}
                                    </a>
                                @else
                                    #{{ $saleId }}
                                @endif
                            @else
                                -
                            @endif
                        </strong>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @can('create_sale_deliveries')
                        @if($hasPlannedRemaining)
                            <a class="btn btn-primary"
                               href="{{ route('sale-deliveries.create', ['source'=>'sale_order', 'sale_order_id'=>$saleOrder->id]) }}">
                                <i class="bi bi-truck"></i> Create Sale Delivery
                            </a>
                        @else
                            <button class="btn btn-secondary" disabled>
                                <i class="bi bi-check2-circle"></i> All Items Planned
                            </button>
                        @endif
                    @endcan

                    {{-- ✅ Edit/Delete only when pending --}}
                    @can('edit_sale_orders')
                        @if($status === 'pending')
                            <a href="{{ route('sale-orders.edit', $saleOrder->id) }}" class="btn btn-outline-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        @endif
                    @endcan

                    @can('delete_sale_orders')
                        @if($status === 'pending')
                            <form action="{{ route('sale-orders.destroy', $saleOrder->id) }}" method="POST"
                                  onsubmit="return confirm('Delete this Sale Order? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        @endif
                    @endcan

                    <!-- <a href="{{ route('sale-orders.index') }}" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> Back
                    </a> -->
                </div>
            </div>

            @if(!empty($saleOrder->note))
                <hr class="my-3">
                <div class="p-3 border rounded-3 bg-light">
                    <div class="text-muted small">Note</div>
                    <div>{{ $saleOrder->note }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Items --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h6 class="mb-0">Items Progress</h6>
                <div class="text-muted small">
                    Delivered = confirmed/partial deliveries • Planned = pending+confirmed+partial
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width:120px;">Ordered</th>
                            <th class="text-end" style="width:120px;">Delivered</th>
                            <th class="text-end" style="width:120px;">Planned Rem.</th>
                            <th style="width:280px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($saleOrder->items as $it)
                        @php
                            $pid = (int) $it->product_id;
                            $ordered = (int) ($it->quantity ?? 0);

                            $remConfirmed = (int) ($remainingConfirmedMap[$pid] ?? 0);
                            $delivered = max(0, $ordered - $remConfirmed);

                            $remPlanned = (int) ($plannedRemainingMap[$pid] ?? 0);
                            $plannedCovered = max(0, $ordered - $remPlanned);

                            $deliveredPct = $ordered > 0 ? (int) round(($delivered / $ordered) * 100) : 0;
                            $plannedPct = $ordered > 0 ? (int) round(($plannedCovered / $ordered) * 100) : 0;

                            if ($deliveredPct > 100) $deliveredPct = 100;
                            if ($plannedPct > 100) $plannedPct = 100;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $it->product?->product_name ?? ('Product ID '.$pid) }}</div>
                                <div class="text-muted small">product_id: {{ $pid }}</div>
                            </td>

                            <td class="text-end">
                                <span class="badge bg-secondary">{{ number_format($ordered) }}</span>
                            </td>

                            <td class="text-end">
                                <span class="badge bg-success">{{ number_format($delivered) }}</span>
                            </td>

                            <td class="text-end">
                                @if($remPlanned <= 0)
                                    <span class="badge bg-dark">0</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ number_format($remPlanned) }}</span>
                                @endif
                            </td>

                            <td>
                                <div class="small text-muted mb-1">Delivered: {{ $deliveredPct }}% • Planned: {{ $plannedPct }}%</div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $plannedPct }}%"></div>
                                </div>
                                <div class="small text-muted mt-1">
                                    Planned covers {{ number_format($plannedCovered) }} of {{ number_format($ordered) }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    {{-- Deliveries --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h6 class="mb-0">Sale Deliveries</h6>
                <div class="text-muted small">History pengiriman untuk Sale Order ini</div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th style="width:140px;">Date</th>
                            <th>Warehouse</th>
                            <th style="width:140px;">Status</th>
                            <th class="text-center" style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($saleOrder->deliveries as $d)
                        @php
                            $dst = strtolower((string)($d->status ?? 'pending'));
                            $dBadge = match($dst) {
                                'pending' => 'bg-warning text-dark',
                                'confirmed' => 'bg-success',
                                'partial' => 'bg-info text-dark',
                                'cancelled' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $d->reference }}</td>
                            <td>{{ $d->date ? \Carbon\Carbon::parse($d->date)->format('d M Y') : '-' }}</td>
                            <td>{{ $d->warehouse?->warehouse_name ?? ('WH#'.$d->warehouse_id) }}</td>
                            <td><span class="badge {{ $dBadge }}">{{ strtoupper($dst) }}</span></td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('sale-deliveries.show', $d->id) }}">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No deliveries yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>
@endsection
