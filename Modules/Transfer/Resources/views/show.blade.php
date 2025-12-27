@extends('layouts.app')

@section('title', 'Detail Transfer')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfer</a></li>
    <li class="breadcrumb-item active">Detail</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">

    {{-- ================= HEADER CARD ================= --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <div class="text-muted small">Reference</div>
                <h5 class="mb-0">{{ $transfer->reference }}</h5>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                @if ($transfer->status === 'pending')
                    <a href="{{ route('transfers.print.pdf', $transfer->id) }}"
                       class="btn btn-sm btn-dark" target="_blank">
                        <i class="bi bi-printer"></i> Cetak Surat Jalan
                    </a>
                @endif

                @if (
                    $transfer->status === 'shipped'
                    && session('active_branch') !== 'all'
                    && (int) session('active_branch') === (int) $transfer->to_branch_id
                )
                    <a href="{{ route('transfers.confirm', $transfer->id) }}"
                       class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> Konfirmasi
                    </a>
                @endif

                @include('transfer::partials.cancel-transfer-modal')
            </div>
        </div>
    </div>

    {{-- ================= INFO GRID ================= --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Dari Gudang</div>
                    <strong>{{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Ke Cabang</div>
                    <strong>{{ $transfer->toBranch->name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Informasi Transfer</div>
                    <div class="mb-1">
                        Tanggal:
                        <strong>{{ \Carbon\Carbon::parse($transfer->date)->format('d M Y') }}</strong>
                    </div>
                    <div class="mb-1">
                        Status:
                        <span class="badge bg-info text-dark text-uppercase">
                            {{ $transfer->status }}
                        </span>
                    </div>
                    @if($transfer->delivery_code)
                        <div class="small text-muted">
                            Delivery Code:
                            <strong>{{ $transfer->delivery_code }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================= ITEM TABLE ================= --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <strong>Daftar Produk</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Produk</th>
                            <th class="text-center" width="120">Sent</th>
                            @if($transfer->status === 'confirmed')
                                <th class="text-center">Received</th>
                                <th class="text-center">Defect</th>
                                <th class="text-center">Damaged</th>
                            @else
                                <th class="text-center">Jumlah</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $i => $item)
                            @php
                                $product = $item->product;
                                $pid = (int) $item->product_id;
                                $summary = $itemSummaries[$pid] ?? [];
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    @if($product)
                                        <div class="fw-semibold">{{ $product->product_name }}</div>
                                        <div class="text-muted small">{{ $product->product_code }}</div>
                                    @else
                                        <span class="text-danger">Product ID {{ $pid }} not found</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary text-white fw-semibold">{{ $item->quantity }}</span>
                                </td>

                                @if($transfer->status === 'confirmed')
                                    <td class="text-center">
                                        <span class="badge bg-success">
                                            {{ $summary['received_good'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">
                                            {{ $summary['defect'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            {{ $summary['damaged'] ?? 0 }}
                                        </span>
                                    </td>
                                @else
                                    <td class="text-center">{{ $item->quantity }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Tidak ada item dalam transfer ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================= DEFECT ================= --}}
    @if($transfer->status === 'confirmed' && $defects->isNotEmpty())
        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning bg-opacity-25 fw-semibold">
                Defect Details
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th>Type</th>
                            <th>Desc</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($defects as $i => $d)
                            @php
                                $item = $transfer->items->firstWhere('product_id', $d->product_id);
                                $product = optional($item)->product;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    {{ $product
                                        ? $product->product_name . ' (' . $product->product_code . ')'
                                        : 'Product ID ' . $d->product_id }}
                                </td>
                                <td class="text-center">{{ $d->quantity }}</td>
                                <td>{{ $d->defect_type }}</td>
                                <td>{{ $d->description ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= DAMAGED ================= --}}
    @if($transfer->status === 'confirmed' && $damaged->isNotEmpty())
        <div class="card mb-4 shadow-sm border-danger">
            <div class="card-header bg-danger bg-opacity-10 fw-semibold">
                Damaged / Pecah Details
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th>Reason</th>
                            <th class="text-center">IN</th>
                            <th class="text-center">OUT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($damaged as $i => $dm)
                            @php
                                $item = $transfer->items->firstWhere('product_id', $dm->product_id);
                                $product = optional($item)->product;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    {{ $product
                                        ? $product->product_name . ' (' . $product->product_code . ')'
                                        : 'Product ID ' . $dm->product_id }}
                                </td>
                                <td class="text-center">{{ $dm->quantity }}</td>
                                <td>{{ $dm->reason }}</td>
                                <td class="text-center">#{{ $dm->mutation_in_id }}</td>
                                <td class="text-center">#{{ $dm->mutation_out_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
