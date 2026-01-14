@extends('layouts.app')

@section('title', 'Purchase Order Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@push('page_css')
<style>
    /* === Modern PO Detail Shell (match gaya PD modern kamu) === */
    .po-wrap .po-title{
        font-weight:900;
        font-size:18px;
        margin:0;
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    }
    .po-wrap .po-sub{
        font-size:12px;
        color:#6c757d;
        margin:4px 0 0;
    }
    .po-card{
        border:1px solid rgba(0,0,0,.06);
        border-radius:14px;
        box-shadow:0 6px 18px rgba(0,0,0,.04);
        overflow:hidden;
        background:#fff;
    }
    .po-card-header{
        background:#fff;
        padding:14px 16px;
        border-bottom:1px solid rgba(0,0,0,.06);
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
    }
    .po-actions{
        display:flex;
        gap:8px;
        align-items:center;
        flex-wrap:wrap;
        justify-content:flex-end;
    }
    .po-btn{
        border-radius:999px;
        font-weight:800;
        font-size:12px;
        padding:7px 12px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        line-height:1;
        border:1px solid rgba(0,0,0,.08);
        background:#fff;
        color:#212529;
        text-decoration:none;
        white-space:nowrap;
    }
    .po-btn:hover{ background:rgba(0,0,0,.02); text-decoration:none; }

    .po-btn--primary{
        background:#0d6efd;
        border-color:#0d6efd;
        color:#fff;
    }
    .po-btn--primary:hover{ background:#0b5ed7; border-color:#0b5ed7; color:#fff; }

    .po-btn--success{
        background:#16a34a;
        border-color:#16a34a;
        color:#fff;
    }
    .po-btn--success:hover{ background:#15803d; border-color:#15803d; color:#fff; }

    .po-btn--ghost{
        background:#fff;
        color:#1d4ed8;
        border:1px solid #dbeafe;
    }
    .po-btn--ghost:hover{ background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }

    .po-pill{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:6px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:900;
        border:1px solid rgba(0,0,0,.08);
        background:rgba(0,0,0,.02);
        color:#111827;
        white-space:nowrap;
    }
    .po-pill--info{ background:rgba(14,165,233,.10); border-color:rgba(14,165,233,.22); color:#0369a1; }
    .po-pill--warn{ background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.22); color:#92400e; }
    .po-pill--ok{ background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.22); color:#166534; }
    .po-pill--muted{ background:rgba(0,0,0,.03); border-color:rgba(0,0,0,.08); color:#334155; }

    .po-box{
        border:1px solid rgba(0,0,0,.08);
        border-radius:12px;
        padding:14px;
        background:#fff;
        height:100%;
    }
    .po-label{
        font-size:12px;
        color:#6c757d;
        margin-bottom:4px;
    }
    .po-value{
        font-weight:800;
        color:#111827;
        word-break:break-word;
    }
    .po-value-muted{
        font-weight:600;
        color:#374151;
        white-space:pre-line;
    }

    .po-table thead th{
        background:rgba(0,0,0,.02);
        border-bottom:1px solid rgba(0,0,0,.08);
        font-weight:900;
        color:#343a40;
        font-size:13px;
        white-space:nowrap;
    }
    .po-table tbody td{
        vertical-align:middle;
        font-size:13px;
    }
    .po-code{
        display:inline-flex;
        padding:3px 8px;
        border-radius:999px;
        font-size:11px;
        font-weight:900;
        border:1px solid rgba(25,135,84,.25);
        background:rgba(25,135,84,.10);
        color:#146c43;
    }

    .po-section-title{
        font-weight:900;
        margin:0 0 10px;
        font-size:14px;
    }
    .po-list a{
        text-decoration:none;
    }
    .po-list a:hover{
        text-decoration:underline;
    }
</style>
@endpush

@section('content')
@php
    $status = (string) ($purchase_order->status ?? '');
    $statusLower = strtolower($status);

    $statusPill = 'po-pill po-pill--muted';
    if ($statusLower === 'pending') $statusPill = 'po-pill po-pill--info';
    if ($statusLower === 'partial' || $statusLower === 'partially sent') $statusPill = 'po-pill po-pill--warn';
    if ($statusLower === 'completed') $statusPill = 'po-pill po-pill--ok';

    $poDateRaw = $purchase_order->getRawOriginal('date') ?? null;
    $poDateText = $poDateRaw ? \Carbon\Carbon::parse($poDateRaw)->format('d M Y') : \Carbon\Carbon::now()->format('d M Y');

    $canAction = (($purchase_order->status ?? null) !== 'Completed');

    $totalFulfilledQty = (int) ($totalFulfilledQty ?? 0);
    $totalOrderedQty   = (int) ($totalOrderedQty ?? 0);
    $totalRemainingQty = (int) ($totalRemainingQty ?? 0);

    // progress % simple (hindari bagi 0)
    $progress = 0;
    if ($totalOrderedQty > 0) {
        $progress = (int) floor(($totalFulfilledQty / $totalOrderedQty) * 100);
        if ($progress < 0) $progress = 0;
        if ($progress > 100) $progress = 100;
    }
@endphp

<div class="container-fluid po-wrap">

    <div class="po-card mb-3">
        <div class="po-card-header">
            <div>
                <div class="po-title">
                    <span>Purchase Order:</span>
                    <span>{{ $purchase_order->reference }}</span>
                    <span class="{{ $statusPill }}">{{ $purchase_order->status ?? '-' }}</span>

                    <span class="po-pill po-pill--muted">
                        Fulfilled / Ordered / Remaining:
                        <span style="font-weight:900;">{{ $totalFulfilledQty }}</span>
                        /
                        <span style="font-weight:900;">{{ $totalOrderedQty }}</span>
                        /
                        <span style="font-weight:900;">{{ $totalRemainingQty }}</span>
                    </span>

                    <span class="po-pill po-pill--muted">
                        Progress: <span style="font-weight:900;">{{ $progress }}%</span>
                    </span>
                </div>

                <div class="po-sub">
                    Date: <strong>{{ $poDateText }}</strong>
                    • Created by: <strong>{{ optional($purchase_order->creator)->name ?? '-' }}</strong>
                    • Branch: <strong>{{ $purchase_order->branch?->name ?? '-' }}</strong>
                </div>
            </div>

            <div class="po-actions">
                {{-- Primary actions --}}
                @if($canAction)
                    <a href="{{ route('purchase-orders.deliveries.create', $purchase_order) }}"
                       class="po-btn po-btn--success d-print-none">
                        <i class="bi bi-truck"></i> Create Delivery
                    </a>

                    <a href="{{ route('purchase-order-purchases.create', $purchase_order->id) }}"
                       class="po-btn po-btn--primary d-print-none">
                        <i class="bi bi-arrow-right-circle"></i> Convert to Purchase
                    </a>
                @endif

                {{-- Secondary actions --}}
                <a target="_blank"
                   class="po-btn po-btn--ghost d-print-none"
                   href="{{ route('purchase-orders.pdf', $purchase_order->id) }}">
                    <i class="bi bi-printer"></i> Print
                </a>

                <a target="_blank"
                   class="po-btn po-btn--ghost d-print-none"
                   href="{{ route('purchase-orders.pdf', $purchase_order->id) }}">
                    <i class="bi bi-save"></i> Save
                </a>
            </div>
        </div>

        <div class="card-body">
            @include('utils.alerts')

            {{-- INFO GRID --}}
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="po-box">
                        <div class="po-section-title">Company Info</div>
                        <div class="po-label">Branch</div>
                        <div class="po-value">{{ $purchase_order->branch?->name ?? '-' }}</div>

                        <div class="mt-3 po-label">Address</div>
                        <div class="po-value-muted">{{ $purchase_order->branch?->address ?? '-' }}</div>

                        <div class="mt-3 po-label">Phone</div>
                        <div class="po-value-muted">{{ $purchase_order->branch?->phone ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="po-box">
                        <div class="po-section-title">Supplier Info</div>
                        <div class="po-label">Supplier</div>
                        <div class="po-value">{{ $supplier->supplier_name ?? '-' }}</div>

                        <div class="mt-3 po-label">Address</div>
                        <div class="po-value-muted">{{ $supplier->address ?? '-' }}</div>

                        <div class="mt-3 po-label">Email</div>
                        <div class="po-value-muted">{{ $supplier->supplier_email ?? '-' }}</div>

                        <div class="mt-3 po-label">Phone</div>
                        <div class="po-value-muted">{{ $supplier->supplier_phone ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="po-box">
                        <div class="po-section-title">Invoice Info</div>

                        <div class="po-label">Invoice</div>
                        <div class="po-value">INV/{{ $purchase_order->reference }}</div>

                        <div class="mt-3 po-label">Date</div>
                        <div class="po-value-muted">{{ $poDateText }}</div>

                        <div class="mt-3 po-label">Status</div>
                        <div class="po-value">
                            <span class="{{ $statusPill }}">{{ $purchase_order->status ?? '-' }}</span>
                        </div>

                        <div class="mt-3 po-label">Payment Status</div>
                        <div class="po-value">
                            <span class="po-pill po-pill--warn">{{ $purchase_order->payment_status ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ITEMS --}}
            <div class="mt-4">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="po-section-title mb-0">Items</div>
                    <div class="po-sub m-0">Total items: {{ $purchase_order->purchaseOrderDetails?->count() ?? 0 }}</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered po-table mb-0">
                        <thead>
                        <tr>
                            <th style="min-width:320px;">Product</th>
                            <th>Unit Price</th>
                            <th class="text-center" style="width:120px;">Ordered Qty</th>
                            <th class="text-center" style="width:120px;">Fulfilled Qty</th>
                            <th class="text-center" style="width:120px;">Remaining Qty</th>
                            <th>Discount</th>
                            <th>Tax</th>
                            <th>Sub Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($purchase_order->purchaseOrderDetails as $item)
                            @php
                                $ordered = (int) ($item->quantity ?? 0);
                                $fulfilled = (int) ($item->fulfilled_quantity ?? 0);
                                $remaining = max(0, $ordered - $fulfilled);
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight:900;">{{ $item->product_name }}</div>
                                    <span class="po-code">{{ $item->product_code }}</span>
                                </td>
                                <td>{{ format_currency($item->unit_price) }}</td>
                                <td class="text-center">{{ $ordered }}</td>
                                <td class="text-center">
                                    <span class="po-pill po-pill--ok" style="padding:4px 10px;">{{ $fulfilled }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="po-pill {{ $remaining > 0 ? 'po-pill--warn' : 'po-pill--ok' }}" style="padding:4px 10px;">
                                        {{ $remaining }}
                                    </span>
                                </td>
                                <td>{{ format_currency($item->product_discount_amount) }}</td>
                                <td>{{ format_currency($item->product_tax_amount) }}</td>
                                <td>{{ format_currency($item->sub_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No items found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TOTALS --}}
            <div class="row mt-4">
                <div class="col-lg-4 col-sm-5 ml-md-auto">
                    <div class="po-box">
                        <div class="po-section-title">Totals</div>
                        <table class="table mb-0">
                            <tbody>
                            <tr>
                                <td><strong>Discount ({{ $purchase_order->discount_percentage }}%)</strong></td>
                                <td class="text-right">{{ format_currency($purchase_order->discount_amount) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Tax ({{ $purchase_order->tax_percentage }}%)</strong></td>
                                <td class="text-right">{{ format_currency($purchase_order->tax_amount) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Shipping</strong></td>
                                <td class="text-right">{{ format_currency($purchase_order->shipping_amount) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Grand Total</strong></td>
                                <td class="text-right"><strong>{{ format_currency($purchase_order->total_amount) }}</strong></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- RELATED --}}
            <div class="row mt-4">
                @if($purchase_order->purchases->isNotEmpty())
                    <div class="col-lg-12">
                        <div class="po-box">
                            <div class="po-section-title">Related Purchases</div>
                            <ul class="mb-0 po-list">
                                @foreach($purchase_order->purchases as $purchase)
                                    <li>
                                        <a href="{{ route('purchases.show', $purchase->id) }}" class="text-primary">
                                            {{ $purchase->reference ?? 'Purchase #' . $purchase->id }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($purchase_order->purchaseDeliveries->isNotEmpty())
                    <div class="col-lg-12 mt-3">
                        <div class="po-box">
                            <div class="po-section-title">Related Deliveries</div>
                            <ul class="mb-0 po-list">
                                @foreach($purchase_order->purchaseDeliveries as $delivery)
                                    <li>
                                        <a href="{{ route('purchase-deliveries.show', $delivery->id) }}" class="text-primary">
                                            {{ $delivery->date }} - Status: {{ $delivery->status }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection
