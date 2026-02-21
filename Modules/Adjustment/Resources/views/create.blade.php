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

                                    {{-- ✅ Warehouse header: ONLY for ADD --}}
                                    <div class="col-lg-4" id="stockWarehouseHeader">
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
                                        <label class="btn btn-outline-primary active" id="lbl_adj_add">
                                            <input type="radio" name="adjustment_type" value="add" autocomplete="off" checked>
                                            ADD (Receive / Add Stock)
                                        </label>
                                        <label class="btn btn-outline-primary" id="lbl_adj_sub">
                                            <input type="radio" name="adjustment_type" value="sub" autocomplete="off">
                                            SUB (Reduce Stock)
                                        </label>
                                    </div>
                                </div>

                                {{-- ✅ ADD UI (DEFAULT ON) --}}
                                <div id="stockAddWrap">
                                    {{-- PAKAI KOMPONEN YANG SEKARANG KAMU PAKAI (JANGAN DITURUNKAN) --}}
                                    <livewire:adjustment.product-table-stock mode="stock_add" :warehouseId="$defaultWarehouseId"/>
                                </div>

                                {{-- ✅ SUB UI (DEFAULT OFF) --}}
                                <div id="stockSubWrap">
                                    <livewire:adjustment.product-table-stock-sub />
                                </div>

                                <hr>

                                <div class="form-group">
                                    <label class="font-weight-bold">Note (If Needed)</label>
                                    <textarea name="note" id="note" rows="4" class="form-control" placeholder="Optional note..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="submitStockForm()">
                                        <span id="btnSubmitText">Create Adjustment</span> <i class="bi bi-check"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- =========================
                            TAB 2: QUALITY
                           ========================= --}}
                        <div class="tab-pane fade" id="pane-quality" role="tabpanel" aria-labelledby="tab-quality">

                            <div class="alert alert-info border mb-3">
                                <div class="sa-help mb-1"><b>Info:</b> GOOD = TOTAL - defect - damaged (warehouse + rack yang dipilih).</div>
                                <div class="sa-help">
                                    Reclass ini akan mengubah bucket di <b>StockRack</b> (GOOD/DEFECT/DAMAGED) tapi total stok tetap net-zero.
                                    <br>✅ Pilih <b>Warehouse</b> & <b>Rack</b> dulu, lalu pilih product & qty.
                                </div>
                            </div>

                            @php
                                $defaultQualityWarehouseId = (int) ($defaultWarehouseId ?: optional($warehouses->first())->id);
                            @endphp

                            <form id="qualityForm" method="POST" action="{{ route('adjustments.quality.store') }}" enctype="multipart/form-data">
                                @csrf

                                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">

                                <div class="form-row">
                                    <div class="col-lg-4">
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

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Rack <span class="text-danger">*</span></label>

                                            <select id="quality_rack_select" class="form-control" required></select>

                                            <input type="hidden" name="rack_id" id="quality_rack_id" value="">

                                            <small class="text-muted">
                                                Rack wajib dipilih karena pergerakan bucket stock adalah per rack.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type" id="quality_type" class="form-control" required>
                                                <optgroup label="GOOD → Quality Issue">
                                                    <option value="defect">Defect (GOOD → DEFECT)</option>
                                                    <option value="damaged">Damaged (GOOD → DAMAGED)</option>
                                                </optgroup>
                                                <optgroup label="Quality Issue → GOOD">
                                                    <option value="defect_to_good">Defect → Good (PICK unit IDs, soft delete)</option>
                                                    <option value="damaged_to_good">Damaged → Good (PICK unit IDs, soft delete)</option>
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

                                                <span class="sa-mini-badge">
                                                    <i class="bi bi-grid-3x3-gap"></i>
                                                    Rack: <b id="quality_rack_text">-</b>
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
                                <input type="hidden" name="picked_unit_ids" id="quality_picked_unit_ids" value="">

                                <div class="sa-divider"></div>

                                <div class="form-group">
                                    <label class="font-weight-bold">User Note (Optional)</label>
                                    <textarea name="user_note" id="quality_user_note" rows="3" class="form-control"
                                              placeholder="Contoh: alasan reclass / info QC..."></textarea>
                                    <small class="text-muted">
                                        Untuk type <b>DEFECT/DAMAGED → GOOD</b>, note ini yang akan muncul di Adjustment detail (di note template).
                                    </small>
                                </div>

                                <div class="unit-card mt-3" id="quality_unit_card">
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
                                                        <th id="unit_col_title">Defect Type / Type *</th>
                                                        <th>Description (optional)</th>
                                                        <th style="width:220px">Photo (optional)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="unit_tbody"></tbody>
                                            </table>
                                        </div>
                                        <div class="text-muted mt-2" id="quality_unit_hint">
                                            <small>
                                                - Defect wajib isi <b>Defect Type</b> (bubble / scratch / distortion).<br>
                                                - Damaged: pilih <b>Type</b> (damaged / missing). Description opsional (boleh isi reason).<br>
                                                - Foto opsional, max 5MB.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-light border mt-3" id="quality_to_good_help" style="display:none;">
                                    <div class="font-weight-bold mb-1">Quality Issue → GOOD</div>
                                    <div class="text-muted small">
                                        Mode ini <b>Wajib pick unit IDs</b> (per unit) lewat modal/table Quality kamu.
                                        Setelah dipilih, sistem akan soft-delete (moved_out_at) unit tersebut dan membuat mutation net-zero.
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
    /* ✅ FIX UTAMA: default hanya ADD yang tampil */
    #stockSubWrap { display: none; }
</style>
@endpush

@push('page_scripts')
<script>
(function(){
    window.RACKS_BY_WAREHOUSE = @json($racksByWarehouse ?? []);

    // ✅ NEW: default stock warehouse id (buat restore value saat toggle ADD/SUB)
    window.DEFAULT_STOCK_WAREHOUSE_ID = {{ (int)($defaultStockWarehouseId ?? $defaultWarehouseId ?? 0) }};

    // ✅ NEW: remember last selected warehouse in Stock tab (biar UX enak)
    let LAST_STOCK_WAREHOUSE_ID = parseInt(window.DEFAULT_STOCK_WAREHOUSE_ID || 0);

    // =========================
    // STOCK: Toggle ADD/SUB UI
    // =========================
    function getAdjType(){
        return document.querySelector('input[name="adjustment_type"]:checked')?.value || 'add';
    }

    function ensureStockWarehouseSelected(){
        const select = document.getElementById('warehouse_id_stock');
        if(!select) return;

        const current = parseInt(select.value || 0);
        if(current > 0){
            LAST_STOCK_WAREHOUSE_ID = current;
            return;
        }

        // kalau kosong, restore last -> default
        const fallback = parseInt(LAST_STOCK_WAREHOUSE_ID || 0) || parseInt(window.DEFAULT_STOCK_WAREHOUSE_ID || 0);
        if(fallback > 0){
            select.value = String(fallback);
            LAST_STOCK_WAREHOUSE_ID = fallback;
        }
    }

    function emitStockWarehouseChangedIfAdd(){
        const select = document.getElementById('warehouse_id_stock');
        if(!select) return;

        const wid = parseInt(select.value || 0);
        if(wid > 0 && window.Livewire){
            Livewire.emit('stockWarehouseChanged', wid);
        }
    }

    function setStockWarehouseHeaderVisible(isAdd){
        const header = document.getElementById('stockWarehouseHeader');
        const select = document.getElementById('warehouse_id_stock');
        if(!header || !select) return;

        if(isAdd){
            header.style.display = '';
            select.required = true;

            // ✅ FIX: kalau sebelumnya SUB bikin value kosong, restore ke default/last
            ensureStockWarehouseSelected();
            emitStockWarehouseChangedIfAdd();

        }else{
            header.style.display = 'none';

            // IMPORTANT: untuk SUB, cukup cabut required
            // ❌ JANGAN kosongin value, karena nanti balik ADD jadi kehilangan selected option
            select.required = false;

            // simpan last selection
            const cur = parseInt(select.value || 0);
            if(cur > 0) LAST_STOCK_WAREHOUSE_ID = cur;
        }
    }

    function setStockModeUI(mode){
        const addWrap = document.getElementById('stockAddWrap');
        const subWrap = document.getElementById('stockSubWrap');
        const btnText = document.getElementById('btnSubmitText');

        if (!addWrap || !subWrap) return;

        if (mode === 'sub') {
            addWrap.style.display = 'none';
            subWrap.style.display = 'block';
            if (btnText) btnText.textContent = 'Create Adjustment (SUB)';

            // ✅ hide warehouse header in SUB
            setStockWarehouseHeaderVisible(false);

        } else {
            addWrap.style.display = 'block';
            subWrap.style.display = 'none';
            if (btnText) btnText.textContent = 'Create Adjustment (ADD)';

            // ✅ show warehouse header in ADD
            setStockWarehouseHeaderVisible(true);
        }
    }

    function bindAdjTypeToggle(){
        const radios = document.querySelectorAll('input[name="adjustment_type"]');
        if (!radios || radios.length === 0) return;

        radios.forEach(r => {
            r.addEventListener('change', function(){
                setStockModeUI(getAdjType());
            });
        });

        // init
        setStockModeUI(getAdjType());
    }

    // =========================
    // STOCK: submit handler
    // =========================
    function submitStockForm(){
        const type = getAdjType();

        // ✅ ONLY ADD needs warehouse header
        if(type === 'add'){
            ensureStockWarehouseSelected();

            const wh = document.getElementById('warehouse_id_stock')?.value;
            if(!wh){
                alert('Pilih warehouse dulu (ADD).');
                return;
            }

            if(typeof window.validateAllAdjustmentRows === 'function'){
                const ok = window.validateAllAdjustmentRows();
                if(!ok){
                    alert('Masih ada item yang belum lengkap. Tolong cek status per item (NEED INFO).');
                    return;
                }
            }
        }

        document.getElementById('adjustmentAddForm')?.submit();
    }
    window.submitStockForm = submitStockForm;

    // =========================
    // QUALITY: Warehouse + Rack sync
    // =========================
    function getRackOptionsByWarehouse(warehouseId){
        const map = window.RACKS_BY_WAREHOUSE || {};
        const wid = parseInt(warehouseId || 0);
        return Array.isArray(map[wid]) ? map[wid] : [];
    }

    function fillQualityRacks(warehouseId){
        const select = document.getElementById('quality_rack_select');
        const hidden = document.getElementById('quality_rack_id');
        const rackText = document.getElementById('quality_rack_text');
        if(!select || !hidden) return;

        const opts = getRackOptionsByWarehouse(warehouseId);

        select.innerHTML = '';
        if(opts.length === 0){
            select.insertAdjacentHTML('beforeend', `<option value="">-- No rack --</option>`);
            hidden.value = '';
            if(rackText) rackText.textContent = '-';
            return;
        }

        select.insertAdjacentHTML('beforeend', `<option value="">-- Select rack --</option>`);
        opts.forEach(o => {
            select.insertAdjacentHTML('beforeend', `<option value="${o.id}">${o.label}</option>`);
        });

        if(!hidden.value){
            const first = opts[0];
            select.value = String(first.id);
            hidden.value = String(first.id);
            if(rackText) rackText.textContent = first.label || ('Rack#' + first.id);
        }
    }

    function syncQualityWarehouse(){
        const wq = document.getElementById('warehouse_id_quality');
        const hidden = document.getElementById('quality_warehouse_id');
        if(!wq || !hidden) return;

        hidden.value = wq.value;
        fillQualityRacks(wq.value);

        if(window.Livewire){
            Livewire.emit('qualityWarehouseChanged', parseInt(wq.value));
        }
    }

    function syncQualityRack(){
        const select = document.getElementById('quality_rack_select');
        const hidden = document.getElementById('quality_rack_id');
        const rackText = document.getElementById('quality_rack_text');
        if(!select || !hidden) return;

        const wid = document.getElementById('warehouse_id_quality')?.value;
        const opts = getRackOptionsByWarehouse(wid);
        const rid = parseInt(select.value || 0);

        hidden.value = rid > 0 ? String(rid) : '';

        const found = opts.find(x => parseInt(x.id) === rid);
        if(rackText) rackText.textContent = found ? (found.label || ('Rack#' + rid)) : '-';
    }

    function isToGood(type){
        return type === 'defect_to_good' || type === 'damaged_to_good';
    }

    function toggleQualityModeUI(type){
        const unitCard = document.getElementById('quality_unit_card');
        const toGoodHelp = document.getElementById('quality_to_good_help');

        if(isToGood(type)){
            if(unitCard) unitCard.style.display = 'none';
            if(toGoodHelp) toGoodHelp.style.display = '';
        } else {
            if(unitCard) unitCard.style.display = '';
            if(toGoodHelp) toGoodHelp.style.display = 'none';
        }
    }

    function buildUnits(qty, type){
        const tbody = document.getElementById('unit_tbody');
        const title = document.getElementById('unit_col_title');
        if(!tbody || !title) return;

        qty = parseInt(qty || 0);

        if(isToGood(type)){
            tbody.innerHTML = '';
            title.innerText = 'Per Unit';
            return;
        }

        let key = 'defect_type';
        let placeholder = 'bubble / scratch';

        const isDamagedType = (type === 'damaged');

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

            if(isDamagedType){
                firstColHtml = `
                    <select name="units[${i}][reason]" class="form-control form-control-sm" required>
                        <option value="damaged">damaged</option>
                        <option value="missing">missing</option>
                    </select>
                `;
            }

            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td class="text-center">${i+1}</td>
                    <td>${firstColHtml}</td>
                    <td>
                        <textarea name="units[${i}][description]"
                                  class="form-control form-control-sm"
                                  placeholder="Optional..."></textarea>
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

        if(typeof d.product_text !== 'undefined'){
            const t = document.getElementById('quality_selected_product_text');
            if(t) t.textContent = d.product_text || 'No product selected';
        }
        const qLabel = document.getElementById('quality_total_qty');
        if(qLabel) qLabel.textContent = String(d.qty || 0);

        const type = document.getElementById('quality_type')?.value || 'defect';
        toggleQualityModeUI(type);
        buildUnits(d.qty, type);
    });

    window.addEventListener('quality-picked-updated', function(e){
        const d = e.detail || {};
        const picked = Array.isArray(d.picked_ids) ? d.picked_ids : [];
        const input = document.getElementById('quality_picked_unit_ids');
        if(input) input.value = JSON.stringify(picked);
    });

    document.addEventListener('DOMContentLoaded', function () {
        bindAdjTypeToggle();

        // ✅ Livewire stockWarehouseChanged hanya saat ADD & header visible
        const stockWh = document.getElementById('warehouse_id_stock');
        if (stockWh) {
            // init remember
            const initVal = parseInt(stockWh.value || 0);
            if(initVal > 0) LAST_STOCK_WAREHOUSE_ID = initVal;

            stockWh.addEventListener('change', () => {
                const v = parseInt(stockWh.value || 0);
                if(v > 0) LAST_STOCK_WAREHOUSE_ID = v;

                if(getAdjType() === 'add' && window.Livewire){
                    Livewire.emit('stockWarehouseChanged', v);
                }
            });
        }

        // QUALITY init
        const qualityWh = document.getElementById('warehouse_id_quality');
        if (qualityWh) {
            fillQualityRacks(qualityWh.value);

            if (window.Livewire) {
                Livewire.emit('qualityWarehouseChanged', parseInt(qualityWh.value));
                qualityWh.addEventListener('change', () => syncQualityWarehouse());
            } else {
                qualityWh.addEventListener('change', () => syncQualityWarehouse());
            }
        }

        const rackSel = document.getElementById('quality_rack_select');
        if(rackSel){
            rackSel.addEventListener('change', syncQualityRack);
            syncQualityRack();
        }

        document.getElementById('quality_type')
            ?.addEventListener('change', function(){
                const qtyVal = document.getElementById('quality_qty')?.value || 0;
                toggleQualityModeUI(this.value);
                buildUnits(qtyVal, this.value);

                if(window.Livewire){
                    Livewire.emit('qualityTypeChanged', this.value);
                }
            });

        const form = document.querySelector('form#adjustmentAddForm');
        if(form){
            form.addEventListener('submit', function(e){
                const type = getAdjType();
                if(type === 'add'){
                    if(typeof window.validateAllAdjustmentRows === 'function'){
                        const ok = window.validateAllAdjustmentRows();
                        if(!ok){
                            e.preventDefault();
                            alert('Masih ada item yang belum lengkap. Tolong cek status per item (NEED INFO).');
                        }
                    }
                }
            });
        }

        toggleQualityModeUI(document.getElementById('quality_type')?.value || 'defect');
    });
})();
</script>
@endpush
