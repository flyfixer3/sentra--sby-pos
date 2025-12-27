@extends('layouts.app')

@section('title', 'Quality Report (Defect & Damaged)')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Quality Report</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>Quality Report</strong> <span class="text-muted">(Defect & Damaged)</span>
            </div>
            <div class="text-muted small">
                Max 500 row
            </div>
        </div>

        <div class="card-body">

            <form method="GET" action="{{ route('transfers.quality-report.index') }}" class="row g-3">

                @if($active === 'all')
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="all">All Branches</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ (int) request('branch_id') === (int) $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-control">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (int) request('warehouse_id') === (int) $wh->id ? 'selected' : '' }}>
                                {{ $wh->warehouse_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="all" {{ request('type', 'all') === 'all' ? 'selected' : '' }}>All</option>
                        <option value="defect" {{ request('type') === 'defect' ? 'selected' : '' }}>Defect</option>
                        <option value="damaged" {{ request('type') === 'damaged' ? 'selected' : '' }}>Damaged</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                           placeholder="Cari product / defect type / reason / transfer reference...">
                </div>

                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a class="btn btn-outline-secondary w-100" href="{{ route('transfers.quality-report.index') }}">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>

        </div>
    </div>

    {{-- Summary --}}
    <div class="row mb-3">
        <div class="col-md-6 mb-2">
            <div class="card border">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted">Total Defect Qty</div>
                        <div class="h4 mb-0">{{ number_format((int) $totalDefectQty) }}</div>
                    </div>
                    <span class="badge bg-warning text-dark">DEFECT</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-2">
            <div class="card border">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted">Total Damaged Qty</div>
                        <div class="h4 mb-0">{{ number_format((int) $totalDamagedQty) }}</div>
                    </div>
                    <span class="badge bg-danger">DAMAGED</span>
                </div>
            </div>
        </div>
    </div>

    {{-- DEFECT TABLE --}}
    @if(request('type', 'all') === 'all' || request('type') === 'defect')
        <div class="card mb-3">
            <div class="card-header">
                <strong>Defect List</strong>
            </div>
            <div class="card-body">
                @if($defects->isEmpty())
                    <div class="text-muted">Tidak ada data defect.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 160px;">Date</th>
                                    <th>Branch</th>
                                    <th>Warehouse</th>
                                    <th>Product</th>
                                    <th style="width: 100px;" class="text-center">Qty</th>
                                    <th style="width: 180px;">Defect Type</th>
                                    <th>Description</th>
                                    <th style="width: 200px;" class="text-center">Reference</th>
                                    {{-- <th style="width: 120px;" class="text-center">Detail</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($defects as $i => $d)
                                    @php
                                        $isTransfer = !empty($d->reference_type)
                                            && $d->reference_type === $transferClass
                                            && !empty($d->reference_id);

                                        $transferId  = $isTransfer ? (int) $d->reference_id : null;
                                        $transferRef = $isTransfer ? ($d->transfer_reference ?? null) : null;

                                        // fallback kalau transfer lama belum punya reference string
                                        $referenceLabel = $transferRef ?: ($transferId ? ('ID #' . $transferId) : null);
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($d->created_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $d->branch_name ?? '-' }}</td>
                                        <td>{{ $d->warehouse_name ?? '-' }}</td>
                                        <td>{{ $d->product_name ?? ('Product ID: ' . (int)$d->product_id) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark fw-semibold">{{ number_format((int) $d->quantity) }}</span>
                                        </td>
                                        <td>{{ $d->defect_type ?? '-' }}</td>
                                        <td>{{ $d->description ?? '-' }}</td>

                                        {{-- ✅ Reference jadi link --}}
                                        <td class="text-center">
                                            @if($transferId)
                                                <a href="{{ route('transfers.show', $transferId) }}"
                                                target="_blank"
                                                class="badge bg-primary text-white fw-semibold text-decoration-none">
                                                    {{ $referenceLabel }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- ✅ Tombol Detail --}}
                                        {{-- <td class="text-center">
                                            @if($transferId)
                                                <a class="btn btn-sm btn-outline-primary"
                                                href="{{ route('transfers.show', $transferId) }}"
                                                target="_blank">
                                                    Detail
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td> --}}
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif


    {{-- DAMAGED TABLE --}}
    @if(request('type', 'all') === 'all' || request('type') === 'damaged')
        <div class="card mb-3">
            <div class="card-header">
                <strong>Damaged / Pecah List</strong>
            </div>
            <div class="card-body">
                @if($damaged->isEmpty())
                    <div class="text-muted">Tidak ada data damaged.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="width: 160px;">Date</th>
                                    <th>Branch</th>
                                    <th>Warehouse</th>
                                    <th>Product</th>
                                    <th style="width: 100px;" class="text-center">Qty</th>
                                    <th>Reason</th>
                                    <th style="width: 130px;" class="text-center">Mut IN</th>
                                    <th style="width: 130px;" class="text-center">Mut OUT</th>
                                    <th style="width: 200px;" class="text-center">Reference</th>
                                    {{-- <th style="width: 120px;" class="text-center">Detail</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($damaged as $i => $dm)
                                    @php
                                        $isTransfer = !empty($dm->reference_type)
                                            && $dm->reference_type === $transferClass
                                            && !empty($dm->reference_id);

                                        $transferId  = $isTransfer ? (int) $dm->reference_id : null;
                                        $transferRef = $isTransfer ? ($dm->transfer_reference ?? null) : null;

                                        $referenceLabel = $transferRef ?: ($transferId ? ('ID #' . $transferId) : null);
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($dm->created_at)->format('d M Y H:i') }}</td>
                                        <td>{{ $dm->branch_name ?? '-' }}</td>
                                        <td>{{ $dm->warehouse_name ?? '-' }}</td>
                                        <td>{{ $dm->product_name ?? ('Product ID: ' . (int)$dm->product_id) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-danger text-white fw-semibold">{{ number_format((int) $dm->quantity) }}</span>
                                        </td>
                                        <td>{{ $dm->reason ?? '-' }}</td>
                                        <td class="text-center"><span class="badge bg-info text-dark">#{{ (int) $dm->mutation_in_id }}</span></td>
                                        <td class="text-center"><span class="badge bg-secondary text-white">#{{ (int) $dm->mutation_out_id }}</span></td>

                                        {{-- ✅ Reference jadi link --}}
                                        <td class="text-center">
                                            @if($transferId)
                                                <a href="{{ route('transfers.show', $transferId) }}"
                                                target="_blank"
                                                class="badge bg-primary text-white fw-semibold text-decoration-none">
                                                    {{ $referenceLabel }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- ✅ Tombol Detail --}}
                                        {{-- <td class="text-center">
                                            @if($transferId)
                                                <a class="btn btn-sm btn-outline-primary"
                                                href="{{ route('transfers.show', $transferId) }}"
                                                target="_blank">
                                                    Detail
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td> --}}
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif


</div>
@endsection
