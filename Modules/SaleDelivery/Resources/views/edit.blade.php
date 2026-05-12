@extends('layouts.app')

@section('title', 'Edit Sale Delivery')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}">Details</a></li>
    <li class="breadcrumb-item active">Edit</li>
</ol>
@endsection

@section('content')
@php
    $status = strtolower((string) ($saleDelivery->status ?? 'pending'));
    $isPending = $status === 'pending';
    $fromSaleOrder = !empty($saleDelivery->sale_order_id);
    $fromWalkInSale = !$fromSaleOrder && !empty($saleDelivery->sale_id);
    $isManualDelivery = !$fromSaleOrder && !$fromWalkInSale;
    $canEditQuantities = $isPending && ($fromSaleOrder || $isManualDelivery);
    $quantityLocked = $isPending && $fromWalkInSale;
    $sourceRef = $fromSaleOrder
        ? ($saleDelivery->saleOrder?->reference ?? ('SO#' . (int) $saleDelivery->sale_order_id))
        : ($fromWalkInSale ? ($saleDelivery->sale?->reference ?? ('Sale#' . (int) $saleDelivery->sale_id)) : 'Manual');
@endphp

<div class="container-fluid">
    @include('utils.alerts')

    <div class="card">
        <div class="card-body">
            <form action="{{ route('sale-deliveries.update', $saleDelivery->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="d-flex flex-wrap align-items-start justify-content-between mb-3" style="gap:12px;">
                    <div>
                        <h5 class="mb-1">Edit Sale Delivery</h5>
                        <div class="text-muted small">
                            Reference: <strong>{{ $saleDelivery->reference }}</strong>
                            <span class="mx-1">|</span>
                            Status: <strong>{{ strtoupper($status) }}</strong>
                            <span class="mx-1">|</span>
                            Source: <strong>{{ $sourceRef }}</strong>
                        </div>
                    </div>

                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                        Cancel
                    </a>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date"
                               name="date"
                               class="form-control"
                               value="{{ old('date', $saleDelivery->date ? $saleDelivery->date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Customer</label>
                        <input type="text"
                               class="form-control"
                               value="{{ $saleDelivery->customer?->customer_name ?? '-' }}"
                               readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Source</label>
                        <input type="text" class="form-control" value="{{ $sourceRef }}" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="3" maxlength="2000">{{ old('note', $saleDelivery->note) }}</textarea>
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px;">
                    <div>
                        <h6 class="mb-0">Items</h6>
                        <div class="text-muted small">
                            Source context is locked.
                            @if($canEditQuantities)
                                Set quantity to 0 to remove a pending row.
                            @else
                                Item quantities are read-only.
                            @endif
                        </div>
                        @if($quantityLocked)
                            <div class="text-warning small mt-1">
                                Quantity is locked because this delivery was generated from a Sale/Invoice.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:260px;">Product</th>
                                <th style="min-width:260px;">Source Context</th>
                                <th class="text-end" style="width:140px;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($saleDelivery->items as $i => $item)
                                @php
                                    $sourceItem = $item->saleOrderItem ?: $item->saleItem;
                                    $sourceLabel = $item->sale_order_item_id
                                        ? ('SO Item #' . (int) $item->sale_order_item_id)
                                        : ($item->sale_item_id ? ('Sale Item #' . (int) $item->sale_item_id) : '-');
                                    $serviceType = $sourceItem?->installation_type ?? null;
                                    $sourcePrice = $sourceItem?->price ?? $item->price ?? null;
                                    $vehicle = $sourceItem?->customerVehicle ?? null;
                                    $oldBase = "items.$i";
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product?->product_name ?? ('Product #' . (int) $item->product_id) }}</div>
                                        <div class="small text-muted">
                                            Code: <strong>{{ $item->product?->product_code ?? '-' }}</strong>
                                            <span class="mx-1">|</span>
                                            product_id: <strong>{{ (int) $item->product_id }}</strong>
                                        </div>

                                        <input type="hidden" name="items[{{ $i }}][id]" value="{{ (int) $item->id }}">
                                        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ (int) $item->product_id }}">
                                        <input type="hidden" name="items[{{ $i }}][sale_order_item_id]" value="{{ $item->sale_order_item_id ? (int) $item->sale_order_item_id : '' }}">
                                        <input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->sale_item_id ? (int) $item->sale_item_id : '' }}">
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
                                    <td>
                                        @if($canEditQuantities)
                                            <input type="number"
                                                   name="items[{{ $i }}][quantity]"
                                                   class="form-control text-end"
                                                   min="0"
                                                   value="{{ (int) old($oldBase . '.quantity', $item->quantity) }}"
                                                   required>
                                        @else
                                            <input type="number"
                                                   class="form-control text-end"
                                                   value="{{ (int) $item->quantity }}"
                                                   disabled
                                                   readonly>
                                            <input type="hidden"
                                                   name="items[{{ $i }}][quantity]"
                                                   value="{{ (int) $item->quantity }}">
                                            @if($quantityLocked)
                                                <div class="small text-warning mt-1">
                                                    Quantity is locked because this delivery was generated from a Sale/Invoice.
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-right">
                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save mr-1"></i> Update Delivery
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
