@extends('layouts.app')

@section('title', 'Rack Movement Detail')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.rack-movements.index') }}">Rack Movements</a></li>
        <li class="breadcrumb-item active">Detail</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="page-title">Rack Movement Detail</div>
                    <div class="page-subtitle text-muted">
                        Reference: <b>{{ $rackMovement->reference }}</b> | Date: <b>{{ optional($rackMovement->date)->format('Y-m-d') }}</b>
                    </div>
                </div>
                <div>
                    <a href="{{ route('inventory.rack-movements.index') }}" class="btn btn-outline-secondary btn-modern">Back</a>
                </div>
            </div>

            <div class="divider-soft mb-3"></div>

            <div class="row">
                <div class="col-md-6">
                    <div class="p-3 border rounded" style="background:#f8fafc;">
                        <div class="font-weight-bold mb-2">From</div>
                        <div class="small text-muted">Warehouse</div>
                        <div>{{ $rackMovement->fromWarehouse->warehouse_name ?? '-' }}</div>
                        <div class="small text-muted mt-2">Rack</div>
                        <div>
                            @php
                                $fromRackLabel = trim((string)($rackMovement->fromRack->code ?? '')) !== ''
                                    ? (($rackMovement->fromRack->code ?? '-') . ' - ' . ($rackMovement->fromRack->name ?? '-'))
                                    : (string)($rackMovement->fromRack->name ?? '-');
                            @endphp
                            {{ $fromRackLabel }}
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 border rounded" style="background:#f8fafc;">
                        <div class="font-weight-bold mb-2">To</div>
                        <div class="small text-muted">Warehouse</div>
                        <div>{{ $rackMovement->toWarehouse->warehouse_name ?? '-' }}</div>
                        <div class="small text-muted mt-2">Rack</div>
                        <div>
                            @php
                                $toRackLabel = trim((string)($rackMovement->toRack->code ?? '')) !== ''
                                    ? (($rackMovement->toRack->code ?? '-') . ' - ' . ($rackMovement->toRack->name ?? '-'))
                                    : (string)($rackMovement->toRack->name ?? '-');
                            @endphp
                            {{ $toRackLabel }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="p-3 border rounded">
                    <div class="small text-muted">Note</div>
                    <div>{{ $rackMovement->note ?: '-' }}</div>
                </div>
            </div>

            <div class="mt-4">
                <div class="font-weight-bold mb-2">Items</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-modern">
                        <thead>
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Product</th>
                                <th style="width:140px;" class="text-center">Condition</th>
                                <th style="width:140px;" class="text-right">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rackMovement->items as $i => $it)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div class="font-weight-bold">{{ $it->product->product_name ?? '-' }}</div>
                                        <div class="small text-muted">{{ $it->product->product_code ?? '-' }}</div>
                                    </td>
                                    <td class="text-center">
                                        @php $c = strtolower((string)$it->condition); @endphp
                                        @if($c === 'good')
                                            <span class="badge badge-success">GOOD</span>
                                        @elseif($c === 'defect')
                                            <span class="badge badge-warning">DEFECT</span>
                                        @else
                                            <span class="badge badge-danger">DAMAGED</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ (int)$it->quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-2">
                    Semua pergerakan di atas akan punya pasangan mutation <b>OUT</b> dari From Rack dan mutation <b>IN</b> ke To Rack dengan reference yang sama.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }
    .btn-modern {
        border-radius: 999px;
        padding: 10px 16px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(2, 6, 23, 0.12);
    }
    .page-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
    }
    .page-subtitle {
        font-size: .9rem;
    }
    .divider-soft {
        height: 1px;
        background: #e2e8f0;
    }
    .table-modern thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
    }
</style>
@endpush