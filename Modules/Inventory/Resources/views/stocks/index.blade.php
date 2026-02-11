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
                    <div class="page-subtitle text-muted">
                        Row yang ditampilkan adalah <b>All Warehouses</b> (pool cabang). Detail fisik gudang/rak lihat dari tombol <b>View Detail</b>.
                    </div>
                </div>
            </div>

            <div class="divider-soft mb-3"></div>

            <div class="alert alert-info alert-modern py-2 mb-3">
                <div class="d-flex align-items-start" style="gap:10px;">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        <div class="font-weight-bold">Catatan Reserved & Incoming</div>
                        <div class="small">
                            <b>Reserved</b> dan <b>Incoming</b> dicatat pada level <span class="badge badge-primary badge-pill px-3 py-1">All Warehouses</span>
                            (pool cabang), karena gudang & rak baru dipilih saat proses <b>Sale Delivery Confirm</b>.
                            Detail fisik gudang/rak bisa dilihat di <b>View Detail</b>.
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('inventory.stocks.index') }}" class="row g-2 align-items-end mb-3">

                @if($isAllBranchMode)
                    <div class="col-md-4">
                        <label class="small text-muted mb-1 d-block">Filter Branch</label>
                        <select name="branch_id" class="form-control form-control-modern">
                            <option value="">-- All Branches --</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ (string)request('branch_id') === (string)$b->id ? 'selected' : '' }}>
                                    {{ $b->name ?? ('Branch #' . $b->id) }}
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
                @else
                    <div class="col-md-10">
                        <label class="small text-muted mb-1 d-block">Cari Produk</label>
                        <input type="text" name="product" class="form-control form-control-modern"
                               placeholder="Cari produk / kode" value="{{ request('product') }}">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-primary btn-modern w-100">Filter</button>
                    </div>
                @endif
            </form>

            <div class="table-wrap">
                {!! $dataTable->table(['class' => 'table table-striped table-bordered w-100 table-modern'], true) !!}
            </div>
        </div>
    </div>
</div>

{{-- ✅ Stock Detail Modal (warehouse/rack/condition) --}}
<div class="modal fade" id="stockDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Stock Detail</h5>
                    <div class="small text-muted" id="stockDetailHint">-</div>
                </div>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
                    <span aria-hidden="true">&times;</span>
                </button>

                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:10px;">
                    <div class="small text-muted">
                        Filter detail stok berdasarkan <b>Warehouse / Rack / Condition</b> (Good/Defect/Damaged).
                    </div>

                    <div class="d-flex flex-wrap" style="gap:6px;">
                        <span class="badge badge-info">
                            Reserved: <span id="modalReservedBadge">0</span>
                        </span>
                        <span class="badge badge-info">
                            Incoming: <span id="modalIncomingBadge">0</span>
                        </span>
                        <span class="badge badge-secondary">
                            Total Qty: <span id="modalTotalQty">0</span>
                        </span>
                    </div>
                </div>

                <div class="p-2 border rounded mb-3" style="background:#f8fafc;">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Warehouse</label>
                            <select id="sdWarehouse" class="form-control form-control-modern">
                                <option value="">All Warehouses</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Rack</label>
                            <select id="sdRack" class="form-control form-control-modern">
                                <option value="">All Racks</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Condition</label>
                            <select id="sdCondition" class="form-control form-control-modern">
                                <option value="">All Conditions</option>
                                <option value="good">Good</option>
                                <option value="defect">Defect</option>
                                <option value="damaged">Damaged</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-2" style="gap:8px;">
                        <button class="btn btn-outline-secondary btn-modern" type="button" onclick="resetStockDetailFilters()">
                            Reset
                        </button>
                        <button class="btn btn-primary btn-modern" type="button" onclick="reloadStockDetailTable()">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 420px; overflow:auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:200px;">Warehouse</th>
                                <th style="width:160px;">Rack</th>
                                <th style="width:140px;">Condition</th>
                                <th class="text-right" style="width:110px;">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="stockDetailBody">
                            <tr><td colspan="4" class="text-center text-muted">Memuat data...</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td colspan="3" class="text-right">Grand Total</td>
                                <td class="text-right" id="stockDetailFootTotal">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    Catatan: <b>Reserved/Incoming</b> adalah pool cabang (All Warehouses) dan tampil sebagai badge di atas.
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
                <div>
                    <h5 class="modal-title" id="qualityModalTitle">Quality Report</h5>
                    <div class="small text-muted">Optional: bisa filter Warehouse/Rack untuk mempersempit data.</div>
                </div>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
                    <span aria-hidden="true">&times;</span>
                </button>

                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <div class="p-2 border rounded mb-3" style="background:#f8fafc;">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Warehouse</label>
                            <select id="qWarehouse" class="form-control form-control-modern">
                                <option value="">All Warehouses</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Rack</label>
                            <select id="qRack" class="form-control form-control-modern">
                                <option value="">All Racks</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary btn-modern w-100" type="button" onclick="reloadQualityTable()">
                                Apply Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:180px;">Branch</th>
                                <th style="width:180px;">Warehouse</th>
                                <th style="width:160px;">Rack</th>
                                <th style="width:90px;" class="text-right">Qty</th>
                                <th id="qualityTypeCol" style="width:180px;">Type</th>
                                <th>Desc / Reason</th>
                                <th style="width:140px;" class="text-center">Photo</th>
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

    .alert-modern{
        border-radius: 12px;
        border: 1px solid rgba(59, 130, 246, .25);
        background: rgba(59, 130, 246, .08);
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

var sdProductId = 0;
var sdBranchId = 0;
var qType = '';
var qProductId = 0;
var qBranchId = 0;
var qIsAll = 0;

function resetStockDetailUI(){
    document.getElementById('stockDetailBody').innerHTML =
        '<tr><td colspan="4" class="text-center text-muted">Memuat data...</td></tr>';

    document.getElementById('stockDetailFootTotal').textContent = '0';
    document.getElementById('modalTotalQty').textContent = '0';

    // dropdown reset
    document.getElementById('sdWarehouse').innerHTML = '<option value="">All Warehouses</option>';
    document.getElementById('sdRack').innerHTML = '<option value="">All Racks</option>';
    document.getElementById('sdCondition').value = '';
}

function resetQualityUI(){
    document.getElementById('qualityBody').innerHTML =
        '<tr><td colspan="9" class="text-center text-muted">Memuat data...</td></tr>';
    document.getElementById('qWarehouse').innerHTML = '<option value="">All Warehouses</option>';
    document.getElementById('qRack').innerHTML = '<option value="">All Racks</option>';
}

function resetStockDetailFilters(){
    document.getElementById('sdWarehouse').value = '';
    document.getElementById('sdRack').value = '';
    document.getElementById('sdCondition').value = '';
    reloadStockDetailTable();
}

function fillOptions(selectEl, items, placeholderAllText){
    var html = '<option value="">' + placeholderAllText + '</option>';
    (items || []).forEach(function(it){
        html += '<option value="' + escapeHtml(it.value) + '">' + escapeHtml(it.label) + '</option>';
    });
    selectEl.innerHTML = html;
}

// ✅ buka modal stock detail dari row datatable
function showStockDetail(productId, branchId, reserved, incoming){
    sdProductId = asInt(productId);
    sdBranchId = asInt(branchId);

    document.getElementById('modalReservedBadge').textContent = String(asInt(reserved));
    document.getElementById('modalIncomingBadge').textContent = String(asInt(incoming));

    var hintEl = document.getElementById('stockDetailHint');
    if (hintEl) hintEl.textContent = 'PID: ' + sdProductId + ' | Branch: ' + sdBranchId;

    resetStockDetailUI();

    var opened = openModal('stockDetailModal');
    if (!opened) {
        alert('Modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    // load options + data
    loadStockDetailOptionsAndData();
}

function loadStockDetailOptionsAndData(){
    var qs = new URLSearchParams();
    qs.set('product_id', sdProductId);
    qs.set('branch_id', sdBranchId);

    fetch('/inventory/stocks/detail/options?' + qs.toString(), {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (!res || !res.success) return;

        fillOptions(document.getElementById('sdWarehouse'), res.warehouses, 'All Warehouses');
        fillOptions(document.getElementById('sdRack'), res.racks, 'All Racks');

        // after options ready, load table
        reloadStockDetailTable();
    })
    .catch(function(e){
        console.error(e);
        reloadStockDetailTable();
    });
}

function reloadStockDetailTable(){
    var body = document.getElementById('stockDetailBody');
    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Memuat data...</td></tr>';

    var qs = new URLSearchParams();
    qs.set('product_id', sdProductId);
    qs.set('branch_id', sdBranchId);

    var wh = (document.getElementById('sdWarehouse') || {}).value || '';
    var rack = (document.getElementById('sdRack') || {}).value || '';
    var cond = (document.getElementById('sdCondition') || {}).value || '';

    if (wh) qs.set('warehouse_id', wh);
    if (rack) qs.set('rack_id', rack);
    if (cond) qs.set('condition', cond);

    fetch('/inventory/stocks/detail/data?' + qs.toString(), {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        body.innerHTML = '';

        if (!res || !res.success || !Array.isArray(res.data) || res.data.length === 0) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>';
            document.getElementById('stockDetailFootTotal').textContent = '0';
            document.getElementById('modalTotalQty').textContent = '0';
            return;
        }

        var total = 0;

        res.data.forEach(function(row){
            var whName = escapeHtml(row.warehouse_name || '-');
            var rackName = escapeHtml(row.rack_name || '-');
            var condText = escapeHtml(row.condition_label || '-');
            var qty = asInt(row.qty || 0);

            total += qty;

            body.innerHTML += ''
                + '<tr>'
                + '  <td>' + whName + '</td>'
                + '  <td><span class="badge badge-dark">' + rackName + '</span></td>'
                + '  <td>' + condText + '</td>'
                + '  <td class="text-right font-weight-bold">' + qty + '</td>'
                + '</tr>';
        });

        document.getElementById('stockDetailFootTotal').textContent = String(total);
        document.getElementById('modalTotalQty').textContent = String(total);
    })
    .catch(function(e){
        console.error(e);
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Gagal memuat data.</td></tr>';
        document.getElementById('stockDetailFootTotal').textContent = '0';
        document.getElementById('modalTotalQty').textContent = '0';
    });
}

// ✅ Quality modal open
function openQualityModal(type, productId, branchId, isAll){
    qType = String(type || '');
    qProductId = asInt(productId);
    qBranchId = asInt(branchId);
    qIsAll = asInt(isAll);

    var titleEl = document.getElementById('qualityModalTitle');
    var bodyEl = document.getElementById('qualityBody');
    var typeColEl = document.getElementById('qualityTypeCol');

    var typeUpper = qType.toUpperCase();
    titleEl.textContent = 'Quality Report - ' + typeUpper;

    if (qType === 'defect') typeColEl.textContent = 'Defect Type';
    else typeColEl.textContent = 'Reason';

    resetQualityUI();

    var opened = openModal('qualityModal');
    if (!opened) {
        alert('Quality modal tidak bisa dibuka: JS modal (bootstrap/coreui) belum ke-load.');
        return;
    }

    // load options first, then data
    loadQualityOptionsAndData();
}

function loadQualityOptionsAndData(){
    var qs = new URLSearchParams();
    qs.set('type', qType);
    qs.set('product_id', qProductId);
    qs.set('branch_id', qBranchId);

    fetch('/inventory/stocks/quality/options?' + qs.toString(), {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res && res.success) {
            fillOptions(document.getElementById('qWarehouse'), res.warehouses, 'All Warehouses');
            fillOptions(document.getElementById('qRack'), res.racks, 'All Racks');
        }
        reloadQualityTable();
    })
    .catch(function(e){
        console.error(e);
        reloadQualityTable();
    });
}

function reloadQualityTable(){
    var bodyEl = document.getElementById('qualityBody');
    bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Memuat data...</td></tr>';

    var qs = new URLSearchParams();
    qs.set('branch_id', qBranchId);

    var wh = (document.getElementById('qWarehouse') || {}).value || '';
    var rack = (document.getElementById('qRack') || {}).value || '';

    if (wh) qs.set('warehouse_id', wh);
    if (rack) qs.set('rack_id', rack);

    fetch('/inventory/stocks/quality-details/' + qType + '/' + qProductId + '?' + qs.toString(), {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        bodyEl.innerHTML = '';

        if (!res || !res.success || !Array.isArray(res.data) || res.data.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Tidak ada data.</td></tr>';
            return;
        }

        res.data.forEach(function(row, idx){
            var branch = escapeHtml(row.branch_name || '-');
            var whName = escapeHtml(row.warehouse_name || '-');
            var rackName = escapeHtml(row.rack_name || '-');
            var qty = asInt(row.quantity || 0);

            var typeText = '-';
            var descText = '-';

            if (qType === 'defect') {
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
                + '  <td>' + whName + '</td>'
                + '  <td><span class="badge badge-dark">' + rackName + '</span></td>'
                + '  <td class="text-right">' + qty + '</td>'
                + '  <td>' + typeText + '</td>'
                + '  <td>' + descText + '</td>'
                + '  <td class="text-center">' + photo + '</td>'
                + '  <td>' + created + '</td>'
                + '</tr>';
        });
    })
    .catch(function(e){
        console.error(e);
        bodyEl.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Gagal memuat data.</td></tr>';
    });
}
</script>
@endpush
