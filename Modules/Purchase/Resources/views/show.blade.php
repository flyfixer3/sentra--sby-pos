@extends('layouts.app')

@section('title', 'Purchases Details')

@push('page_css')
<style>
    .pd-wrap .pd-title{
        font-weight:800;
        font-size:18px;
        margin:0;
        display:flex;
        gap:10px;
        align-items:center;
        flex-wrap:wrap;
    }
    .pd-wrap .pd-sub{
        font-size:12px;
        color:#6c757d;
        margin:2px 0 0;
    }
    .pd-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:5px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        border:1px solid rgba(0,0,0,.08);
        background:rgba(0,0,0,.03);
        color:#343a40;
        white-space:nowrap;
        text-transform:uppercase;
    }
    .pd-badge--paid{ background:rgba(25,135,84,.10); color:#146c43; border-color:rgba(25,135,84,.20); }
    .pd-badge--partial{ background:rgba(255,193,7,.14); color:#7a5d00; border-color:rgba(255,193,7,.25); }
    .pd-badge--unpaid{ background:rgba(220,53,69,.10); color:#b02a37; border-color:rgba(220,53,69,.22); }

    .pd-actions .btn{
        border-radius:10px;
        font-weight:700;
        padding:6px 10px;
    }

    .info-card{
        border:1px solid rgba(0,0,0,.06);
        border-radius:14px;
        padding:12px 14px;
        height:100%;
        background:#fff;
    }
    .info-card .info-title{
        font-weight:800;
        font-size:13px;
        margin:0 0 10px 0;
        padding-bottom:8px;
        border-bottom:1px solid rgba(0,0,0,.06);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
    }
    .info-row{
        display:flex;
        justify-content:space-between;
        gap:12px;
        margin-bottom:6px;
        font-size:13px;
    }
    .info-row .k{ color:#6c757d; }
    .info-row .v{ font-weight:700; text-align:right; word-break:break-word; }

    .mini-delivery{
        border:1px solid rgba(0,0,0,.06);
        border-radius:12px;
        padding:8px 10px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        background:#fff;
    }
    .mini-delivery a{ font-weight:800; text-decoration:none; }
    .mini-delivery a:hover{ text-decoration:underline; }

    .item-code{
        display:inline-flex;
        padding:3px 8px;
        border-radius:999px;
        font-size:11px;
        font-weight:800;
        border:1px solid rgba(25,135,84,.25);
        background:rgba(25,135,84,.10);
        color:#146c43;
    }
    .table thead th{
        background:rgba(0,0,0,.02);
        font-weight:800;
        font-size:12px;
        color:#343a40;
        border-bottom:1px solid rgba(0,0,0,.06);
        white-space:nowrap;
    }

    .totals-wrap{
        border:1px solid rgba(0,0,0,.06);
        border-radius:14px;
        overflow:hidden;
        background:#fff;
    }
    .totals-row{
        display:flex;
        justify-content:space-between;
        padding:10px 12px;
        border-bottom:1px solid rgba(0,0,0,.06);
        font-size:13px;
        gap:10px;
    }
    .totals-row:last-child{ border-bottom:none; }
    .totals-row .label{ font-weight:800; color:#343a40; }
    .totals-row .value{ font-weight:800; }
    .totals-row.grand{ background:rgba(0,0,0,.02); }

    /* keep existing spacing system */
    .card{
        border:1px solid rgba(0,0,0,.06);
        border-radius:14px;
        box-shadow:0 6px 18px rgba(0,0,0,.04);
    }
</style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
@php
    $paymentStatus = strtolower(trim((string)($purchase->payment_status ?? '')));
    $paymentBadge = 'pd-badge';
    if ($paymentStatus === 'paid') $paymentBadge = 'pd-badge pd-badge--paid';
    elseif ($paymentStatus === 'partial') $paymentBadge = 'pd-badge pd-badge--partial';
    elseif ($paymentStatus === 'unpaid') $paymentBadge = 'pd-badge pd-badge--unpaid';

    $pdBadgeClass = function ($status) {
        $s = strtolower(trim((string) $status));
        if ($s === 'received') return 'badge bg-success';
        if ($s === 'partial')  return 'badge bg-info';
        if ($s === 'pending' || $s === 'open') return 'badge bg-warning text-dark';
        return 'badge bg-secondary';
    };

    $dateText = !empty($purchase->date) ? \Carbon\Carbon::parse($purchase->date)->format('d M, Y') : '-';
    $dueDateText = !empty($purchase->due_date) ? \Carbon\Carbon::parse($purchase->due_date)->format('d M, Y') : null;
@endphp

<div class="container-fluid pd-wrap">
    <div class="row">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <div class="pd-title">
                            Purchase Invoice
                            <span class="{{ $paymentBadge }}">{{ $purchase->payment_status ?? '-' }}</span>
                        </div>
                        <div class="pd-sub">
                            Reference: <strong>{{ $purchase->reference }}</strong>
                            • Invoice: <strong>INV/{{ $purchase->reference }}</strong>
                            • Date: <strong>{{ $dateText }}</strong>
                        </div>
                    </div>

                    <div class="pd-actions d-flex align-items-center gap-2 ms-auto">
                        <a target="_blank"
                           class="btn btn-sm btn-secondary mfe-1 d-print-none"
                           href="{{ route('purchases.pdf', $purchase->id) }}">
                            <i class="bi bi-printer"></i> Print
                        </a>
                        <a target="_blank"
                           class="btn btn-sm btn-info d-print-none"
                           href="{{ route('purchases.pdf', $purchase->id) }}">
                            <i class="bi bi-save"></i> Save
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    {{-- TOP INFO (same feel as PD pages: 3 columns, boxed) --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="info-card">
                                <div class="info-title">
                                    <span>Company Info</span>
                                </div>
                                <div class="info-row">
                                    <div class="k">Name</div>
                                    <div class="v">{{ $company['name'] ?? '-' }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Address</div>
                                    <div class="v" style="font-weight:600; white-space:pre-line;">{{ $company['address'] ?? '-' }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Email</div>
                                    <div class="v" style="font-weight:700;">{{ $company['email'] ?? '-' }}</div>
                                </div>
                                <div class="info-row mb-0">
                                    <div class="k">Phone</div>
                                    <div class="v" style="font-weight:700;">{{ $company['phone'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="info-card">
                                <div class="info-title">
                                    <span>Supplier Info</span>
                                </div>
                                <div class="info-row">
                                    <div class="k">Name</div>
                                    <div class="v">{{ $supplier->supplier_name ?? '-' }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Address</div>
                                    <div class="v" style="font-weight:600; white-space:pre-line;">{{ $supplier->address ?: '-' }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Email</div>
                                    <div class="v" style="font-weight:700;">{{ $supplier->supplier_email ?: '-' }}</div>
                                </div>
                                <div class="info-row mb-0">
                                    <div class="k">Phone</div>
                                    <div class="v" style="font-weight:700;">{{ $supplier->supplier_phone ?: '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="info-card">
                                <div class="info-title">
                                    <span>Invoice Info</span>
                                </div>

                                <div class="info-row">
                                    <div class="k">Invoice</div>
                                    <div class="v">INV/{{ $purchase->reference }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Date</div>
                                    <div class="v" style="font-weight:700;">{{ $dateText }}</div>
                                </div>
                                @if($dueDateText)
                                    <div class="info-row">
                                        <div class="k">Due Date</div>
                                        <div class="v" style="font-weight:700;">{{ $dueDateText }}</div>
                                    </div>
                                @endif

                                <div class="info-row">
                                    <div class="k">Supplier Invoice</div>
                                    <div class="v" style="font-weight:700;">{{ $purchase->reference_supplier ?: '-' }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Payment Method</div>
                                    <div class="v" style="font-weight:700;">{{ $purchase->payment_method ?: '-' }}</div>
                                </div>

                                <div class="info-row">
                                    <div class="k">Paid</div>
                                    <div class="v">{{ format_currency($purchase->paid_amount ?? 0) }}</div>
                                </div>
                                <div class="info-row">
                                    <div class="k">Due</div>
                                    <div class="v">{{ format_currency($purchase->due_amount ?? 0) }}</div>
                                </div>

                                <div class="mt-2">
                                    <div class="text-muted" style="font-size:12px; font-weight:800;">Related Deliveries</div>

                                    @if(isset($relatedDeliveries) && $relatedDeliveries->isNotEmpty())
                                        <div class="d-flex flex-column mt-1" style="gap:6px;">
                                            @foreach($relatedDeliveries as $pd)
                                                <div class="mini-delivery">
                                                    <div class="d-flex flex-column">
                                                        <a href="{{ route('purchase-deliveries.show', $pd->id) }}">
                                                            PD-{{ str_pad((int)$pd->id, 5, '0', STR_PAD_LEFT) }}
                                                        </a>
                                                        <small class="text-muted">
                                                            {{ $pd->date ? \Carbon\Carbon::parse($pd->date)->format('d M, Y') : '-' }}
                                                        </small>
                                                    </div>
                                                    <span class="{{ $pdBadgeClass($pd->status) }}" style="text-transform:uppercase;">
                                                        {{ strtolower((string)$pd->status) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted" style="font-size:12px;">None</div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ITEMS --}}
                    <div class="table-responsive-sm">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Net Unit Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($purchase->purchaseDetails as $item)
                                @php
                                    $unit = (float) ($item->unit_price ?? 0);
                                    $price = (float) ($item->price ?? 0);
                                    $shownUnitPrice = $unit > 0 ? $unit : $price;

                                    $qty = (int) ($item->quantity ?? 0);

                                    $sub = (float) ($item->sub_total ?? 0);
                                    if ($sub <= 0) $sub = $shownUnitPrice * $qty;
                                @endphp
                                <tr>
                                    <td>
                                        <div style="font-weight:800;">{{ $item->product_name }}</div>
                                        <div class="mt-1">
                                            <span class="item-code">{{ $item->product_code }}</span>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ format_currency($shownUnitPrice) }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark" style="border:1px solid rgba(0,0,0,.08); border-radius:999px; font-weight:800;">
                                            {{ $qty }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ format_currency($item->product_discount_amount) }}</td>
                                    <td class="text-end">{{ format_currency($item->product_tax_amount) }}</td>
                                    <td class="text-end" style="font-weight:800;">{{ format_currency($sub) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- TOTALS --}}
                    <div class="row mt-3">
                        <div class="col-lg-4 col-sm-6 ms-auto">
                            <div class="totals-wrap">
                                <div class="totals-row">
                                    <div class="label">Discount ({{ $purchase->discount_percentage }}%)</div>
                                    <div class="value">{{ format_currency($purchase->discount_amount) }}</div>
                                </div>
                                <div class="totals-row">
                                    <div class="label">Tax ({{ $purchase->tax_percentage }}%)</div>
                                    <div class="value">{{ format_currency($purchase->tax_amount) }}</div>
                                </div>
                                <div class="totals-row">
                                    <div class="label">Shipping</div>
                                    <div class="value">{{ format_currency($purchase->shipping_amount) }}</div>
                                </div>
                                <div class="totals-row grand">
                                    <div class="label">Grand Total</div>
                                    <div class="value">{{ format_currency($purchase->total_amount) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> {{-- card-body --}}
            </div> {{-- card --}}
        </div>
    </div>
</div>
@endsection
