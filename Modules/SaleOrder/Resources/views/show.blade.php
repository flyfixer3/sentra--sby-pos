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

    $remainingConfirmedMap = $remainingMap ?? [];
    $plannedRemainingMap = $plannedRemainingMap ?? $remainingConfirmedMap;

    $hasPlannedRemaining = false;
    foreach(($plannedRemainingMap ?? []) as $rem){
        if ((int)$rem > 0) { $hasPlannedRemaining = true; break; }
    }

    $quotationId = $saleOrder->quotation_id ? (int) $saleOrder->quotation_id : null;
    $saleId      = $saleOrder->sale_id ? (int) $saleOrder->sale_id : null;

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

    // ✅ Financial Summary
    $subtotal = (int)($saleOrder->subtotal_amount ?? 0);
    $taxAmt   = (int)($saleOrder->tax_amount ?? 0);
    $fee      = (int)($saleOrder->fee_amount ?? 0);
    $ship     = (int)($saleOrder->shipping_amount ?? 0);
    $total    = (int)($saleOrder->total_amount ?? 0);

    $discInfo = (int)($saleOrder->discount_amount ?? 0); // informational diff
    $dpMax    = (int)($saleOrder->deposit_amount ?? 0);
    $dpRec    = (int)($saleOrder->deposit_received_amount ?? 0);
    $remainingAfterDp = max(0, $total - $dpRec);
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

    {{-- ✅ NEW: Financial Summary --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3">Financial Summary</h6>

            <div class="row">
                <div class="col-lg-5">
                    <table class="table table-sm">
                        <tr><td>Items Subtotal (Sell)</td><td class="text-end">{{ format_currency($subtotal) }}</td></tr>
                        <tr><td>Tax</td><td class="text-end">{{ format_currency($taxAmt) }}</td></tr>
                        <tr><td>Platform Fee</td><td class="text-end">{{ format_currency($fee) }}</td></tr>
                        <tr><td>Shipping</td><td class="text-end">{{ format_currency($ship) }}</td></tr>
                        <tr><td><strong>Grand Total</strong></td><td class="text-end"><strong>{{ format_currency($total) }}</strong></td></tr>
                    </table>
                    <div class="text-muted small">
                        Discount di SO adalah <strong>informasi</strong> (selisih Master vs Sell), bukan pengurangan kedua kali.
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-muted small">Discount Info (Diff)</div>
                                <div class="fw-semibold">{{ format_currency($discInfo) }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">DP Planned (Max)</div>
                                <div class="fw-semibold">{{ format_currency($dpMax) }}</div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <div class="text-muted small">DP Received</div>
                                <div class="fw-semibold">{{ format_currency($dpRec) }}</div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <div class="text-muted small">Remaining (after DP)</div>
                                <div class="fw-semibold">{{ format_currency($remainingAfterDp) }}</div>
                            </div>
                        </div>
                        <div class="text-muted small mt-2">
                            DP Received akan dipakai sebagai catatan pengurang tagihan saat Invoice dibuat (allocated pro-rata).
                        </div>
                    </div>
                </div>
            </div>
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

                            $pName = $it->product?->product_name ?? ('Product ID '.$pid);
                            $pCode = $it->product?->product_code ?? null;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $pName }}</div>
                                <div class="text-muted small">
                                    @if(!empty($pCode))
                                        <span class="badge bg-light text-dark border">{{ $pCode }}</span>
                                    @endif
                                    <span class="ms-1">product_id: {{ $pid }}</span>
                                </div>
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
