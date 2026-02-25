@extends('layouts.app')

@section('title', 'Rack Movements')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.stocks.index') }}">Inventory Stocks</a></li>
        <li class="breadcrumb-item active">Rack Movements</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">
            @include('utils.alerts')

            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="page-title">Rack Movements</div>
                    <div class="page-subtitle text-muted">
                        Fitur ini untuk <b>memindahkan stok antar rack</b> di <b>cabang yang sama</b>.
                        Semua pergerakan stok dicatat lewat <b>Mutations</b> (tidak mengubah stock langsung di controller).
                    </div>
                </div>

                @can('create_rack_movements')
                    <div>
                        <a href="{{ route('inventory.rack-movements.create') }}" class="btn btn-primary btn-modern">
                            Create Rack Movement <i class="bi bi-plus"></i>
                        </a>
                    </div>
                @endcan
            </div>

            <div class="divider-soft mb-3"></div>

            <form method="GET" action="{{ route('inventory.rack-movements.index') }}" class="row g-2 align-items-end mb-3">
                @if($isAll)
                    <div class="col-md-3">
                        <label class="small text-muted mb-1 d-block">Branch</label>
                        <select name="branch_id" class="form-control form-control-modern">
                            <option value="">-- All Branches --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ (string)request('branch_id') === (string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name ?? ('Branch #' . $b->id) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="small text-muted mb-1 d-block">Search</label>
                        <input type="text" name="q" class="form-control form-control-modern" placeholder="Search reference / note" value="{{ request('q') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-modern w-100">Filter</button>
                    </div>
                @else
                    <div class="col-md-10">
                        <label class="small text-muted mb-1 d-block">Search</label>
                        <input type="text" name="q" class="form-control form-control-modern" placeholder="Search reference / note" value="{{ request('q') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-modern w-100">Filter</button>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-modern w-100">
                    <thead>
                        <tr>
                            <th style="width:170px;">Date</th>
                            <th style="width:190px;">Reference</th>
                            @if($isAll)
                                <th style="width:180px;">Branch</th>
                            @endif
                            <th>From</th>
                            <th>To</th>
                            <th style="width:120px;" class="text-center">Items</th>
                            <th style="width:120px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mv)
                            @php
                                $fromRackLabel = trim((string)($mv->fromRack->code ?? '')) !== ''
                                    ? (($mv->fromRack->code ?? '-') . ' - ' . ($mv->fromRack->name ?? '-'))
                                    : (string)($mv->fromRack->name ?? '-');
                                $toRackLabel = trim((string)($mv->toRack->code ?? '')) !== ''
                                    ? (($mv->toRack->code ?? '-') . ' - ' . ($mv->toRack->name ?? '-'))
                                    : (string)($mv->toRack->name ?? '-');
                            @endphp
                            <tr>
                                <td>{{ optional($mv->date)->format('Y-m-d') }}</td>
                                <td><span class="font-weight-bold">{{ $mv->reference }}</span></td>
                                @if($isAll)
                                    <td>{{ $mv->branch->name ?? '-' }}</td>
                                @endif
                                <td>
                                    <div class="small text-muted">Warehouse</div>
                                    <div>{{ $mv->fromWarehouse->warehouse_name ?? '-' }}</div>
                                    <div class="small text-muted mt-1">Rack</div>
                                    <div>{{ $fromRackLabel }}</div>
                                </td>
                                <td>
                                    <div class="small text-muted">Warehouse</div>
                                    <div>{{ $mv->toWarehouse->warehouse_name ?? '-' }}</div>
                                    <div class="small text-muted mt-1">Rack</div>
                                    <div>{{ $toRackLabel }}</div>
                                </td>
                                <td class="text-center">
                                    {{ (int)($mv->items_count ?? 0) }}
                                </td>
                                <td class="text-center">
                                    @can('show_rack_movements')
                                        <a href="{{ route('inventory.rack-movements.show', $mv->id) }}" class="btn btn-sm btn-outline-primary btn-modern">
                                            View
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAll ? 7 : 6 }}" class="text-center text-muted">No data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {{ $movements->links() }}
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
    .form-control-modern {
        border-radius: 10px;
        border-color: #e2e8f0;
    }
    .form-control-modern:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 0.2rem rgba(147,197,253,0.25);
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