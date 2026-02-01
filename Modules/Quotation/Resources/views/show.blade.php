@extends('layouts.app')

@section('title', 'Quotation Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">Quotations</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
@php
    $branchId = \App\Support\BranchContext::id();

    $st = strtolower(trim((string) ($quotation->status ?? 'pending')));
    $badgeClass = match($st) {
        'pending' => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary',
    };

    $dateText = $quotation->date
        ? (method_exists($quotation->date, 'format') ? $quotation->date->format('d M, Y') : \Carbon\Carbon::parse($quotation->date)->format('d M, Y'))
        : '-';

    $hasSO = \Illuminate\Support\Facades\DB::table('sale_orders')
        ->where('branch_id', $branchId)
        ->where('quotation_id', (int) $quotation->id)
        ->exists();

    $hasSD = \Illuminate\Support\Facades\DB::table('sale_deliveries')
        ->where('branch_id', $branchId)
        ->where('quotation_id', (int) $quotation->id)
        ->exists();

    $hasChildren = $hasSO || $hasSD;

    $customerName = $customer->customer_name ?? ($quotation->customer_name ?? '-');
@endphp

<div class="container-fluid">
    @include('utils.alerts')

    {{-- Top Summary / Hero --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="mb-0">{{ $quotation->reference }}</h4>
                        <span class="badge {{ $badgeClass }}">{{ strtoupper($st) }}</span>
                    </div>

                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong>
                        <span class="mx-1">•</span>
                        Customer: <strong>{{ $customerName }}</strong>
                    </div>

                    <div class="text-muted small mt-1">
                        Quotation ID: <strong>#{{ (int) $quotation->id }}</strong>
                        @if(!empty($quotation->created_at))
                            <span class="mx-1">•</span>
                            Created: <strong>{{ $quotation->created_at->format('d M Y H:i') }}</strong>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    @can('create_sale_orders')
                        <a class="btn btn-primary"
                           href="{{ route('sale-orders.create', ['source'=>'quotation', 'quotation_id'=>$quotation->id]) }}"
                           @if($hasChildren) aria-disabled="true" style="pointer-events:none;opacity:.6;" title="Already has Sale Order / Sale Delivery" @endif>
                            <i class="bi bi-clipboard-check me-1"></i> Create Sale Order
                        </a>
                    @endcan

                    @can('create_sale_invoices')
                        <form method="POST"
                            action="{{ route('quotations.create-invoice-direct', $quotation->id) }}"
                            class="d-inline"
                            onsubmit="return confirm('Create Sale Invoice from this quotation (skip Sale Order, auto create Sale Delivery pending)?');">
                            @csrf
                            <button type="submit" class="btn btn-warning" @if($hasChildren) disabled @endif>
                                <i class="bi bi-receipt me-1"></i> Create Sales Invoice
                            </button>
                        </form>
                    @endcan

                    <a target="_blank" class="btn btn-outline-secondary" href="{{ route('quotations.pdf', $quotation->id) }}">
                        <i class="bi bi-printer me-1"></i> Print
                    </a>

                    <a target="_blank" class="btn btn-outline-info" href="{{ route('quotations.pdf', $quotation->id) }}">
                        <i class="bi bi-save me-1"></i> Save
                    </a>
                </div>
            </div>

            @if($hasChildren)
                <hr class="my-3">
                <div class="alert alert-success mb-0 d-flex align-items-start gap-2">
                    <i class="bi bi-check2-circle fs-5"></i>
                    <div>
                        <div class="fw-semibold">Quotation sudah punya turunan.</div>
                        <div class="small">
                            Karena sudah ada Sale Order / Sale Delivery dari quotation ini, status quotation dianggap <strong>Completed</strong>.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Info Cards --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Company</div>
                        <span class="badge bg-light text-dark border"><i class="bi bi-building me-1"></i> Info</span>
                    </div>
                    <hr class="my-2">
                    <div class="fw-bold">{{ settings()->company_name }}</div>
                    <div class="text-muted small">{{ settings()->company_address }}</div>
                    <div class="small mt-2">
                        <div>Email: <strong>{{ settings()->company_email }}</strong></div>
                        <div>Phone: <strong>{{ settings()->company_phone }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Customer</div>
                        <span class="badge bg-light text-dark border"><i class="bi bi-person-circle me-1"></i> Info</span>
                    </div>
                    <hr class="my-2">
                    <div class="fw-bold">{{ $customer->customer_name ?? '-' }}</div>
                    <div class="text-muted small">{{ $customer->address ?? '-' }}</div>
                    <div class="small mt-2">
                        <div>Email: <strong>{{ $customer->customer_email ?? '-' }}</strong></div>
                        <div>Phone: <strong>{{ $customer->customer_phone ?? '-' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Quotation</div>
                        <span class="badge bg-light text-dark border"><i class="bi bi-receipt me-1"></i> Summary</span>
                    </div>
                    <hr class="my-2">
                    <div class="small">
                        <div>Reference: <strong>{{ $quotation->reference }}</strong></div>
                        <div>Date: <strong>{{ $dateText }}</strong></div>
                        <div>Status: <strong>{{ $quotation->status ?? '-' }}</strong></div>
                        <div>Payment Status: <strong>{{ $quotation->payment_status ?? '-' }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Items + Totals --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <h6 class="mb-0">Items</h6>
                    <div class="text-muted small">Daftar item quotation beserta harga dan perhitungan.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end" style="width:160px;">Net Unit Price</th>
                            <th class="text-end" style="width:120px;">Qty</th>
                            <th class="text-end" style="width:140px;">Discount</th>
                            <th class="text-end" style="width:140px;">Tax</th>
                            <th class="text-end" style="width:160px;">Sub Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotation->quotationDetails as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->product_name }}</div>
                                    <div class="text-muted small">
                                        <span class="badge bg-success">{{ $item->product_code }}</span>
                                    </div>
                                </td>
                                <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                                <td class="text-end">{{ number_format((int)$item->quantity) }}</td>
                                <td class="text-end">{{ format_currency($item->product_discount_amount) }}</td>
                                <td class="text-end">{{ format_currency($item->product_tax_amount) }}</td>
                                <td class="text-end fw-semibold">{{ format_currency($item->sub_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <hr class="my-3">

            <div class="row g-3">
                <div class="col-lg-8">
                    {{-- optional: space for future notes / terms --}}
                    <div class="text-muted small">
                        Note: {{ $quotation->note ?? '-' }}
                    </div>
                </div>

                <div class="col-lg-4 ms-auto">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex justify-content-between">
                            <div class="text-muted">Discount ({{ $quotation->discount_percentage }}%)</div>
                            <div class="fw-semibold">{{ format_currency($quotation->discount_amount) }}</div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="text-muted">Tax ({{ $quotation->tax_percentage }}%)</div>
                            <div class="fw-semibold">{{ format_currency($quotation->tax_amount) }}</div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <div class="text-muted">Shipping</div>
                            <div class="fw-semibold">{{ format_currency($quotation->shipping_amount) }}</div>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between">
                            <div class="fw-bold">Grand Total</div>
                            <div class="fw-bold fs-6">{{ format_currency($quotation->total_amount) }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
