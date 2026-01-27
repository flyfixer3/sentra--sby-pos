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
<div class="container-fluid">

    <div class="row">
        <div class="col-lg-12">

            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div>
                        Reference: <strong>{{ $saleOrder->reference }}</strong>
                        <div class="text-muted small">
                            Date: {{ \Carbon\Carbon::parse($saleOrder->date)->format('d M, Y') }}
                            • Status: <strong>{{ strtoupper($saleOrder->status) }}</strong>
                        </div>
                    </div>

                    <div class="mfs-auto d-flex flex-wrap gap-2">
                        @php
                            $hasRemaining = false;
                            foreach(($remainingMap ?? []) as $rem){
                                if ((int)$rem > 0) { $hasRemaining = true; break; }
                            }
                        @endphp

                        @can('create_sale_deliveries')
                            @if($hasRemaining)
                                <a class="btn btn-sm btn-primary"
                                   href="{{ route('sale-deliveries.create', ['source'=>'sale_order', 'sale_order_id'=>$saleOrder->id]) }}">
                                    <i class="bi bi-truck"></i> Create Sale Delivery
                                </a>
                            @else
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="bi bi-check2-circle"></i> Fully Delivered
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>

                <div class="card-body">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Customer</h6>
                            <div><strong>{{ $saleOrder->customer?->customer_name ?? '-' }}</strong></div>
                            <div class="text-muted small">{{ $saleOrder->customer?->address ?? '' }}</div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Links</h6>
                            <div>Quotation: <strong>{{ $saleOrder->quotation_id ? ('#'.$saleOrder->quotation_id) : '-' }}</strong></div>
                            <div>Invoice (Sale): <strong>{{ $saleOrder->sale_id ? ('#'.$saleOrder->sale_id) : '-' }}</strong></div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Note</h6>
                            <div class="text-muted">{{ $saleOrder->note ?? '-' }}</div>
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-2">Items (Anchor Qty vs Delivered)</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-right">Ordered</th>
                                    <th class="text-right">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($saleOrder->items as $it)
                                @php
                                    $pid = (int) $it->product_id;
                                    $rem = (int) ($remainingMap[$pid] ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        {{ $it->product?->product_name ?? ('Product ID '.$pid) }}
                                        <div class="small text-muted">product_id: {{ $pid }}</div>
                                    </td>
                                    <td class="text-right">{{ number_format((int)($it->quantity ?? 0)) }}</td>
                                    <td class="text-right">
                                        @if($rem <= 0)
                                            <span class="badge badge-success">0</span>
                                        @else
                                            <span class="badge badge-warning">{{ number_format($rem) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    <h6 class="mb-2">Sale Deliveries for this Sale Order</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Warehouse</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width:120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($saleOrder->deliveries as $d)
                                <tr>
                                    <td>{{ $d->reference }}</td>
                                    <td>{{ $d->date ? \Carbon\Carbon::parse($d->date)->format('d-m-Y') : '-' }}</td>
                                    <td>{{ $d->warehouse?->warehouse_name ?? ('WH#'.$d->warehouse_id) }}</td>
                                    <td>{{ strtoupper($d->status ?? 'PENDING') }}</td>
                                    <td class="text-center">
                                        <a class="btn btn-sm btn-info" href="{{ route('sale-deliveries.show', $d->id) }}">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No deliveries yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection
