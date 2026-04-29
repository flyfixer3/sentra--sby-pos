@extends('layouts.app')

@section('title', 'Stock Opname')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.stocks.index') }}">Inventory</a></li>
        <li class="breadcrumb-item active">Stock Opname</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:12px;">
                <div>
                    <div class="page-title">Stock Opname Kaca</div>
                    <div class="text-muted small">Buat draft, export template hitung fisik, import hasil, lalu finalize ke adjustment.</div>
                </div>
                <a href="{{ route('inventory.stock-opnames.create') }}" class="btn btn-primary btn-modern">
                    <i class="bi bi-plus-circle mr-1"></i> Buat Draft Opname
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="thead-light">
                        <tr>
                            <th>Reference</th>
                            <th>Tanggal</th>
                            <th>Cabang</th>
                            <th>Gudang</th>
                            <th>Status</th>
                            <th>Adjustment</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($opnames as $opname)
                            <tr>
                                <td>{{ $opname->reference }}</td>
                                <td>{{ $opname->opname_date }}</td>
                                <td>{{ optional($opname->branch)->name }}</td>
                                <td>{{ optional($opname->warehouse)->warehouse_name }}</td>
                                <td>
                                    <span class="badge badge-{{ $opname->status === 'finalized' ? 'success' : 'secondary' }}">
                                        {{ strtoupper($opname->status) }}
                                    </span>
                                </td>
                                <td>{{ optional($opname->adjustment)->reference ?? '-' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('inventory.stock-opnames.show', $opname) }}" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Belum ada draft stock opname.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $opnames->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern{border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 22px rgba(15,23,42,.06)}
    .page-title{font-weight:800;font-size:18px;color:#0f172a;line-height:1.2}
    .btn-modern{border-radius:999px;padding:8px 14px;font-weight:700;box-shadow:0 6px 14px rgba(2,6,23,.12)}
</style>
@endpush
