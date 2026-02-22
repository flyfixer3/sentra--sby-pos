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
                                <div class="sa-help mb-1"><b>Info:</b> Reclass ini net-zero (bucket GOOD/DEFECT/DAMAGED berubah, total stock tetap).</div>
                                <div class="sa-help">
                                    <b>GOOD → Issue</b>: pilih Warehouse & Rack header, lalu pilih product & qty. <br>
                                    <b>Issue → GOOD</b>: header Warehouse/Rack <b>tidak dipakai</b>, pick unit IDs lintas warehouse/rack via modal.
                                </div>
                            </div>

                            @php
                                $defaultQualityWarehouseId = (int) ($defaultWarehouseId ?: optional($warehouses->first())->id);
                            @endphp

                            <form id="qualityForm" method="POST" action="{{ route('adjustments.quality.store') }}" enctype="multipart/form-data">
                                @csrf

                                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">

                                {{-- TYPE selalu ada (dipakai untuk show/hide UI) --}}
                                <div class="form-row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type" id="quality_type" class="form-control" required>
                                                <optgroup label="GOOD → Quality Issue">
                                                    <option value="defect">Defect (GOOD → DEFECT)</option>
                                                    <option value="damaged">Damaged (GOOD → DAMAGED)</option>
                                                </optgroup>
                                                <optgroup label="Quality Issue → GOOD">
                                                    <option value="defect_to_good">Defect → Good (PICK unit IDs)</option>
                                                    <option value="damaged_to_good">Damaged → Good (PICK unit IDs)</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================
                                    QUALITY CLASSIC (GOOD -> issue)
                                   ========================= --}}
                                <div id="qualityClassicWrap">

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
                                    </div>

                                    <div class="sa-divider"></div>

                                    <livewire:adjustment.product-table-quality mode="quality" :warehouseId="$defaultQualityWarehouseId"/>

                                    <input type="hidden" name="product_id" id="quality_product_id" value="">
                                    <input type="hidden" name="qty" id="quality_qty" value="0">

                                    <div class="sa-divider"></div>

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
                                                            <th id="unit_col_second_title">Description (optional)</th>
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

                                </div>

                                {{-- =========================
                                    QUALITY ISSUE -> GOOD (NEW)
                                   ========================= --}}
                                <div id="qualityIssueToGoodWrap">
                                    <livewire:adjustment.product-table-quality-to-good />

                                    <div class="alert alert-light border mt-3">
                                        <div class="font-weight-bold mb-1">Quality Issue → GOOD</div>
                                        <div class="text-muted small">
                                            Mode ini akan mengambil unit defect/damaged berdasarkan <b>Unit IDs</b> yang kamu pick (bisa lintas warehouse/rack),
                                            lalu sistem akan mengubah bucket stock menjadi GOOD (net-zero) dan menandai unit tersebut moved_out (soft).
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-divider"></div>

                                <div class="form-group">
                                    <label class="font-weight-bold">User Note <span class="text-danger">*</span></label>
                                    <textarea name="user_note" id="quality_user_note" rows="3" class="form-control"
                                              placeholder="Contoh: alasan reclass / info QC..." required></textarea>
                                    <small class="text-muted">
                                        Untuk type <b>DEFECT/DAMAGED → GOOD</b>, note ini <b>wajib</b>.
                                    </small>
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

    .sa-mini-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border:1px solid #e5e5e5;
        border-radius:999px;
        background:#fafafa;
        font-size:12px;
        color:#333;
    }

    /* Quality mode default */
    #qualityIssueToGoodWrap { display:none; }
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
    // STOCK: Toggle ADD/SUB UI (JANGAN UBAH LOGIC EXISTING)
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
            ensureStockWarehouseSelected();
            emitStockWarehouseChangedIfAdd();
        }else{
            header.style.display = 'none';
            select.required = false;
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
            setStockWarehouseHeaderVisible(false);
        } else {
            addWrap.style.display = 'block';
            subWrap.style.display = 'none';
            if (btnText) btnText.textContent = 'Create Adjustment (ADD)';
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

        setStockModeUI(getAdjType());
    }

    function submitStockForm(){
        const type = getAdjType();

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
    // QUALITY: mode switch (CLASSIC vs ISSUE->GOOD)
    // =========================
    function isToGood(type){
        return type === 'defect_to_good' || type === 'damaged_to_good';
    }

    function toggleQualityUIMode(type){
        const classic = document.getElementById('qualityClassicWrap');
        const toGoodWrap = document.getElementById('qualityIssueToGoodWrap');

        if(isToGood(type)){
            if(classic) classic.style.display = 'none';
            if(toGoodWrap) toGoodWrap.style.display = '';

            // inform livewire new condition
            if(window.Livewire){
                Livewire.emit('qualityToGoodTypeChanged', type);
            }
        }else{
            if(classic) classic.style.display = '';
            if(toGoodWrap) toGoodWrap.style.display = 'none';
        }
    }

    // =========================
    // QUALITY: Warehouse + Rack sync (HANYA UNTUK CLASSIC)
    // =========================
    function getRackOptionsByWarehouse(warehouseId){
        const map = window.RACKS_BY_WAREHOUSE || {};
        const wid = parseInt(warehouseId || 0);
        return Array.isArray(map[wid]) ? map[wid] : [];
    }

    function fillQualityRacks(warehouseId){
        const select = document.getElementById('quality_rack_select');
        const hidden = document.getElementById('quality_rack_id');
        if(!select || !hidden) return;

        const opts = getRackOptionsByWarehouse(warehouseId);

        select.innerHTML = '';
        if(opts.length === 0){
            select.insertAdjacentHTML('beforeend', `<option value="">-- No rack --</option>`);
            hidden.value = '';
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
        if(!select || !hidden) return;

        const rid = parseInt(select.value || 0);
        hidden.value = rid > 0 ? String(rid) : '';

        if(window.Livewire){
            Livewire.emit('qualityRackChanged', rid);
        }
    }

    function buildUnits(qty, type){
        const tbody = document.getElementById('unit_tbody');
        const title = document.getElementById('unit_col_title');
        const secondTitle = document.getElementById('unit_col_second_title');
        if(!tbody || !title || !secondTitle) return;

        qty = parseInt(qty || 0);

        // only for classic mode
        if(isToGood(type)){
            tbody.innerHTML = '';
            title.innerText = 'Per Unit';
            secondTitle.innerText = '';
            return;
        }

        // DEFAULT (defect)
        let firstKey = 'defect_type';
        let firstPlaceholder = 'bubble / scratch';
        title.innerText = 'DEFECT TYPE *';
        secondTitle.innerText = 'Description (optional)';

        const isDamaged = (type === 'damaged');

        // DAMAGED: damage_type fixed = damaged, second column = reason required
        if(isDamaged){
            title.innerText = 'TYPE';
            secondTitle.innerText = 'Reason *';
        }

        tbody.innerHTML = '';

        for(let i=0;i<qty;i++){

            let firstColHtml = `
                <input name="units[${i}][${firstKey}]"
                    class="form-control form-control-sm"
                    required
                    placeholder="${firstPlaceholder}">
            `;

            let secondColHtml = `
                <textarea name="units[${i}][description]"
                        class="form-control form-control-sm"
                        placeholder="Optional..."></textarea>
            `;

            if(isDamaged){
                // fixed damage_type (no dropdown)
                firstColHtml = `
                    <input type="hidden" name="units[${i}][damage_type]" value="damaged">
                    <span class="badge badge-light border px-2 py-1">damaged</span>
                `;

                // reason required
                secondColHtml = `
                    <input name="units[${i}][reason]"
                        class="form-control form-control-sm"
                        required
                        placeholder="Contoh: pecah saat bongkar / retak / penyok...">
                `;
            }

            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td class="text-center">${i+1}</td>
                    <td>${firstColHtml}</td>
                    <td>${secondColHtml}</td>
                    <td>
                        <input type="file"
                            name="units[${i}][photo]"
                            class="form-control form-control-sm">
                    </td>
                </tr>
            `);
        }
    }

    // =========================
    // EVENTS dari Livewire quality table (CLASSIC)
    // =========================
    window.addEventListener('quality-table-updated', function(e){
        const d = e.detail || {};

        const pid = document.getElementById('quality_product_id');
        const qty = document.getElementById('quality_qty');

        if(pid) pid.value = d.product_id || '';
        if(qty) qty.value = d.qty || 0;

        const type = document.getElementById('quality_type')?.value || 'defect';
        buildUnits(d.qty, type);
    });

    document.addEventListener('DOMContentLoaded', function () {
        bindAdjTypeToggle();

        const stockWh = document.getElementById('warehouse_id_stock');
        if (stockWh) {
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
        const qualityType = document.getElementById('quality_type');
        if(qualityType){
            toggleQualityUIMode(qualityType.value);

            qualityType.addEventListener('change', function(){
                toggleQualityUIMode(this.value);

                // classic-only: rebuild unit rows if needed
                const qtyVal = document.getElementById('quality_qty')?.value || 0;
                buildUnits(qtyVal, this.value);

                if(window.Livewire){
                    Livewire.emit('qualityTypeChanged', this.value);
                }
            });
        }

        // classic-only warehouse/rack init
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

        // STOCK form guard (existing)
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

        // QUALITY form submit guard (to-good validate)
        const qForm = document.getElementById('qualityForm');
        if(qForm){
            qForm.addEventListener('submit', function(e){
                const type = document.getElementById('quality_type')?.value || 'defect';

                // to-good: wajib valid semua row table
                if(isToGood(type)){
                    if(typeof window.validateAllQtgRows === 'function'){
                        const ok = window.validateAllQtgRows();
                        if(!ok){
                            e.preventDefault();
                            alert('Masih ada item Issue→GOOD yang Qty mismatch. Tolong cek status per item.');
                            return;
                        }
                    }
                }else{
                    // classic: pastikan product & qty ada
                    const pid = parseInt(document.getElementById('quality_product_id')?.value || 0);
                    const qty = parseInt(document.getElementById('quality_qty')?.value || 0);
                    if(pid <= 0 || qty <= 0){
                        e.preventDefault();
                        alert('Pilih product dan qty dulu untuk Quality Reclass (classic).');
                        return;
                    }
                }
            });
        }

        // initial unit build
        buildUnits(document.getElementById('quality_qty')?.value || 0, document.getElementById('quality_type')?.value || 'defect');
    });
})();
</script>
@endpush