@extends('layouts.app')

@section('title', 'Purchase Order Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
@php
    $status = (string) ($purchase_order->status ?? '');
    $statusLower = strtolower($status);

    $statusClass = 'badge-secondary';
    if ($statusLower === 'pending') $statusClass = 'badge-info';
    if ($statusLower === 'partial' || $statusLower === 'partially sent') $statusClass = 'badge-warning';
    if ($statusLower === 'completed') $statusClass = 'badge-success';
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Purchase Order: <strong>{{ $purchase_order->reference }}</strong></h4>
                    <div>
                        <a target="_blank" class="btn btn-sm btn-secondary d-print-none" href="{{ route('purchase-orders.pdf', $purchase_order->id) }}">
                            <i class="bi bi-printer"></i> Print
                        </a>
                        <a target="_blank" class="btn btn-sm btn-info d-print-none" href="{{ route('purchase-orders.pdf', $purchase_order->id) }}">
                            <i class="bi bi-save"></i> Save
                        </a>
                    </div>
                </div>

                <div class="card-body">

                    {{-- Convert / Delivery buttons --}}
                    @if(($purchase_order->status ?? null) !== 'Completed')
                        <div class="mb-3">
                            <a href="{{ route('purchase-order-purchases.create', $purchase_order->id) }}" class="btn btn-success">
                                Convert to Purchase <i class="bi bi-arrow-right-circle"></i>
                            </a>
                        </div>
                        <div class="mb-3">
                            <a href="{{ route('purchase-orders.deliveries.create', $purchase_order) }}" class="btn btn-success">
                                Create Purchase Delivery <i class="bi bi-truck"></i>
                            </a>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-sm-4">
                            <h5 class="border-bottom pb-2">Company Info:</h5>
                            <p><strong>{{ $purchase_order->branch?->name ?? '-' }}</strong></p>
                            <p>{{ $purchase_order->branch?->address ?? '-' }}</p>
                            <p>Phone: {{ $purchase_order->branch?->phone ?? '-' }}</p>
                        </div>

                        <div class="col-sm-4">
                            <h5 class="border-bottom pb-2">Supplier Info:</h5>
                            <p><strong>{{ $supplier->supplier_name ?? '-' }}</strong></p>
                            <p>{{ $supplier->address ?? '-' }}</p>
                            <p>Email: {{ $supplier->supplier_email ?? '-' }}</p>
                            <p>Phone: {{ $supplier->supplier_phone ?? '-' }}</p>
                        </div>

                        <div class="col-sm-4">
                            <h5 class="border-bottom pb-2">Invoice Info:</h5>
                            <p><strong>Invoice:</strong> INV/{{ $purchase_order->reference }}</p>
                            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($purchase_order->getRawOriginal('date') ?? now())->format('d M, Y') }}</p>

                            <p><strong>Status:</strong> <span class="badge {{ $statusClass }}">{{ $purchase_order->status ?? '-' }}</span></p>
                            <p><strong>Payment Status:</strong> <span class="badge badge-warning">{{ $purchase_order->payment_status ?? '-' }}</span></p>

                            {{-- ✅ NEW --}}
                            <p><strong>Fulfilled / Ordered / Remaining:</strong>
                                <span class="badge badge-light">{{ $totalFulfilledQty ?? 0 }}</span>
                                /
                                <span class="badge badge-light">{{ $totalOrderedQty ?? 0 }}</span>
                                /
                                <span class="badge badge-light">{{ $totalRemainingQty ?? 0 }}</span>
                            </p>

                            <p><strong>Created By:</strong> {{ optional($purchase_order->creator)->name ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Ordered Qty</th>
                                <th>Fulfilled Qty</th>
                                <th>Remaining Qty</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th>Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($purchase_order->purchaseOrderDetails as $item)
                                @php
                                    $ordered = (int) ($item->quantity ?? 0);
                                    $fulfilled = (int) ($item->fulfilled_quantity ?? 0);
                                    $remaining = max(0, $ordered - $fulfilled);
                                @endphp
                                <tr>
                                    <td>
                                        {{ $item->product_name }}<br>
                                        <span class="badge badge-success">{{ $item->product_code }}</span>
                                    </td>
                                    <td>{{ format_currency($item->unit_price) }}</td>
                                    <td>{{ $ordered }}</td>
                                    <td>{{ $fulfilled }}</td>
                                    <td><strong class="{{ $remaining > 0 ? 'text-danger' : 'text-success' }}">{{ $remaining }}</strong></td>
                                    <td>{{ format_currency($item->product_discount_amount) }}</td>
                                    <td>{{ format_currency($item->product_tax_amount) }}</td>
                                    <td>{{ format_currency($item->sub_total) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 col-sm-5 ml-md-auto">
                            <table class="table">
                                <tbody>
                                <tr>
                                    <td><strong>Discount ({{ $purchase_order->discount_percentage }}%)</strong></td>
                                    <td>{{ format_currency($purchase_order->discount_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tax ({{ $purchase_order->tax_percentage }}%)</strong></td>
                                    <td>{{ format_currency($purchase_order->tax_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Shipping</strong></td>
                                    <td>{{ format_currency($purchase_order->shipping_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Grand Total</strong></td>
                                    <td><strong>{{ format_currency($purchase_order->total_amount) }}</strong></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        @if($purchase_order->purchases->isNotEmpty())
                            <div class="col-lg-12">
                                <h5>Related Purchases:</h5>
                                <ul>
                                    @foreach($purchase_order->purchases as $purchase)
                                        <li>
                                            <a href="{{ route('purchases.show', $purchase->id) }}" class="text-primary">
                                                {{ $purchase->reference ?? 'Purchase #' . $purchase->id }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($purchase_order->purchaseDeliveries->isNotEmpty())
                            <div class="col-lg-12 mt-3">
                                <h5>Related Deliveries:</h5>
                                <ul>
                                    @foreach($purchase_order->purchaseDeliveries as $delivery)
                                        <li>
                                            <a href="{{ route('purchase-deliveries.show', $delivery->id) }}" class="text-primary">
                                                {{ $delivery->date }} - Status: {{ $delivery->status }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
