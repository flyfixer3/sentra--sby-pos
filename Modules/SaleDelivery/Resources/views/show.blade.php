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
    $st = strtolower(trim((string)($saleDelivery->status ?? 'pending')));

    $badgeClass = match($st) {
        'pending' => 'bg-warning text-dark',
        'confirmed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };

    $dateText = $saleDelivery->date
        ? (method_exists($saleDelivery->date, 'format') ? $saleDelivery->date->format('d M Y') : \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y'))
        : '-';

    $createdAt = $saleDelivery->created_at ? $saleDelivery->created_at->format('d M Y H:i') : '-';
    $confirmedAt = $saleDelivery->confirmed_at ? $saleDelivery->confirmed_at->format('d M Y H:i') : '-';

    $soRef = $saleDelivery->saleOrder?->reference ?? null;

    $canConfirm = ($st === 'pending');
    $canPrint = ($st === 'confirmed');

    $canCreateInvoice = ($st === 'confirmed' && empty($saleDelivery->sale_id));
@endphp

<div class="container-fluid">
    @include('utils.alerts')

    {{-- Header / Hero --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="mb-0">{{ $saleDelivery->reference }}</h4>
                        <span class="badge {{ $badgeClass }}">{{ strtoupper($st) }}</span>
                    </div>

                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong>
                        <span class="mx-1">•</span>
                        Warehouse: <strong>{{ $saleDelivery->warehouse?->warehouse_name ?? '-' }}</strong>
                    </div>

                    <div class="text-muted small mt-1">
                        Created: <strong>{{ $createdAt }}</strong>
                        by <strong>{{ $saleDelivery->creator?->name ?? '-' }}</strong>
                    </div>

                    {{-- ✅ moved to next line (lebih enak dilihat) --}}
                    <div class="text-muted small mt-1">
                        @if($saleDelivery->sale_order_id)
                            <span class="me-2">
                                <i class="bi bi-clipboard-check me-1"></i>
                                Sale Order:
                                <a href="{{ route('sale-orders.show', $saleDelivery->sale_order_id) }}">
                                    <strong>{{ $soRef ?? ('SO#'.$saleDelivery->sale_order_id) }}</strong>
                                </a>
                            </span>
                        @endif

                        @if($saleDelivery->quotation_id)
                            <span>
                                <i class="bi bi-file-earmark-text me-1"></i>
                                Quotation:
                                <a href="{{ route('quotations.show', $saleDelivery->quotation_id) }}">
                                    <strong>{{ $saleDelivery->quotation_id }}</strong>
                                </a>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if(session('active_branch') && $canConfirm)
                        <a href="{{ route('sale-deliveries.confirm.form', $saleDelivery->id) }}"
                           class="btn btn-primary">
                            <i class="bi bi-check2-circle me-1"></i> Confirm Delivery
                        </a>
                    @endif

                    @if($canCreateInvoice)
                        <form method="POST"
                              action="{{ route('sale-deliveries.create-invoice', $saleDelivery->id) }}"
                              class="d-inline"
                              onsubmit="return confirm('Create Invoice from this Sale Delivery? (1 Delivery = 1 Invoice)');">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-receipt me-1"></i> Create Invoice
                            </button>
                        </form>
                    @elseif(!empty($saleDelivery->sale_id))
                        <a href="{{ route('sales.show', (int)$saleDelivery->sale_id) }}" class="btn btn-outline-success">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Invoice
                        </a>
                    @endif

                    @if($canPrint)
                        <button type="button"
                            class="btn btn-outline-secondary"
                            onclick="printSaleDelivery({{ $saleDelivery->id }})">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    @endif

                    @can('delete_sale_deliveries')
                        @if($st !== 'confirmed')
                            <form method="POST"
                                action="{{ route('sale-deliveries.destroy', $saleDelivery->id) }}"
                                class="d-inline"
                                onsubmit="return confirm('Delete this Sale Delivery? Only allowed if status is not CONFIRMED.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            <hr class="my-3">

            {{-- Summary Cards --}}
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Customer</div>
                            <span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i> Info</span>
                        </div>
                        <hr class="my-2">
                        <div class="fw-bold">
                            {{ $saleDelivery->customer?->customer_name ?? ($saleDelivery->customer_name ?? '-') }}
                        </div>
                        <div class="text-muted small mt-1">
                            Note: {{ $saleDelivery->note ?: '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Confirmation</div>
                            <span class="badge bg-light text-dark border"><i class="bi bi-shield-check me-1"></i> Status</span>
                        </div>
                        <hr class="my-2">
                        <div class="small">
                            <div>Confirmed at: <strong>{{ $confirmedAt }}</strong></div>
                            <div class="mt-1">
                                Confirmed by:
                                <strong>{{ $saleDelivery->confirmed_at ? ($saleDelivery->confirmer?->name ?? '-') : '-' }}</strong>
                            </div>
                            <div class="mt-1">
                                Confirmation Note: <strong>{{ $saleDelivery->confirm_note ?: '-' }}</strong>
                            </div>
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
                <div>
                    <h6 class="mb-0">Items</h6>
                    <div class="text-muted small">Breakdown Good/Defect/Damaged akan terisi setelah confirm.</div>
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

                                $isMismatch = ($st === 'confirmed' && $expected > 0 && $sum > 0 && $sum !== $expected);
                            @endphp
                            <tr @if($isMismatch) class="table-danger" @endif>
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

@push('page_scripts')
<script>
async function printSaleDelivery(id) {
    try {
        const res = await fetch(`{{ url('/') }}/sale-deliveries/${id}/prepare-print`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content'),
                'Accept': 'application/json',
            }
        });

        const data = await res.json();

        if (!res.ok || !data.ok) {
            alert(data.message || 'Cannot print sale delivery.');
            return;
        }

        window.open(data.pdf_url, '_blank');
    } catch (e) {
        alert('Unexpected error. Please try again.');
    }
}
</script>
@endpush
