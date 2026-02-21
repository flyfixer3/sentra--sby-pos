@extends('layouts.app')

@section('title', 'Create Adjustment')

@push('page_css')
    @livewireStyles
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">Adjustments</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid mb-4">

    <div class="row">
        <div class="col-12">
            {{-- Search Product JANGAN DIGANTI --}}
            <livewire:search-product/>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">

            <div class="card">
                <div class="card-header d-flex align-items-start justify-content-between">
                    <div>
                        <div class="font-weight-bold">Create Adjustment</div>
                        <div class="text-muted small">
                            Mode 1: Stock add/sub (creates mutation). Mode 2: Quality reclass GOOD ↔ defect/damaged (net-zero).
                        </div>
                    </div>

                    <span class="badge badge-pill badge-light border px-3 py-2">
                        <i class="bi bi-diagram-3"></i>
                        Active Branch: {{ $activeBranchId }}
                    </span>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    {{-- TAB (BS4) --}}
                    <ul class="nav nav-pills mb-3" id="adjustmentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active"
                               id="tab-stock"
                               data-toggle="pill"
                               href="#pane-stock"
                               role="tab"
                               aria-controls="pane-stock"
                               aria-selected="true">
                                <i class="bi bi-arrow-left-right"></i> Stock Adjustment
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link"
                               id="tab-quality"
                               data-toggle="pill"
                               href="#pane-quality"
                               role="tab"
                               aria-controls="pane-quality"
                               aria-selected="false">
                                <i class="bi bi-shield-check"></i> Quality Reclass
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="adjustmentTabsContent">

                        {{-- =========================
                            TAB 1: STOCK
                           ========================= --}}
                        <div class="tab-pane fade show active" id="pane-stock" role="tabpanel" aria-labelledby="tab-stock">

                            <div class="alert alert-light border mb-3">
                                <div class="font-weight-bold mb-1">Stock Adjustment</div>
                                <div class="text-muted small">
                                    Pilih dulu <b>Adjustment Type</b> (ADD / SUB). Untuk ADD, UI mengikuti format Transfer::Confirm (Good split racks + per-unit defect/damaged).
                                </div>
                            </div>

                            <form action="{{ route('adjustments.store') }}" method="POST" id="adjustmentAddForm" enctype="multipart/form-data">
                                @csrf

                                <div class="form-row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="font-weight-bold">Reference <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="reference" readonly value="ADJ">
                                            <small class="text-muted">Auto-generate by system after submit.</small>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="font-weight-bold">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                                            <select name="warehouse_id" id="warehouse_id_stock" class="form-control" required>
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ (int)$defaultWarehouseId === (int)$wh->id ? 'selected' : '' }}>
                                                        {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Showing warehouses for active branch (ID: {{ $activeBranchId }})</small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Adjustment type selector --}}
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold d-block mb-2">Adjustment Type <span class="text-danger">*</span></label>

                                    <div class="btn-group btn-group-toggle" data-toggle="buttons" id="adjTypeToggle">
                                        <label class="btn btn-outline-primary active">
                                            <input type="radio" name="adjustment_type" value="add" autocomplete="off" checked>
                                            ADD (Receive / Add Stock)
                                        </label>
                                        <label class="btn btn-outline-secondary">
                                            <input type="radio" name="adjustment_type" value="sub" autocomplete="off">
                                            SUB (Reduce Stock)
                                        </label>
                                    </div>

                                    <small class="text-muted d-block mt-2">
                                        Untuk saat ini kita fokus dulu implement <b>ADD</b> sesuai UI Transfer::Confirm.
                                    </small>
                                </div>

                                {{-- ADD UI --}}
                                <div id="stockAddWrap">
                                    {{-- Livewire stock table (NEW UI) --}}
                                    <livewire:adjustment.product-table-stock mode="stock_add" :warehouseId="$defaultWarehouseId"/>
                                </div>

                                {{-- SUB placeholder --}}
                                <div id="stockSubWrap">
                                    <livewire:adjustment.product-table-stock-sub />
                                </div>

                                <hr>

                                <div class="form-group">
                                    <label class="font-weight-bold">Note (If Needed)</label>
                                    <textarea name="note" id="note" rows="4" class="form-control" placeholder="Optional note..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="submitStockAdd()">
                                        Create Adjustment <i class="bi bi-check"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- =========================
                            TAB 2: QUALITY
                            ⚠️ PERSIS seperti yang kamu punya sekarang (JANGAN DIUBAH)
                           ========================= --}}
                        <div class="tab-pane fade" id="pane-quality" role="tabpanel" aria-labelledby="tab-quality">

                            <div class="alert alert-info border mb-3">
                                <div class="sa-help mb-1"><b>Info:</b> GOOD = TOTAL - defect - damaged (warehouse yang dipilih).</div>
                                <div class="sa-help">
                                    Reclass ini akan mengubah bucket di <b>StockRack</b> (GOOD/DEFECT/DAMAGED) tapi total stok tetap net-zero.
                                    <br>✅ Pilih <b>Rack</b> per item (di tabel) seperti Stock Adjustment.
                                </div>
                            </div>

                            @php
                                $defaultQualityWarehouseId = (int) ($defaultWarehouseId ?: optional($warehouses->first())->id);
                            @endphp

                            <form id="qualityForm" method="POST" action="{{ route('adjustments.quality.store') }}" enctype="multipart/form-data">
                                @csrf

                                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">

                                <div class="form-row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="sa-form-label">Warehouse <span class="text-danger">*</span></label>

                                            <select id="warehouse_id_quality" class="form-control" required>
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ (int)$defaultQualityWarehouseId === (int)$wh->id ? 'selected' : '' }}>
                                                        {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <input type="hidden" name="warehouse_id" id="quality_warehouse_id" value="{{ $defaultQualityWarehouseId }}">

                                            <small class="text-muted">
                                                Warehouse untuk Quality tab <b>terpisah</b> dari Stock tab.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="sa-form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type" id="quality_type" class="form-control" required>
                                                <optgroup label="GOOD → Quality Issue">
                                                    <option value="defect">Defect (GOOD → DEFECT)</option>
                                                    <option value="damaged">Damaged (GOOD → DAMAGED)</option>
                                                </optgroup>
                                                <optgroup label="Quality Issue → GOOD">
                                                    <option value="defect_to_good">Defect → Good (DELETE defect rows)</option>
                                                    <option value="damaged_to_good">Damaged → Good (DELETE damaged rows)</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label class="sa-form-label">Summary</label>
                                            <div class="d-flex align-items-center gap-2" style="gap:10px;">
                                                <span class="sa-mini-badge">
                                                    <i class="bi bi-box"></i>
                                                    <span id="quality_selected_product_text">No product selected</span>
                                                </span>

                                                <span class="sa-mini-badge">
                                                    <i class="bi bi-hash"></i>
                                                    Qty: <b id="quality_total_qty">0</b>
                                                </span>
                                            </div>
                                            <small class="text-muted">Product & Qty diambil dari tabel list di bawah.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-divider"></div>

                                <livewire:adjustment.product-table-quality mode="quality" :warehouseId="$defaultQualityWarehouseId"/>

                                <input type="hidden" name="product_id" id="quality_product_id" value="">
                                <input type="hidden" name="qty" id="quality_qty" value="0">

                                <div class="sa-divider"></div>

                                <div class="unit-card mt-3">
                                    <div class="unit-head d-flex align-items-center justify-content-between">
                                        <div>Per-Unit Details & Photo (Optional)</div>
                                        <div class="text-muted"><small>Auto-build dari Qty + Type</small></div>
                                    </div>
                                    <div class="unit-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered unit-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width:60px" class="text-center">#</th>
                                                        <th id="unit_col_title">Defect Type / Reason *</th>
                                                        <th>Description (optional)</th>
                                                        <th style="width:220px">Photo (optional)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="unit_tbody"></tbody>
                                            </table>
                                        </div>
                                        <div class="text-muted mt-2">
                                            <small>
                                                - Defect wajib isi <b>Defect Type</b> (bubble / scratch / distortion).<br>
                                                - Damaged wajib isi <b>Reason</b> (pecah sudut / retak / shipping damage).<br>
                                                - Foto opsional, max 5MB.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        Submit Reclass <i class="bi bi-check"></i>
                                    </button>
                                </div>

                                <div class="mt-2 sa-help">
                                    Note: Remove/sell defect/damaged dilakukan di Inventory → Quality Details.
                                </div>
                            </form>

                        </div>
                    </div>{{-- tab content --}}
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    /* ===== style “transfer confirm vibe” ===== */
    .table-modern thead th {
        background: #f8fafc;
        font-weight: 600;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-modern tbody td { vertical-align: middle; }

    .btn-notes {
        background: #ffffff;
        border: 1px solid #dbeafe;
        color: #1d4ed8;
        border-radius: 999px;
        padding: 6px 12px;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        transition: all .15s ease;
    }
    .btn-notes:hover {
        background: #eff6ff;
        border-color: #93c5fd;
        color: #1e40af;
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(29,78,216,0.10);
    }

    .badge-defect { background: #2563eb; color: #fff; font-weight: 700; padding: 5px 8px; }
    .badge-damaged { background: #ef4444; color: #fff; font-weight: 700; padding: 5px 8px; }

    .perunit-td {
        background: #f1f5f9;
        border-top: 0 !important;
        padding: 14px !important;
    }

    .perunit-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }
    .perunit-card-header {
        padding: 12px 14px;
        background: linear-gradient(90deg, #f8fafc, #ffffff);
        border-bottom: 1px solid #e2e8f0;
    }
    .perunit-card-body { padding: 14px; }

    .section-title {
        font-weight: 800;
        font-size: 14px;
        margin-bottom: 10px;
        display: inline-block;
        padding: 6px 10px;
        border-radius: 10px;
    }
    .defect-title {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #dbeafe;
    }
    .damaged-title {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fee2e2;
    }

    .section-table thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
    }
    .section-table td, .section-table th {
        border-color: #e2e8f0 !important;
    }

    .row-status.badge-success { background: #16a34a; }
    .row-status.badge-danger  { background: #ef4444; }
    .row-status.badge-warning { background: #f59e0b; color: #111827; }
    .row-status.badge-secondary { background: #64748b; }

    .form-control-sm {
        border-radius: 10px;
        border-color: #e2e8f0;
    }
    .form-control-sm:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 0.2rem rgba(147,197,253,0.25);
    }

    .photo-input {
        border: 1px dashed #cbd5e1;
        padding: 6px;
        border-radius: 10px;
        background: #fff;
        width: 100%;
        font-size: 12px;
    }
</style>
@endpush

@push('page_scripts')
<script>
(function(){
    // ✅ IMPORTANT: racks mapping for stock table
    window.RACKS_BY_WAREHOUSE = @json($racksByWarehouse ?? []);

    function submitStockAdd(){
        const type = document.querySelector('input[name="adjustment_type"]:checked')?.value;
        if(type !== 'add'){
            alert('SUB belum aktif.');
            return;
        }

        const wh = document.getElementById('warehouse_id_stock')?.value;
        if(!wh){
            alert('Pilih warehouse dulu.');
            return;
        }

        // optional validate row status (dari product-table-stock)
        if(typeof window.validateAllAdjustmentRows === 'function'){
            const ok = window.validateAllAdjustmentRows();
            if(!ok){
                alert('Masih ada item yang belum lengkap. Tolong cek status per item (NEED INFO).');
                return;
            }
        }

        document.getElementById('adjustmentAddForm').submit();
    }
    window.submitStockAdd = submitStockAdd;

    // ================= QUALITY =================
    function syncQualityWarehouse(){
        const wq = document.getElementById('warehouse_id_quality');
        const hidden = document.getElementById('quality_warehouse_id');
        if(!wq || !hidden) return;

        hidden.value = wq.value;

        if(window.Livewire){
            Livewire.emit('qualityWarehouseChanged', parseInt(wq.value));
        }
    }

    // ✅ CHANGED: untuk type === 'damaged' pakai dropdown damaged|missing
    function buildUnits(qty, type){
        const tbody = document.getElementById('unit_tbody');
        const title = document.getElementById('unit_col_title');
        if(!tbody || !title) return;

        qty = parseInt(qty || 0);

        let key = 'defect_type';
        let placeholder = 'bubble / scratch';

        // default: defect input textbox
        // khusus damaged (GOOD -> DAMAGED): dropdown damaged/missing (name tetap units[i][reason])
        const isDamagedDropdown = (type === 'damaged');

        if(type === 'damaged'){
            key = 'reason';
            placeholder = 'damaged';
        }

        title.innerText = type.toUpperCase() + ' *';
        tbody.innerHTML = '';

        for(let i=0;i<qty;i++){

            let firstColHtml = `
                <input name="units[${i}][${key}]"
                       class="form-control form-control-sm"
                       required
                       placeholder="${placeholder}">
            `;

            if(isDamagedDropdown){
                firstColHtml = `
                    <select name="units[${i}][reason]" class="form-control form-control-sm" required>
                        <option value="damaged">damaged</option>
                        <option value="missing">missing</option>
                    </select>
                `;
            }

            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${i+1}</td>
                    <td>
                        ${firstColHtml}
                    </td>
                    <td>
                        <textarea name="units[${i}][description]"
                                  class="form-control form-control-sm"></textarea>
                    </td>
                    <td>
                        <input type="file"
                               name="units[${i}][photo]"
                               class="form-control form-control-sm">
                    </td>
                </tr>
            `);
        }
    }

    window.addEventListener('quality-table-updated', function(e){
        const d = e.detail || {};

        const pid = document.getElementById('quality_product_id');
        const qty = document.getElementById('quality_qty');
        if(pid) pid.value = d.product_id || '';
        if(qty) qty.value = d.qty || 0;

        buildUnits(d.qty, document.getElementById('quality_type')?.value || 'defect');
    });

    document.addEventListener('DOMContentLoaded', function () {
        // ===== STOCK init =====
        const stockWh = document.getElementById('warehouse_id_stock');
        if (stockWh && window.Livewire) {
            Livewire.emit('stockWarehouseChanged', parseInt(stockWh.value));
            stockWh.addEventListener('change', () => {
                Livewire.emit('stockWarehouseChanged', parseInt(stockWh.value));
            });
        }

        // ===== QUALITY init =====
        const qualityWh = document.getElementById('warehouse_id_quality');
        if (qualityWh && window.Livewire) {
            Livewire.emit('qualityWarehouseChanged', parseInt(qualityWh.value));
            qualityWh.addEventListener('change', () => {
                Livewire.emit('qualityWarehouseChanged', parseInt(qualityWh.value));
            });
        }

        // ✅ FIX: form id yang bener
        const form = document.querySelector('form#adjustmentAddForm');
        if(form){
            form.addEventListener('submit', function(e){
                if(typeof window.validateAllAdjustmentRows === 'function'){
                    const ok = window.validateAllAdjustmentRows();
                    if(!ok){
                        e.preventDefault();
                        alert('Masih ada item yang belum lengkap. Tolong cek status per item (NEED INFO).');
                    }
                }
            });
        }
    });

    document.getElementById('warehouse_id_quality')
        ?.addEventListener('change', syncQualityWarehouse);

    document.getElementById('quality_type')
        ?.addEventListener('change', function(){
            buildUnits(
                document.getElementById('quality_qty')?.value || 0,
                this.value
            );

            if(window.Livewire){
                Livewire.emit('qualityTypeChanged', this.value);
            }
        });

})();
</script>
@endpush