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

    // ✅ NEW: tampilkan warehouse
    $warehouseName = $saleDelivery->warehouse?->warehouse_name
        ?? (!empty($saleDelivery->warehouse_id) ? ('WH#' . (int)$saleDelivery->warehouse_id) : null);

    // ✅ NEW: walk-in flag (NO delete action from SD UI)
    $isWalkin = empty($saleDelivery->sale_order_id);
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
                        @else
                            <span class="me-2">
                                <i class="bi bi-clipboard-check me-1"></i>
                                Sale Order:
                                <span class="badge bg-secondary text-dark">WALK-IN</span>
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
                    @can('confirm_sale_deliveries')
                        @if(session('active_branch') && $canConfirm)
                            <a href="{{ route('sale-deliveries.confirm.form', $saleDelivery->id) }}"
                               class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Confirm Delivery
                            </a>
                        @endif
                    @endcan

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
                        {{-- ✅ PATCH: walk-in delivery tidak boleh di-delete dari UI SaleDelivery --}}
                        @if(!$isWalkin && $st !== 'confirmed')
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
                            <th style="width:220px;">Source Context</th>
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
                                $sourceItem = $it->saleOrderItem ?: $it->saleItem;
                                $sourceLabel = $it->sale_order_item_id
                                    ? ('SO Item #' . (int) $it->sale_order_item_id)
                                    : ($it->sale_item_id ? ('Sale Item #' . (int) $it->sale_item_id) : '-');
                                $serviceType = $sourceItem?->installation_type ?? null;
                                $sourcePrice = $sourceItem?->price ?? $it->price ?? null;
                                $vehicle = $sourceItem?->customerVehicle ?? null;
                            @endphp
                            <tr @if($isMismatch) class="table-danger" @endif>
                                <td>
                                    <div class="fw-semibold">{{ $it->product?->product_name ?? ($it->product_name ?? '-') }}</div>
                                    <span class="text-muted small">product_id: {{ (int)($it->product_id ?? 0) }}</span> <span class="text-muted small">| product_code: {{ $it->product?->product_code ?? '-' }}</span>
                                </td>

                                <td>
                                    <div class="small fw-semibold">{{ $sourceLabel }}</div>
                                    <div class="small text-muted">
                                        Service: <strong>{{ $serviceType ? str_replace('_', ' ', $serviceType) : '-' }}</strong>
                                    </div>
                                    <div class="small text-muted">
                                        Price: <strong>{{ $sourcePrice !== null ? number_format((float) $sourcePrice, 0, ',', '.') : '-' }}</strong>
                                    </div>
                                    <div class="small text-muted">
                                        Vehicle:
                                        <strong>
                                            @if($vehicle)
                                                {{ trim(implode(' ', array_filter([
                                                    $vehicle->vehicle_name ?? null,
                                                    $vehicle->license_number ?? null,
                                                    $vehicle->car_plate ?? null,
                                                ]))) ?: ('Vehicle #' . (int) $vehicle->id) }}
                                            @else
                                                -
                                            @endif
                                        </strong>
                                    </div>
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
                                <td colspan="6" class="text-center text-muted py-4">No items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    @if(strtolower((string)$saleDelivery->status) === 'confirmed')
        @php
            $pickedTables = [
                'Picked Defect Items' => $pickedDefectItems ?? collect(),
                'Picked Damaged Items' => $pickedDamagedItems ?? collect(),
            ];
        @endphp

        @foreach($pickedTables as $pickedTitle => $pickedRows)
            <div class="card mt-3">
                <div class="card-header">
                    <strong>{{ $pickedTitle }}</strong>
                </div>
                <div class="card-body">
                    @if($pickedRows->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID</th>
                                        <th>Product</th>
                                        <th>Warehouse / Rack</th>
                                        <th>Type</th>
                                        <th class="text-center" style="width:80px;">Qty</th>
                                        <th>Description</th>
                                        <th style="width:150px;">Moved Out</th>
                                        <th style="width:140px;">By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pickedRows as $row)
                                        @php
                                            $rackText = $row->rack_code ?? $row->rack_name ?? '-';
                                            $typeText = $row->defect_type
                                                ?? $row->damage_type
                                                ?? $row->reason
                                                ?? '-';
                                            if (!empty($row->defect_types)) {
                                                $decodedTypes = json_decode((string) $row->defect_types, true);
                                                if (is_array($decodedTypes) && count($decodedTypes) > 0) {
                                                    $typeText = implode(', ', $decodedTypes);
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td>#{{ (int) $row->id }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $row->product_name ?? ('Product#' . (int) $row->product_id) }}</div>
                                                <div class="small text-muted">{{ $row->product_code ?? '-' }}</div>
                                            </td>
                                            <td>
                                                <div>{{ $row->warehouse_name ?? ('WH#' . (int) $row->warehouse_id) }}</div>
                                                <div class="small text-muted">Rack: {{ $rackText }}</div>
                                            </td>
                                            <td>{{ $typeText }}</td>
                                            <td class="text-center">{{ (int) ($row->qty ?? 1) ?: 1 }}</td>
                                            <td>{{ $row->description ?? '-' }}</td>
                                            <td>{{ $row->moved_out_at ? \Carbon\Carbon::parse($row->moved_out_at)->format('d M Y H:i') : '-' }}</td>
                                            <td>{{ $row->moved_out_by_name ?? ($row->moved_out_by ? ('User#' . (int) $row->moved_out_by) : '-') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">No {{ strtolower($pickedTitle) }} found.</div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="card mt-3">
            <div class="card-header">
                <strong>Stock Out Log (Mutation)</strong>
            </div>
            <div class="card-body">
                @if(isset($mutations) && $mutations->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th style="width:140px;">Rack</th>
                                    <th class="text-center" style="width:90px;">Qty Out</th>
                                    <th>Note</th>
                                    <th style="width:130px;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mutations as $m)
                                    @php
                                        $rackText = '-';
                                        if (!empty($m->rack)) {
                                            $rackText = $m->rack->code
                                                ?? $m->rack->rack_code
                                                ?? $m->rack->name
                                                ?? $m->rack->rack_name
                                                ?? '-';
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $m->product?->product_name ?? ('Product#'.$m->product_id) }}</td>
                                        <td>{{ $m->warehouse?->warehouse_name ?? ('WH#'.$m->warehouse_id) }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $rackText }}</span></td>
                                        <td class="text-center">{{ (int) $m->stock_out }}</td>
                                        <td>{{ $m->note }}</td>
                                        <td>{{ $m->date ? \Carbon\Carbon::parse($m->date)->format('d M Y') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted">No mutation log found.</div>
                @endif
            </div>
        </div>
    @endif

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
