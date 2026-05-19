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
        'delivered' => 'bg-secondary',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };

    $dateText = $saleOrder->date ? \Carbon\Carbon::parse($saleOrder->date)->format('d M Y') : '-';
    $hasShortage = (bool)($saleOrder->has_shortage ?? false);
    $shortageBadgeClass = $hasShortage ? 'bg-danger' : 'bg-success';
    $shortageText = $hasShortage ? 'PENDING STOCK' : 'AVAILABLE';
    $activeShortageQuantity = $saleOrder->shortage_quantity;
    $shortageQuantityText = is_null($activeShortageQuantity)
        ? ($hasShortage ? 'Not recorded' : '0 qty')
        : number_format((int) $activeShortageQuantity).' qty';
    $shortageDetectedText = $saleOrder->shortage_detected_at ? \Carbon\Carbon::parse($saleOrder->shortage_detected_at)->format('d M Y H:i') : '-';
    $shortageResolvedText = $saleOrder->shortage_resolved_at ? \Carbon\Carbon::parse($saleOrder->shortage_resolved_at)->format('d M Y H:i') : '-';

    $etaDateText = '-';
    $etaCountdownText = '-';
    $etaBadgeClass = 'bg-secondary';
    if (!empty($saleOrder->estimated_arrival_date)) {
        $etaDate = \Carbon\Carbon::parse($saleOrder->estimated_arrival_date)->startOfDay();
        $etaDateText = $etaDate->format('d M Y');
        $etaDays = now()->startOfDay()->diffInDays($etaDate, false);

        if ($etaDays < 0) {
            $etaCountdownText = 'Overdue '.abs($etaDays).' days';
            $etaBadgeClass = 'bg-danger text-dark sale-order-eta-countdown-badge';
        } elseif ($etaDays === 0) {
            $etaCountdownText = 'Due Today';
            $etaBadgeClass = 'bg-danger text-dark sale-order-eta-countdown-badge';
        } elseif ($etaDays <= 3) {
            $etaCountdownText = $etaDays.' days left';
            $etaBadgeClass = 'bg-danger text-dark sale-order-eta-countdown-badge';
        } elseif ($etaDays <= 7) {
            $etaCountdownText = $etaDays.' days left';
            $etaBadgeClass = 'bg-warning text-dark sale-order-eta-countdown-badge';
        } else {
            $etaCountdownText = $etaDays.' days left';
            $etaBadgeClass = 'bg-light text-dark border sale-order-eta-countdown-badge';
        }
    }

    $remainingConfirmedMap = $remainingMap ?? [];
    $plannedRemainingMap = $plannedRemainingMap ?? $remainingConfirmedMap;

    $hasPlannedRemaining = false;
    foreach(($plannedRemainingMap ?? []) as $rem){
        if ((int)$rem > 0) { $hasPlannedRemaining = true; break; }
    }

    $quotationId = $saleOrder->quotation_id ? (int) $saleOrder->quotation_id : null;

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

    $subtotal = (int)($saleOrder->subtotal_amount ?? 0);
    $taxAmt   = (int)($saleOrder->tax_amount ?? 0);
    $fee      = (int)($saleOrder->fee_amount ?? 0);
    $ship     = (int)($saleOrder->shipping_amount ?? 0);
    $total    = (int)($saleOrder->total_amount ?? 0);

    $storedDiscountAmount = (int)($saleOrder->discount_amount ?? 0);
    $baseGrandBeforeDiscount = $subtotal + $taxAmt + $fee + $ship;
    $appliedOrderDiscount = max(0, $baseGrandBeforeDiscount - $total);
    $legacyStoredDiscountOnly = $appliedOrderDiscount <= 0 && $storedDiscountAmount > 0;
    $dpMax    = (int)($saleOrder->deposit_amount ?? 0);
    $dpRec    = (int)($saleOrder->deposit_received_amount ?? 0);
    $remainingAfterDp = max(0, $total - $dpRec);

    $createdByName = optional($saleOrder->creator)->name ?? '-';
    $updatedByName = optional($saleOrder->updater)->name ?? '-';
    $createdAtText = $saleOrder->created_at ? \Carbon\Carbon::parse($saleOrder->created_at)->format('d M Y H:i') : '-';
    $updatedAtText = $saleOrder->updated_at ? \Carbon\Carbon::parse($saleOrder->updated_at)->format('d M Y H:i') : '-';

    $showShortagePoModal = (bool) session('show_sale_order_shortage_po_modal')
        && (int) session('shortage_sale_order_id') === (int) $saleOrder->id;
    $shortagePoReference = (string) session('shortage_sale_order_reference', $saleOrder->reference);
    $shortagePoQuantity = session('shortage_quantity');
    $purchaseOrderCreateUrl = \Illuminate\Support\Facades\Route::has('purchase-orders.create')
        ? route('purchase-orders.create', ['source' => 'sale_order', 'sale_order_id' => (int) $saleOrder->id])
        : null;
@endphp

<style>
    .sale-order-title-wrap {
        min-width: 260px;
    }

    .sale-order-status-strip {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .45rem;
    }

    .sale-order-status-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .5rem;
        border: 1px solid #d8dbe0;
        border-radius: .5rem;
        background: #f8f9fa;
        line-height: 1;
    }

    .sale-order-status-chip-label {
        color: #768192;
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sale-order-status-chip .badge {
        font-size: .68rem;
    }

    .sale-order-meta-line {
        color: #768192;
        font-size: .78rem;
    }

    .sale-order-meta-line strong {
        color: #3c4b64;
    }

    .sale-order-status-help {
        color: #768192;
        font-size: .72rem;
        margin-top: .35rem;
    }

    .sale-order-eta-countdown-badge {
        color: #111827 !important;
    }

    @media (max-width: 576px) {
        .sale-order-status-chip {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<div class="container-fluid">

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="sale-order-title-wrap">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h4 class="mb-0">{{ $saleOrder->reference }}</h4>
                    </div>

                    <div class="sale-order-status-strip">
                        <div class="sale-order-status-chip">
                            <span class="sale-order-status-chip-label">Order Status</span>
                            <span class="badge {{ $statusBadge }}">{{ strtoupper($saleOrder->status ?? 'PENDING') }}</span>
                        </div>

                        <div class="sale-order-status-chip">
                            <span class="sale-order-status-chip-label">Stock Status</span>
                            <span class="badge {{ $shortageBadgeClass }}">{{ $shortageText }}</span>
                        </div>
                    </div>

                    <div class="sale-order-status-help">
                        Order Status = progress dokumen SO. Stock Status = kondisi shortage / ketersediaan barang.
                    </div>

                    <div class="sale-order-meta-line mt-2">
                        Date: <strong>{{ $dateText }}</strong>
                        &bull; Customer: <strong>{{ $saleOrder->customer?->customer_name ?? '-' }}</strong>
                    </div>

                    <div class="sale-order-meta-line mt-1">
                        ETA: <strong>{{ $etaDateText }}</strong>
                        @if($etaDateText !== '-')
                            &bull; <span class="badge {{ $etaBadgeClass }}">{{ $etaCountdownText }}</span>
                        @endif
                    </div>

                    <div class="sale-order-meta-line mt-1">
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

                        &bull; Invoice (Sale):
                        <span class="text-muted">listed per delivery below</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end d-print-none">
                    @can('create_sale_deliveries')
                        @if($hasPlannedRemaining)
                            <a class="btn btn-primary shadow-sm"
                               href="{{ route('sale-deliveries.create', ['source'=>'sale_order', 'sale_order_id'=>$saleOrder->id]) }}">
                                <i class="bi bi-truck me-1"></i> Create Sale Delivery
                            </a>
                        @else
                            <button class="btn btn-secondary" disabled>
                                <i class="bi bi-check2-circle me-1"></i> All Items Planned
                            </button>
                        @endif
                    @endcan

                    <div class="btn-group shadow-sm" role="group" aria-label="Sale Order PDF actions">
                        <a class="btn btn-outline-secondary"
                           href="{{ route('sale-orders.print', $saleOrder->id) }}"
                           target="_blank">
                            <i class="bi bi-printer me-1"></i> Preview PDF
                        </a>
                        <a class="btn btn-outline-secondary"
                           href="{{ route('sale-orders.download', $saleOrder->id) }}">
                            <i class="bi bi-download me-1"></i> Download PDF
                        </a>
                    </div>

                    @can('edit_sale_orders')
                        @if($status === 'pending')
                            <a href="{{ route('sale-orders.edit', $saleOrder->id) }}" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </a>
                        @endif
                    @endcan

                    @can('delete_sale_orders')
                        @if($status === 'pending')
                            <form action="{{ route('sale-orders.destroy', $saleOrder->id) }}" method="POST"
                                  class="m-0"
                                  data-confirm-submit="true" data-confirm-title="Confirm Delete" data-confirm-message="Delete this Sale Order? This cannot be undone." data-confirm-button="Delete" data-confirm-variant="danger">
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

            @if(!empty($saleOrder->note))
                <hr class="my-3">
                <div class="p-3 border rounded-3 bg-light">
                    <div class="text-muted small">Note</div>
                    <div>{{ $saleOrder->note }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3">Tracking & Audit Information</h6>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="text-muted small">Stock Status</div>
                    <div>
                        <span class="badge {{ $shortageBadgeClass }}">{{ $shortageText }}</span>
                        <div class="text-muted small mt-1">
                            {{ $hasShortage ? 'Needs purchasing / incoming stock follow-up.' : 'No shortage flag for this Sale Order.' }}
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="text-muted small">Shortage Detected At</div>
                    <div class="fw-semibold">{{ $shortageDetectedText }}</div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="text-muted small">{{ $hasShortage ? 'Current Shortage Qty' : 'Active Shortage Qty' }}</div>
                    <div class="fw-semibold">
                        @if($hasShortage)
                            <span class="badge bg-danger">{{ $shortageQuantityText }}</span>
                        @else
                            <span class="badge bg-secondary">{{ $shortageQuantityText }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="text-muted small">Shortage Resolved At</div>
                    <div class="fw-semibold">{{ $shortageResolvedText }}</div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="text-muted small">Estimated Arrival</div>
                    <div class="fw-semibold">
                        {{ $etaDateText }}
                        @if($etaDateText !== '-')
                            <span class="badge {{ $etaBadgeClass }}">{{ $etaCountdownText }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted small">Created By</div>
                    <div class="fw-semibold">{{ $createdByName }}</div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted small">Created At</div>
                    <div class="fw-semibold">{{ $createdAtText }}</div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted small">Last Updated By</div>
                    <div class="fw-semibold">{{ $updatedByName }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Last Updated At</div>
                    <div class="fw-semibold">{{ $updatedAtText }}</div>
                </div>
            </div>
        </div>
    </div>

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
                        @if($appliedOrderDiscount > 0)
                            <tr>
                                <td>
                                    Header Discount
                                    @if((float)($saleOrder->discount_percentage ?? 0) > 0)
                                        <div class="text-muted small">{{ number_format((float)($saleOrder->discount_percentage ?? 0), 2) }}%</div>
                                    @endif
                                </td>
                                <td class="text-end">- {{ format_currency($appliedOrderDiscount) }}</td>
                            </tr>
                        @endif
                        <tr><td><strong>Grand Total</strong></td><td class="text-end"><strong>{{ format_currency($total) }}</strong></td></tr>
                    </table>
                    <div class="text-muted small">
                        Item discount sudah tercermin di subtotal per baris.
                        Header discount, bila ada, hanya mengurangi Grand Total.
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-muted small">Header Discount Applied</div>
                                <div class="fw-semibold">{{ format_currency($appliedOrderDiscount) }}</div>
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
                            @if($legacyStoredDiscountOnly)
                                <div class="col-md-12 mt-2">
                                    <div class="text-muted small">Legacy Stored Discount Value</div>
                                    <div class="fw-semibold">{{ format_currency($storedDiscountAmount) }}</div>
                                </div>
                            @endif
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
                    Delivered = confirmed/partial deliveries &bull; Planned = pending+confirmed+partial
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width:120px;">Ordered</th>
                            <th class="text-end" style="width:150px;">Sellable at Order</th>
                            <th class="text-end" style="width:140px;">Shortage at Order</th>
                            <th style="width:180px;">Service Type</th>
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
                            $sellableAtOrder = $it->sellable_stock_at_order;
                            $itemShortageQty = $it->shortage_quantity;
                            $unitPrice = (int) ($it->unit_price ?? ($it->price ?? 0));
                            $netPrice = (int) ($it->price ?? $unitPrice);
                            $itemDiscount = (int) ($it->product_discount_amount ?? max(0, $unitPrice - $netPrice));
                            $lineSubtotal = (int) ($it->sub_total ?? ($ordered * $netPrice));

                            $remConfirmed = isset($remainingByItem) && isset($remainingByItem[$it->id])
                                ? (int) $remainingByItem[$it->id]
                                : (int) ($remainingConfirmedMap[$pid] ?? 0);
                            $delivered = max(0, $ordered - $remConfirmed);

                            $remPlanned = isset($plannedRemainingByItem) && isset($plannedRemainingByItem[$it->id])
                                ? (int) $plannedRemainingByItem[$it->id]
                                : (int) ($plannedRemainingMap[$pid] ?? 0);
                            $plannedCovered = max(0, $ordered - $remPlanned);

                            $deliveredPct = $ordered > 0 ? (int) round(($delivered / $ordered) * 100) : 0;
                            $plannedPct = $ordered > 0 ? (int) round(($plannedCovered / $ordered) * 100) : 0;

                            if ($deliveredPct > 100) $deliveredPct = 100;
                            if ($plannedPct > 100) $plannedPct = 100;

                            $pName = $it->product?->product_name ?? ('Product ID '.$pid);
                            $pCode = $it->product?->product_code ?? null;
                            $installationType = (string) ($it->installation_type ?? 'item_only') === 'with_installation' ? 'with_installation' : 'item_only';
                            $vehicle = $it->customerVehicle ?? null;
                            $vehicleLabel = '-';
                            if ($vehicle) {
                                $vehicleLabel = trim((string) $vehicle->car_plate);
                                if (!empty($vehicle->vehicle_name)) {
                                    $vehicleLabel .= ' / ' . $vehicle->vehicle_name;
                                }
                            }
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
                                <div class="text-muted small mt-1">
                                    Unit: <strong>{{ format_currency($unitPrice) }}</strong>
                                    &bull; Net: <strong>{{ format_currency($netPrice) }}</strong>
                                    &bull; Item Discount: <strong>{{ format_currency($itemDiscount) }}</strong>
                                    &bull; Subtotal: <strong>{{ format_currency($lineSubtotal) }}</strong>
                                </div>
                            </td>

                            <td class="text-end">
                                <span class="badge bg-secondary">{{ number_format($ordered) }}</span>
                            </td>

                            <td class="text-end">
                                @if(is_null($sellableAtOrder))
                                    <span class="badge bg-light text-dark border">Not recorded</span>
                                @else
                                    <span class="badge bg-light text-dark border">{{ number_format((int) $sellableAtOrder) }}</span>
                                @endif
                            </td>

                            <td class="text-end">
                                @if(is_null($itemShortageQty))
                                    <span class="badge bg-light text-dark border">Not recorded</span>
                                @elseif((int) $itemShortageQty > 0)
                                    <span class="badge bg-danger">{{ number_format((int) $itemShortageQty) }}</span>
                                @else
                                    <span class="badge bg-success">0</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge {{ $installationType === 'with_installation' ? 'bg-secondary text-dark' : 'bg-light text-dark border' }}">
                                    {{ $installationType === 'with_installation' ? 'With Installation' : 'Item Only' }}
                                </span>
                                <div class="text-muted small mt-1">
                                    Vehicle: <strong>{{ $installationType === 'with_installation' ? $vehicleLabel : '-' }}</strong>
                                </div>
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
                                <div class="small text-muted mb-1">Delivered: {{ $deliveredPct }}% &bull; Planned: {{ $plannedPct }}%</div>
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
                            <th style="width:170px;">Invoice</th>
                            <th class="text-center" style="width:260px;">Action</th>
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

                            $hasInvoice = !empty($d->sale_id);
                            $invBadge = $hasInvoice ? 'bg-success' : 'bg-secondary';

                            $invUrl = null;
                            if ($hasInvoice) {
                                if (\Illuminate\Support\Facades\Route::has('sales.show')) {
                                    $invUrl = route('sales.show', (int)$d->sale_id);
                                } elseif (\Illuminate\Support\Facades\Route::has('sale.show')) {
                                    $invUrl = route('sale.show', (int)$d->sale_id);
                                } elseif (\Illuminate\Support\Facades\Route::has('sale-invoices.show')) {
                                    $invUrl = route('sale-invoices.show', (int)$d->sale_id);
                                }
                            }
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $d->reference }}</td>
                            <td>{{ $d->date ? \Carbon\Carbon::parse($d->date)->format('d M Y') : '-' }}</td>
                            <td><span class="badge {{ $dBadge }}">{{ strtoupper($dst) }}</span></td>

                            <td>
                                @if($hasInvoice)
                                    @if($invUrl)
                                        <a href="{{ $invUrl }}" class="text-decoration-none">
                                            <span class="badge {{ $invBadge }}">INVOICED #{{ (int)$d->sale_id }}</span>
                                        </a>
                                    @else
                                        <span class="badge {{ $invBadge }}">INVOICED #{{ (int)$d->sale_id }}</span>
                                    @endif
                                @else
                                    <span class="badge {{ $invBadge }}">NOT YET</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <div class="d-flex flex-wrap justify-content-center gap-1">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('sale-deliveries.show', $d->id) }}">
                                        <i class="bi bi-eye"></i> View Delivery
                                    </a>

                                    @can('confirm_sale_deliveries')
                                        @if($dst === 'pending')
                                            <a class="btn btn-sm btn-outline-success"
                                               href="{{ route('sale-deliveries.confirm.form', $d->id) }}">
                                                <i class="bi bi-check2-circle"></i> Confirm Delivery
                                            </a>
                                        @endif
                                    @endcan

                                    @if($hasInvoice && $invUrl)
                                        <a class="btn btn-sm btn-outline-success" href="{{ $invUrl }}">
                                            <i class="bi bi-box-arrow-up-right"></i> View Invoice
                                        </a>
                                    @elseif($hasInvoice)
                                        <span class="badge bg-success align-self-center">Invoice #{{ (int)$d->sale_id }}</span>
                                    @else
                                        @can('create_sales')
                                            @if($dst === 'confirmed')
                                                <form method="POST"
                                                      action="{{ route('sale-deliveries.create-invoice', $d->id) }}"
                                                      class="d-inline"
                                                      data-confirm-submit="true" data-confirm-title="Confirm Create" data-confirm-message="Create Invoice from this Sale Delivery? (1 Delivery = 1 Invoice)" data-confirm-button="Create" data-confirm-variant="primary">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-receipt"></i> Create Invoice
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    @endif
                                </div>
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

    @if($showShortagePoModal)
        <div class="modal fade" id="saleOrderShortagePoModal" tabindex="-1" role="dialog" aria-labelledby="saleOrderShortagePoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="saleOrderShortagePoModalLabel">Pending Stock Detected</h5>
                        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" data-coreui-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            Sale Order <strong>{{ $shortagePoReference }}</strong> has pending stock / shortage.
                        </p>
                        <p class="mb-2">
                            Current shortage:
                            <strong>
                                @if(is_null($shortagePoQuantity))
                                    Not recorded
                                @else
                                    {{ number_format((int) $shortagePoQuantity) }} qty
                                @endif
                            </strong>
                        </p>
                        <p class="mb-0 text-muted">
                            You may create a Purchase Order to fulfill the required items.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal" data-coreui-dismiss="modal">Close</button>
                        @if($purchaseOrderCreateUrl)
                            <a href="{{ $purchaseOrderCreateUrl }}" class="btn btn-primary">
                                <i class="bi bi-journal-plus me-1"></i> Create Purchase Order
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

@include('includes.edit-activity-log', ['model' => $saleOrder])
@endsection

@if($showShortagePoModal)
    @push('page_scripts')
        <script>
        (function () {
            function openModal(modalId) {
                var modalEl = document.getElementById(modalId);
                if (!modalEl) return false;

                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        return true;
                    }
                } catch (e) {}

                try {
                    if (typeof coreui !== 'undefined' && coreui.Modal) {
                        coreui.Modal.getOrCreateInstance(modalEl).show();
                        return true;
                    }
                } catch (e) {}

                try {
                    if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
                        jQuery(modalEl).modal('show');
                        return true;
                    }
                } catch (e) {}

                return false;
            }

            document.addEventListener('DOMContentLoaded', function () {
                openModal('saleOrderShortagePoModal');
            });
        })();
        </script>
    @endpush
@endif
