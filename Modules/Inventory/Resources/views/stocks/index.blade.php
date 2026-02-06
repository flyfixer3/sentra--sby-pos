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
                            <option value="{{ $wh->id }}" {{ (string)request('warehouse_id') === (string)$wh->id ? 'selected' : '' }}>
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

{{-- ✅ Rack Modal (UPGRADE: Total/Good/Defect/Damaged) --}}
<div class="modal fade" id="rackModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Detail Stok per Rak</h5>
                    <div class="small text-muted" id="rackModalHint">-</div>
                </div>

                {{-- BS4 / CoreUI close --}}
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px;">
                    <span aria-hidden="true">&times;</span>
                </button>

                {{-- BS5 close (kalau suatu saat pindah bs5) --}}
                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:8px;">
                    <div class="small text-muted">
                        Breakdown stock per rack: <b>Total / Good / Defect / Damaged</b>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:6px;">
                        <span class="badge badge-secondary">Total: <span id="rackGrandTotal">0</span></span>
                        <span class="badge badge-success">Good: <span id="rackGrandGood">0</span></span>
                        <span class="badge badge-warning text-dark">Defect: <span id="rackGrandDefect">0</span></span>
                        <span class="badge badge-danger">Damaged: <span id="rackGrandDamaged">0</span></span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:120px;">Kode Rak</th>
                                <th>Nama Rak</th>
                                <th class="text-end" style="width:110px;">Total</th>
                                <th class="text-end" style="width:110px;">Good</th>
                                <th class="text-end" style="width:110px;">Defect</th>
                                <th class="text-end" style="width:110px;">Damaged</th>
                            </tr>
                        </thead>

                        <tbody id="rackDetailBody">
                            <tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>
                        </tbody>

                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="2" class="text-right">Grand Total</td>
                                <td class="text-end" id="rackFootTotal">0</td>
                                <td class="text-end" id="rackFootGood">0</td>
                                <td class="text-end" id="rackFootDefect">0</td>
                                <td class="text-end" id="rackFootDamaged">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    Idealnya <b>Total = Good + Defect + Damaged</b> per rack.
                </div>
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
                                <th style="width:170px;">Created</th>
                            </tr>
                        </thead>
                        <tbody id="qualityBody">
                            <tr><td colspan="8" class="text-center text-muted">Memuat data...</td></tr>
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

function asInt(v){
    const n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
}
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
function resetRackTotals(){
    const ids = [
        'rackGrandTotal','rackGrandGood','rackGrandDefect','rackGrandDamaged',
        'rackFootTotal','rackFootGood','rackFootDefect','rackFootDamaged'
    ];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '0';
    });
}

// ✅ Rack detail modal
function showRackDetails(productId, branchId, warehouseId) {
    document.getElementById('rackDetailBody').innerHTML =
        '<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>';

    resetRackTotals();

    // hint kecil biar user paham lagi buka apa
    const hintEl = document.getElementById('rackModalHint');
    if (hintEl) hintEl.textContent = `PID: ${productId} | WH: ${warehouseId}`;

    const opened = openModal('rackModal');
    if (!opened) {
        alert('Modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    fetch(`/inventory/stocks/rack-details/${productId}/${branchId}/${warehouseId}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(res => {
        const body = document.getElementById('rackDetailBody');
        body.innerHTML = '';

        if (!res || !res.success || !Array.isArray(res.data) || res.data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data rak.</td></tr>';
            return;
        }

        let grandTotal = 0, grandGood = 0, grandDefect = 0, grandDamaged = 0;

        res.data.forEach(item => {
            // ✅ sesuai response rackDetails() terbaru
            const code = escapeHtml(item.rack_code ?? '-');
            const name = escapeHtml(item.rack_name ?? '-');

            const qt = asInt(item.qty_total);
            const qg = asInt(item.qty_good);
            const qd = asInt(item.qty_defect);
            const qx = asInt(item.qty_damaged);

            grandTotal += qt;
            grandGood += qg;
            grandDefect += qd;
            grandDamaged += qx;

            body.innerHTML += `
                <tr>
                    <td><span class="badge badge-dark">${code}</span></td>
                    <td>${name}</td>
                    <td class="text-end font-weight-bold">${qt}</td>
                    <td class="text-end"><span class="badge badge-success">${qg}</span></td>
                    <td class="text-end"><span class="badge badge-warning text-dark">${qd}</span></td>
                    <td class="text-end"><span class="badge badge-danger">${qx}</span></td>
                </tr>`;
        });

        // header badges
        document.getElementById('rackGrandTotal').textContent  = String(grandTotal);
        document.getElementById('rackGrandGood').textContent   = String(grandGood);
        document.getElementById('rackGrandDefect').textContent = String(grandDefect);
        document.getElementById('rackGrandDamaged').textContent= String(grandDamaged);

        // footer totals
        document.getElementById('rackFootTotal').textContent   = String(grandTotal);
        document.getElementById('rackFootGood').textContent    = String(grandGood);
        document.getElementById('rackFootDefect').textContent  = String(grandDefect);
        document.getElementById('rackFootDamaged').textContent = String(grandDamaged);
    })
    .catch(err => {
        console.error(err);
        document.getElementById('rackDetailBody').innerHTML =
            '<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>';
    });
}

// ✅ Quality report (defect / damaged)
function openQualityModal(type, productId, warehouseId, isAllBranchMode) {
    const titleEl = document.getElementById('qualityModalTitle');
    const bodyEl = document.getElementById('qualityBody');
    const typeColEl = document.getElementById('qualityTypeCol');

    const typeUpper = (type || '').toUpperCase();

    titleEl.textContent = `Quality Report - ${typeUpper}`;
    bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Memuat data...</td></tr>';

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

    fetch(`/inventory/stocks/quality-details/${type}/${productId}?` + qs.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(res => {
        bodyEl.innerHTML = '';

        if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>';
            return;
        }

        res.data.forEach((row, idx) => {
            const branch = escapeHtml(row.branch_name ?? '-');
            const wh = escapeHtml(row.warehouse_name ?? '-');
            const qty = asInt(row.quantity ?? 0);

            let typeText = '-';
            let descText = '-';

            if (type === 'defect') {
                typeText = escapeHtml(row.defect_type ?? '-');
                descText = escapeHtml(row.description ?? '-');
            } else {
                typeText = escapeHtml(row.reason ?? '-');
                descText = escapeHtml(row.reason ?? '-');
            }

            const photo = row.photo_url
                ? `<a href="${row.photo_url}" target="_blank" title="Open Photo">
                     <img src="${row.photo_url}" style="height:45px;width:auto;border-radius:8px;border:1px solid #e5e7eb;">
                   </a>`
                : '<span class="text-muted">-</span>';

            const created = escapeHtml(row.created_at ?? '-');

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
        bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Gagal memuat data.</td></tr>';
    });
}
</script>
@endpush
