@extends('layouts.app')

@section('title', 'Inventory Stocks')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Inventory Stocks</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">

            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="page-title">Daftar Stok Produk</div>
                    <div class="page-subtitle text-muted">Cabang mengikuti pilihan di header.</div>
                </div>
            </div>

            <div class="divider-soft mb-3"></div>

            <form method="GET" action="{{ route('inventory.stocks.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="small text-muted mb-1 d-block">Filter Gudang</label>
                    <select name="warehouse_id" class="form-control form-control-modern">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                {{ $wh->warehouse_name ?? ('Warehouse #' . $wh->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="small text-muted mb-1 d-block">Cari Produk</label>
                    <input type="text" name="product" class="form-control form-control-modern"
                           placeholder="Cari produk / kode" value="{{ request('product') }}">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary btn-modern w-100">Filter</button>
                </div>
            </form>

            <div class="table-wrap">
                {!! $dataTable->table(['class' => 'table table-striped table-bordered w-100 table-modern'], true) !!}
            </div>
        </div>
    </div>
</div>

{{-- ✅ Rack Modal --}}
<div class="modal fade" id="rackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Stok per Rak</h5>

                {{-- BS4 / CoreUI close --}}
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px;">
                    <span aria-hidden="true">&times;</span>
                </button>

                {{-- BS5 close (kalau suatu saat pindah bs5) --}}
                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Kode Rak</th>
                            <th>Nama Rak</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="rackDetailBody">
                        <tr><td colspan="3" class="text-center text-muted">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ✅ Quality Modal (Defect/Damaged) --}}
<div class="modal fade" id="qualityModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qualityModalTitle">Quality Report</h5>

                {{-- BS4 / CoreUI close --}}
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px;">
                    <span aria-hidden="true">&times;</span>
                </button>

                {{-- BS5 close --}}
                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="small text-muted mb-2" id="qualityModalHint">
                    Menampilkan detail defect/damaged per cabang & gudang + foto.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:180px;">Cabang</th>
                                <th style="width:180px;">Gudang</th>
                                <th style="width:90px;" class="text-end">Qty</th>
                                <th id="qualityTypeCol" style="width:180px;">Type</th>
                                <th>Desc / Reason</th>
                                <th style="width:140px;" class="text-center">Photo</th>
                                {{-- <th style="width:170px;">Ref</th> --}}
                                <th style="width:170px;">Created</th>
                            </tr>
                        </thead>
                        <tbody id="qualityBody">
                            <tr><td colspan="9" class="text-center text-muted">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern{
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }
    .page-title{
        font-weight: 800;
        font-size: 18px;
        color: #0f172a;
        line-height: 1.2;
    }
    .page-subtitle{ font-size: 13px; }
    .divider-soft{
        height: 1px;
        background: #e2e8f0;
        opacity: .9;
    }
    .btn-modern{
        border-radius: 999px;
        padding: 8px 14px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(2, 6, 23, 0.12);
    }
    .form-control-modern{
        border-radius: 10px;
        border-color: #e2e8f0;
    }
    .form-control-modern:focus{
        border-color: #93c5fd;
        box-shadow: 0 0 0 0.2rem rgba(147,197,253,0.25);
    }
    .table-wrap{
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-modern thead th{
        background: #f8fafc !important;
        color: #334155;
        font-weight: 800;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .table-modern td, .table-modern th{
        vertical-align: middle;
    }
</style>
@endpush

@push('page_scripts')
    {!! $dataTable->scripts() !!}

<script>
function openModal(modalId) {
    const modalEl = document.getElementById(modalId);

    // 1) Bootstrap 5
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return true;
        }
    } catch(e) {}

    // 2) CoreUI
    try {
        if (typeof coreui !== 'undefined' && coreui.Modal) {
            const modal = coreui.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return true;
        }
    } catch(e) {}

    // 3) Bootstrap 4 / CoreUI (jQuery)
    try {
        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
            jQuery(modalEl).modal('show');
            return true;
        }
    } catch(e) {}

    return false;
}

function showRackDetails(productId, branchId, warehouseId) {
    document.getElementById('rackDetailBody').innerHTML =
        '<tr><td colspan="3" class="text-center text-muted">Memuat data...</td></tr>';

    const opened = openModal('rackModal');
    if (!opened) {
        alert('Modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    fetch(`/inventory/stocks/rack-details/${productId}/${branchId}/${warehouseId}`)
        .then(r => r.json())
        .then(res => {
            const body = document.getElementById('rackDetailBody');
            body.innerHTML = '';

            if (res.success && Array.isArray(res.data) && res.data.length) {
                res.data.forEach(item => {
                    const code = item.rack && item.rack.code ? item.rack.code : '-';
                    const name = item.rack && item.rack.name ? item.rack.name : '-';
                    const qty  = typeof item.qty_available !== 'undefined' ? item.qty_available : 0;

                    body.innerHTML += `
                        <tr>
                            <td>${code}</td>
                            <td>${name}</td>
                            <td class="text-end">${qty}</td>
                        </tr>`;
                });
            } else {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Tidak ada data rak.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('rackDetailBody').innerHTML =
                '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data.</td></tr>';
        });
}

// ✅ Quality report (defect / damaged)
function openQualityModal(type, productId, warehouseId, isAllBranchMode) {
    const titleEl = document.getElementById('qualityModalTitle');
    const bodyEl = document.getElementById('qualityBody');
    const typeColEl = document.getElementById('qualityTypeCol');

    const typeUpper = (type || '').toUpperCase();

    titleEl.textContent = `Quality Report - ${typeUpper}`;
    bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Memuat data...</td></tr>';

    // header kolom type
    if (type === 'defect') typeColEl.textContent = 'Defect Type';
    else typeColEl.textContent = 'Reason';

    const opened = openModal('qualityModal');
    if (!opened) {
        alert('Quality modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    // ikut filter gudang yang dipilih di form
    const whSelected = document.querySelector('select[name="warehouse_id"]')?.value || '';
    const qs = new URLSearchParams();
    if (whSelected) qs.set('warehouse_id', whSelected);

    fetch(`/inventory/stocks/quality-details/${type}/${productId}?` + qs.toString())
        .then(r => r.json())
        .then(res => {
            bodyEl.innerHTML = '';

            if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Tidak ada data.</td></tr>';
                return;
            }

            res.data.forEach((row, idx) => {
                const branch = row.branch_name ?? '-';
                const wh = row.warehouse_name ?? '-';
                const qty = row.quantity ?? 0;

                let typeText = '-';
                let descText = '-';

                if (type === 'defect') {
                    typeText = row.defect_type ?? '-';
                    descText = row.description ?? '-';
                } else {
                    typeText = row.reason ?? '-';
                    descText = row.reason ?? '-';
                }

                const photo = row.photo_url
                    ? `<a href="${row.photo_url}" target="_blank" title="Open Photo">
                         <img src="${row.photo_url}" style="height:45px;width:auto;border-radius:8px;border:1px solid #e5e7eb;">
                       </a>`
                    : '<span class="text-muted">-</span>';

                const ref = (row.reference_type && row.reference_id)
                    ? `${row.reference_type} #${row.reference_id}`
                    : '-';

                const created = row.created_at ?? '-';

                bodyEl.innerHTML += `
                    <tr>
                        <td>${idx+1}</td>
                        <td>${branch}</td>
                        <td>${wh}</td>
                        <td class="text-end">${qty}</td>
                        <td>${typeText}</td>
                        <td>${descText}</td>
                        <td class="text-center">${photo}</td>
                        <td>${created}</td>
                    </tr>
                `;
            });
        })
        .catch(err => {
            console.error(err);
            bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Gagal memuat data.</td></tr>';
        });
}
</script>
@endpush
