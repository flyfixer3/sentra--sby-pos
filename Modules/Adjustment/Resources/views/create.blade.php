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

                            <form action="{{ route('adjustments.store') }}" method="POST" id="adjustmentAddForm" enctype="multipart/form-data" data-confirm-submit="false">
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

                            @php
                                $defaultQualityWarehouseId = (int) ($defaultWarehouseId ?: optional($warehouses->first())->id);
                            @endphp

                            <form id="qualityForm" method="POST" action="{{ route('adjustments.quality.store') }}" enctype="multipart/form-data">
                                @csrf

                                {{-- TYPE selalu ada (dipakai untuk show/hide UI) --}}
                                <div class="form-row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type" id="quality_type" class="form-control" required>
                                                <optgroup label="GOOD → Quality Issue">
                                                    <option value="defect" {{ old('type','defect') === 'defect' ? 'selected' : '' }}>Defect (GOOD → DEFECT)</option>
                                                    <option value="damaged" {{ old('type') === 'damaged' ? 'selected' : '' }}>Damaged (GOOD → DAMAGED)</option>
                                                </optgroup>
                                                <optgroup label="Quality Issue → GOOD">
                                                    <option value="defect_to_good" {{ old('type') === 'defect_to_good' ? 'selected' : '' }}>Defect → Good (PICK unit IDs)</option>
                                                    <option value="damaged_to_good" {{ old('type') === 'damaged_to_good' ? 'selected' : '' }}>Damaged → Good (PICK unit IDs)</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- =========================
                                    QUALITY CLASSIC (GOOD -> issue)
                                    sekarang ditangani oleh partial + JS di partial
                                ========================= --}}
                                <div id="qualityClassicWrap">
                                    @include('adjustment::partials.quality_reclass_good_to_issue')
                                </div>

                                {{-- =========================
                                    QUALITY ISSUE -> GOOD (existing)
                                ========================= --}}
                                <div id="qualityIssueToGoodWrap">
                                    <livewire:adjustment.product-table-quality-to-good />
                                </div>

                                <div class="sa-divider"></div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="submitQualityForm()">
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

<div class="modal fade" id="adjustmentSubmitConfirmModal" tabindex="-1" role="dialog" aria-labelledby="adjustmentSubmitConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustmentSubmitConfirmTitle">Submit Adjustment Request?</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" data-coreui-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="adjustmentSubmitConfirmMessage">
                    This adjustment will be submitted as a pending request and will not affect stock until approved.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-dismiss="modal" data-bs-dismiss="modal" data-coreui-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAdjustmentSubmitBtn">
                    Submit Request
                </button>
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
    #qualityClassicWrap { display:block; }
</style>
@endpush

@push('page_scripts')
<script>
(function(){
    // mapping racksByWarehouse dipakai oleh:
    // - Stock ADD livewire (via Livewire event stockWarehouseChanged)
    // - Quality classic partial (qrcWarehouse -> rack dropdown per row)
    window.RACKS_BY_WAREHOUSE = @json($racksByWarehouse ?? []);

    // ✅ default stock warehouse id (buat restore value saat toggle ADD/SUB)
    window.DEFAULT_STOCK_WAREHOUSE_ID = {{ (int)($defaultStockWarehouseId ?? $defaultWarehouseId ?? 0) }};

    // ✅ remember last selected warehouse in Stock tab (biar UX enak)
    let LAST_STOCK_WAREHOUSE_ID = parseInt(window.DEFAULT_STOCK_WAREHOUSE_ID || 0);
    window.currentAdjustmentMode = window.currentAdjustmentMode || 'stock';
    let pendingAdjustmentSubmitForm = null;
    let adjustmentSubmitting = false;

    // =========================
    // STOCK: Toggle ADD/SUB UI (JANGAN UBAH LOGIC EXISTING)
    // =========================
    function getAdjType(){
        return document.querySelector('input[name="adjustment_type"]:checked')?.value || 'add';
    }

    function isToGood(type){
        return type === 'defect_to_good' || type === 'damaged_to_good';
    }

    function currentProductSelectionContext(){
        if(window.currentAdjustmentMode === 'quality'){
            const qualityType = document.getElementById('quality_type')?.value || 'defect';
            return isToGood(qualityType) ? 'adjustment_quality_to_good' : 'adjustment_quality_classic';
        }

        return getAdjType() === 'sub' ? 'adjustment_stock_sub' : 'adjustment_stock_add';
    }

    function syncProductSelectionContext(){
        const context = currentProductSelectionContext();

        document.querySelectorAll('[data-product-selection-context-input]').forEach(function(input){
            if(input.value !== context){
                input.value = context;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        if(window.Livewire){
            Livewire.emit('productSelectionContextChanged', context);
        }
    }

    window.currentProductSelectionContext = currentProductSelectionContext;
    window.syncProductSelectionContext = syncProductSelectionContext;

    function setAdjustmentMode(mode){
        window.currentAdjustmentMode = mode === 'quality' ? 'quality' : 'stock';
        syncProductSelectionContext();
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

        syncProductSelectionContext();
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

    function showAdjustmentSubmitModal(form, message){
        pendingAdjustmentSubmitForm = form;

        const messageEl = document.getElementById('adjustmentSubmitConfirmMessage');
        if(messageEl){
            messageEl.textContent = message || 'This adjustment will be submitted as a pending request and will not affect stock until approved.';
        }

        const btn = document.getElementById('confirmAdjustmentSubmitBtn');
        if(btn){
            btn.disabled = false;
            btn.innerHTML = 'Submit Request';
        }

        const modal = document.getElementById('adjustmentSubmitConfirmModal');
        if(!modal) return;

        if(window.bootstrap && window.bootstrap.Modal){
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        if(window.coreui && window.coreui.Modal){
            window.coreui.Modal.getOrCreateInstance(modal).show();
            return;
        }

        if(window.jQuery && typeof window.jQuery(modal).modal === 'function'){
            window.jQuery(modal).modal('show');
        }
    }

    function hideAdjustmentSubmitModal(){
        const modal = document.getElementById('adjustmentSubmitConfirmModal');
        if(!modal) return;

        if(window.bootstrap && window.bootstrap.Modal){
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
            return;
        }

        if(window.coreui && window.coreui.Modal){
            window.coreui.Modal.getOrCreateInstance(modal).hide();
            return;
        }

        if(window.jQuery && typeof window.jQuery(modal).modal === 'function'){
            window.jQuery(modal).modal('hide');
        }
    }

    function formPassesBrowserValidation(form){
        if(!form) return false;

        if(typeof form.reportValidity === 'function'){
            return form.reportValidity();
        }

        if(typeof form.checkValidity === 'function' && !form.checkValidity()){
            return false;
        }

        return true;
    }

    function submitStockForm(){
        const type = getAdjType();
        const stockForm = document.getElementById('adjustmentAddForm');
        if(!stockForm) return;

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

        if(!formPassesBrowserValidation(stockForm)){
            return;
        }

        showAdjustmentSubmitModal(
            stockForm,
            'This adjustment will be submitted as a pending request and will not affect stock until approved.'
        );
    }
    window.submitStockForm = submitStockForm;

    function submitQualityForm(){
        const qForm = document.getElementById('qualityForm');
        if(!qForm) return;

        const type = document.getElementById('quality_type')?.value || 'defect';

        if(isToGood(type)){
            if(typeof window.validateAllQtgRows === 'function'){
                const ok = window.validateAllQtgRows();
                if(!ok){
                    alert('Masih ada item Issueâ†’GOOD yang Qty mismatch. Tolong cek status per item.');
                    return;
                }
            }
        }else{
            const ok = validateQrcClassicBeforeSubmit();
            if(!ok){
                return;
            }
        }

        if(!formPassesBrowserValidation(qForm)){
            return;
        }

        showAdjustmentSubmitModal(
            qForm,
            'This quality reclass will be submitted as a pending request and will not affect stock until approved.'
        );
    }
    window.submitQualityForm = submitQualityForm;

    // =========================
    // QUALITY: mode switch (CLASSIC vs ISSUE->GOOD)
    // - Classic (GOOD->Issue) UI & JS sekarang ADA di partial (qrc*)
    // - ToGood tetap Livewire (existing)
    // =========================
    function toggleQualityUIMode(type){
        const classic = document.getElementById('qualityClassicWrap');
        const toGoodWrap = document.getElementById('qualityIssueToGoodWrap');

        if(isToGood(type)){
            if(classic) classic.style.display = 'none';
            if(toGoodWrap) toGoodWrap.style.display = 'block';

            // existing livewire picker may listen this
            if(window.Livewire){
                Livewire.emit('qualityToGoodTypeChanged', type);
            }
        }else{
            if(classic) classic.style.display = 'block';
            if(toGoodWrap) toGoodWrap.style.display = 'none';
        }

        syncProductSelectionContext();
    }

    // =========================
    // QUALITY: submit guard
    // =========================
    function validateQrcClassicBeforeSubmit(){
        // partial IDs
        const wh = document.getElementById('qrcWarehouse');
        const tbody = document.querySelector('#qrcTable tbody');

        if(!wh || !tbody) return true; // kalau partial belum render, jangan block

        const wid = String(wh.value || '').trim();
        if(!wid){
            alert('Please select Warehouse first (Quality Classic).');
            return false;
        }

        const rows = Array.from(tbody.querySelectorAll('tr'));
        if(rows.length === 0){
            alert('Please add at least 1 item (Quality Classic).');
            return false;
        }

        // check per row required hidden values filled (product_id, rack_id, qty)
        for(const tr of rows){
            const idx = tr.dataset.idx;

            const productId = tr.querySelector(`input[name="items[${idx}][product_id]"]`)?.value || '';
            const rackId = tr.querySelector(`input[name="items[${idx}][rack_id]"]`)?.value || '';
            const qty = tr.querySelector(`input[name="items[${idx}][qty]"]`)?.value || '0';

            if(!String(productId).trim()){
                alert('Quality Classic: ada item yang belum pilih Product.');
                return false;
            }
            if(!String(rackId).trim()){
                alert('Quality Classic: ada item yang belum pilih Rack.');
                return false;
            }
            if((parseInt(qty, 10) || 0) <= 0){
                alert('Quality Classic: Qty harus minimal 1.');
                return false;
            }
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // STOCK init
        bindAdjTypeToggle();
        setAdjustmentMode(document.getElementById('pane-quality')?.classList.contains('active') ? 'quality' : 'stock');

        document.getElementById('tab-stock')?.addEventListener('click', function () {
            setAdjustmentMode('stock');
        });

        document.getElementById('tab-quality')?.addEventListener('click', function () {
            setAdjustmentMode('quality');
        });

        if (window.jQuery) {
            $('#adjustmentTabs a[data-toggle="pill"]').on('shown.bs.tab', function (event) {
                setAdjustmentMode(event.target && event.target.id === 'tab-quality' ? 'quality' : 'stock');
            });
        }

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

                // keep event for other components (safe)
                if(window.Livewire){
                    Livewire.emit('qualityTypeChanged', this.value);
                }
            });
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

        // QUALITY form guard (to-good validate + classic validate)
        const qForm = document.getElementById('qualityForm');
        if(qForm){
            qForm.addEventListener('submit', function(e){
                const type = document.getElementById('quality_type')?.value || 'defect';

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
                    const ok = validateQrcClassicBeforeSubmit();
                    if(!ok){
                        e.preventDefault();
                        return;
                    }
                }
            });
        }

        const confirmBtn = document.getElementById('confirmAdjustmentSubmitBtn');
        if(confirmBtn){
            confirmBtn.addEventListener('click', function(){
                if(adjustmentSubmitting || !pendingAdjustmentSubmitForm){
                    return;
                }

                adjustmentSubmitting = true;
                this.disabled = true;
                this.innerHTML = 'Submitting...';

                const form = pendingAdjustmentSubmitForm;
                hideAdjustmentSubmitModal();

                HTMLFormElement.prototype.submit.call(form);
            });
        }
    });
})();
</script>
@endpush
