@extends('layouts.app')

@section('title', 'Edit Sale Delivery')

@push('page_css')
<style>
    .sd-header{
        display:flex;align-items:center;justify-content:space-between;gap:12px;
        padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);
    }
    .sd-title{margin:0;font-weight:700;font-size:16px;}
    .sd-sub{margin:2px 0 0;font-size:12px;color:#6c757d;}
    .sd-badge{
        display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
        font-size:12px;background:rgba(13,110,253,.08);color:#0d6efd;border:1px solid rgba(13,110,253,.18);
        white-space:nowrap;
    }
    .sd-table thead th{white-space:nowrap;}
    .sd-mini{font-size:12px;color:#6c757d;}
</style>
@endpush

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}">Details</a>
    </li>
    <li class="breadcrumb-item active">Edit</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
<form action="{{ route('sale-deliveries.update', $saleDelivery->id) }}" method="POST">
@csrf
@method('PUT')

<div class="card">
    {{-- HEADER --}}
    <div class="sd-header">
        <div>
            <h3 class="sd-title">Edit Sale Delivery</h3>
            <p class="sd-sub mb-0">
                <span class="sd-badge">
                    <i class="bi bi-truck"></i> {{ $saleDelivery->reference }}
                </span>
                @if($saleDelivery->saleOrder)
                    <span class="sd-badge">
                        <i class="bi bi-file-earmark-text"></i>
                        SO: {{ $saleDelivery->saleOrder->reference ?? ('SO#'.$saleDelivery->sale_order_id) }}
                    </span>
                @endif
                <span class="sd-badge">
                    <i class="bi bi-person"></i> {{ $saleDelivery->customer?->customer_name ?? '-' }}
                </span>
            </p>
        </div>

        <div>
            <span class="sd-badge">
                <i class="bi bi-building"></i>
                {{ $saleDelivery->branch?->name ?? 'Branch' }}
            </span>
        </div>
    </div>

    {{-- BODY --}}
    <div class="card-body">
        @include('utils.alerts')

        {{-- TOP INFO --}}
        <div class="row">
            <div class="col-md-4">
                <label class="mb-1">Customer</label>
                <input type="text" class="form-control"
                       value="{{ $saleDelivery->customer?->customer_name ?? '-' }}" readonly>
            </div>

            <div class="col-md-4">
                <label class="mb-1">Reference</label>
                <input type="text" class="form-control"
                       value="{{ $saleDelivery->reference }}" readonly>
            </div>

            <div class="col-md-4">
                <label class="mb-1">Status</label>
                <input type="text" class="form-control"
                       value="{{ ucfirst($saleDelivery->status) }}" readonly>
                <small class="text-muted">Only pending delivery can be edited</small>
            </div>
        </div>

        {{-- DATE & WAREHOUSE --}}
        <div class="row mt-3">
            <div class="col-md-4">
                <label class="mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date"
                       value="{{ old('date', $saleDelivery->date?->format('Y-m-d')) }}" required>
            </div>

            <div class="col-md-4">
                <label class="mb-1">Warehouse (Stock Out) <span class="text-danger">*</span></label>
                <select name="warehouse_id" class="form-control" required>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}"
                            {{ (int) old('warehouse_id', $saleDelivery->warehouse_id) === (int) $wh->id ? 'selected' : '' }}>
                            {{ $wh->warehouse_name }} {{ $wh->is_main ? '(Main)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ITEMS (READONLY) --}}
        <div class="d-flex align-items-center justify-content-between mt-4">
            <h5 class="mb-0">Items in this Delivery</h5>
            <div class="sd-mini">
                Qty & items cannot be edited here
            </div>
        </div>

        <div class="table-responsive mt-2">
            <table class="table table-striped sd-table">
                <thead>
                    <tr>
                        <th style="min-width:260px;">Product</th>
                        <th style="width:160px;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($saleDelivery->items as $item)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $item->product?->product_name ?? '-' }}</div>
                            <span class="badge bg-secondary">{{ $item->product?->product_code ?? '-' }}</span>
                        </td>
                        <td>
                            <input type="number" class="form-control"
                                   value="{{ (int) $item->quantity }}" readonly>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- NOTE --}}
        <div class="row mt-3">
            <div class="col-md-6">
                <label class="mb-1">Note</label>
                <textarea name="note" class="form-control" rows="3"
                          maxlength="2000">{{ old('note', $saleDelivery->note) }}</textarea>
            </div>
        </div>

        {{-- ACTION --}}
        <div class="mt-4 text-right">
            <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>
</form>
</div>
@endsection
