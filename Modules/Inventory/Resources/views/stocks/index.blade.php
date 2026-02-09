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

            {{-- ✅ INFO: Reserved / Incoming pool --}}
            <div class="alert alert-info alert-modern py-2 mb-3">
                <div class="d-flex align-items-start" style="gap:10px;">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        <div class="font-weight-bold">Catatan Reserved & Incoming</div>
                        <div class="small">
                            Reserved dan Incoming dicatat pada baris
                            <span class="badge badge-primary badge-pill px-3 py-1">All Warehouses</span>
                            (pool level cabang), karena gudang & rack baru dipilih saat proses
                            <b>Sale Delivery Confirm</b>. Baris per gudang menampilkan stok fisik gudang.
                        </div>
                    </div>
                </div>
            </div>

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

                {{-- BS5 close (optional) --}}
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
                        <thead class="thead-light">
                            <tr>
                                <th style="width:120px;">Kode Rak</th>
                                <th>Nama Rak</th>
                                <th class="text-right" style="width:110px;">Total</th>
                                <th class="text-right" style="width:110px;">Good</th>
                                <th class="text-right" style="width:110px;">Defect</th>
                                <th class="text-right" style="width:110px;">Damaged</th>
                            </tr>
                        </thead>

                        <tbody id="rackDetailBody">
                            <tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>
                        </tbody>

                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="2" class="text-right">Grand Total</td>
                                <td class="text-right" id="rackFootTotal">0</td>
                                <td class="text-right" id="rackFootGood">0</td>
                                <td class="text-right" id="rackFootDefect">0</td>
                                <td class="text-right" id="rackFootDamaged">0</td>
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

                {{-- BS5 close (optional) --}}
                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="small text-muted mb-2" id="qualityModalHint">
                    Menampilkan detail defect/damaged per cabang & gudang + foto.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:180px;">Cabang</th>
                                <th style="width:180px;">Gudang</th>
                                <th style="width:90px;" class="text-right">Qty</th>
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

    .alert-modern{
        border-radius: 12px;
        border: 1px solid rgba(59, 130, 246, .25);
        background: rgba(59, 130, 246, .08);
    }

    /* ✅ FIX: summary row (All Warehouses) soft highlight WITHOUT ugly border */
    table.dataTable tbody tr.table-primary,
    table.dataTable tbody tr.table-primary td,
    table.dataTable tbody tr.table-primary th{
        background: rgba(59, 130, 246, .06) !important;
        border-color: #e2e8f0 !important;
        box-shadow: none !important;
        outline: none !important;
    }
    table.dataTable tbody tr.table-primary td{
        border-top-color: #dbeafe !important;
        border-bottom-color: #dbeafe !important;
    }
</style>
@endpush

@push('page_scripts')
{!! $dataTable->scripts() !!}

<script>
function openModal(modalId) {
    var modalEl = document.getElementById(modalId);

    // 1) Bootstrap 5
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            return true;
        }
    } catch(e) {}

    // 2) CoreUI
    try {
        if (typeof coreui !== 'undefined' && coreui.Modal) {
            var modal2 = coreui.Modal.getOrCreateInstance(modalEl);
            modal2.show();
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
    var n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function resetRackTotals(){
    var ids = [
        'rackGrandTotal','rackGrandGood','rackGrandDefect','rackGrandDamaged',
        'rackFootTotal','rackFootGood','rackFootDefect','rackFootDamaged'
    ];
    ids.forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.textContent = '0';
    });
}

// ✅ Rack detail modal
function showRackDetails(productId, branchId, warehouseId) {
    document.getElementById('rackDetailBody').innerHTML =
        '<tr><td colspan="6" class="text-center text-muted">Memuat data...</td></tr>';

    resetRackTotals();

    var hintEl = document.getElementById('rackModalHint');
    if (hintEl) hintEl.textContent = 'PID: ' + productId + ' | WH: ' + warehouseId;

    var opened = openModal('rackModal');
    if (!opened) {
        alert('Modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    fetch('/inventory/stocks/rack-details/' + productId + '/' + branchId + '/' + warehouseId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        var body = document.getElementById('rackDetailBody');
        body.innerHTML = '';

        if (!res || !res.success || !Array.isArray(res.data) || res.data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data rak.</td></tr>';
            return;
        }

        var grandTotal = 0, grandGood = 0, grandDefect = 0, grandDamaged = 0;

        res.data.forEach(function(item){
            var code = escapeHtml(item.rack_code || '-');
            var name = escapeHtml(item.rack_name || '-');

            var qt = asInt(item.qty_total);
            var qg = asInt(item.qty_good);
            var qd = asInt(item.qty_defect);
            var qx = asInt(item.qty_damaged);

            grandTotal += qt;
            grandGood += qg;
            grandDefect += qd;
            grandDamaged += qx;

            body.innerHTML += ''
                + '<tr>'
                + '  <td><span class="badge badge-dark">' + code + '</span></td>'
                + '  <td>' + name + '</td>'
                + '  <td class="text-right font-weight-bold">' + qt + '</td>'
                + '  <td class="text-right"><span class="badge badge-success">' + qg + '</span></td>'
                + '  <td class="text-right"><span class="badge badge-warning text-dark">' + qd + '</span></td>'
                + '  <td class="text-right"><span class="badge badge-danger">' + qx + '</span></td>'
                + '</tr>';
        });

        document.getElementById('rackGrandTotal').textContent  = String(grandTotal);
        document.getElementById('rackGrandGood').textContent   = String(grandGood);
        document.getElementById('rackGrandDefect').textContent = String(grandDefect);
        document.getElementById('rackGrandDamaged').textContent= String(grandDamaged);

        document.getElementById('rackFootTotal').textContent   = String(grandTotal);
        document.getElementById('rackFootGood').textContent    = String(grandGood);
        document.getElementById('rackFootDefect').textContent  = String(grandDefect);
        document.getElementById('rackFootDamaged').textContent = String(grandDamaged);
    })
    .catch(function(err){
        console.error(err);
        document.getElementById('rackDetailBody').innerHTML =
            '<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>';
    });
}

// ✅ Quality report (defect / damaged)
function openQualityModal(type, productId) {
    var titleEl = document.getElementById('qualityModalTitle');
    var bodyEl = document.getElementById('qualityBody');
    var typeColEl = document.getElementById('qualityTypeCol');

    var typeUpper = String(type || '').toUpperCase();

    titleEl.textContent = 'Quality Report - ' + typeUpper;
    bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Memuat data...</td></tr>';

    if (type === 'defect') typeColEl.textContent = 'Defect Type';
    else typeColEl.textContent = 'Reason';

    var opened = openModal('qualityModal');
    if (!opened) {
        alert('Quality modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    var whSelected = (document.querySelector('select[name="warehouse_id"]') || {}).value || '';
    var qs = new URLSearchParams();
    if (whSelected) qs.set('warehouse_id', whSelected);

    fetch('/inventory/stocks/quality-details/' + type + '/' + productId + '?' + qs.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        bodyEl.innerHTML = '';

        if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>';
            return;
        }

        res.data.forEach(function(row, idx){
            var branch = escapeHtml(row.branch_name || '-');
            var wh = escapeHtml(row.warehouse_name || '-');
            var qty = asInt(row.quantity || 0);

            var typeText = '-';
            var descText = '-';

            if (type === 'defect') {
                typeText = escapeHtml(row.defect_type || '-');
                descText = escapeHtml(row.description || '-');
            } else {
                typeText = escapeHtml(row.reason || '-');
                descText = escapeHtml(row.reason || '-');
            }

            var photo = row.photo_url
                ? '<a href="' + row.photo_url + '" target="_blank" title="Open Photo">'
                    + '<img src="' + row.photo_url + '" style="height:45px;width:auto;border-radius:8px;border:1px solid #e5e7eb;">'
                  + '</a>'
                : '<span class="text-muted">-</span>';

            var created = escapeHtml(row.created_at || '-');

            bodyEl.innerHTML += ''
                + '<tr>'
                + '  <td>' + (idx+1) + '</td>'
                + '  <td>' + branch + '</td>'
                + '  <td>' + wh + '</td>'
                + '  <td class="text-right">' + qty + '</td>'
                + '  <td>' + typeText + '</td>'
                + '  <td>' + descText + '</td>'
                + '  <td class="text-center">' + photo + '</td>'
                + '  <td>' + created + '</td>'
                + '</tr>';
        });
    })
    .catch(function(err){
        console.error(err);
        bodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Gagal memuat data.</td></tr>';
    });
}
</script>
@endpush
