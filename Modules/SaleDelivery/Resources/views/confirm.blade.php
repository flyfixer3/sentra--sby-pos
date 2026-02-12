@extends('layouts.app')

@section('title', 'Confirm Sale Delivery')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}">Details</a></li>
    <li class="breadcrumb-item active">Confirm</li>
</ol>
@endsection

@section('content')
@php
    $dateText = $saleDelivery->date
        ? (method_exists($saleDelivery->date, 'format') ? $saleDelivery->date->format('d M Y') : \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y'))
        : '-';

    // map warehouses for JS
    $warehouseList = $warehouses->map(function($w){
        return [
            'id' => (int) $w->id,
            'name' => (string) $w->warehouse_name,
            'is_main' => (bool) $w->is_main,
        ];
    })->values();
@endphp

<style>
    .sd-step {
        display:flex; gap:.75rem; align-items:flex-start;
        padding:.75rem 1rem; border:1px solid #e9ecef; border-radius:.75rem; background:#fff;
    }
    .sd-step .n {
        width:26px; height:26px; border-radius:999px;
        display:flex; align-items:center; justify-content:center;
        font-weight:700; font-size:.9rem;
        border:1px solid #dee2e6; background:#f8f9fa;
        flex:0 0 26px;
    }
    .sd-step .t { font-weight:700; }
    .sd-step .d { font-size:.85rem; color:#6c757d; margin-top:.1rem; }

    .badge-soft {
        border: 1px solid #e9ecef;
        background: #f8f9fa;
        color: #495057;
        font-weight: 600;
        padding: .35rem .5rem;
        border-radius: .5rem;
    }

    .confirm-card.border-danger{ border-width:2px !important; }
    .confirm-card.border-success{ border-width:2px !important; }

    .sticky-card{ position: sticky; top: 90px; }

    .id-chip {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.25rem .55rem; border-radius:999px;
        border:1px solid #e9ecef; background:#fff;
        margin:.15rem .25rem .15rem 0; font-size:.85rem;
    }
    .id-chip .x { cursor:pointer; opacity:.65; margin-left:.25rem; }
    .id-chip .x:hover { opacity:1; }

    /* Modal wireframe-like */
    .pick-box{
        border:1px solid #e2e8f0;
        border-radius:12px;
        padding:12px;
        background:#f8fafc;
    }
    .pick-list-wrap{
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#fff;
        overflow:hidden;
        position: relative;
    }
    .pick-scroll{
        max-height:360px;
        overflow:auto;
    }
    .pick-row{
        display:flex;
        gap:10px;
        align-items:flex-start;
        padding:10px 12px;
        border-bottom:1px solid #f1f5f9;
    }
    .pick-row:last-child{ border-bottom:none; }
    .pick-row .meta .idline{
        font-weight:800;
        line-height:1.2;
    }
    .pick-row .meta .sub{
        font-size:12px;
        color:#64748b;
        margin-top:2px;
    }
    .pick-row .right{
        margin-left:auto;
        display:flex;
        align-items:center;
        gap:10px;
    }
    .pick-selected-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        border:1px solid #e2e8f0;
        background:#fff;
        font-weight:800;
        white-space: nowrap;
    }
    .qty-mini{
        width: 90px;
        text-align: right;
    }
    .legend-pill{
        display:inline-flex; align-items:center; gap:6px;
        padding:4px 8px; border-radius:999px;
        border:1px solid #e2e8f0; background:#fff;
        font-size:12px; font-weight:700;
    }
    .pick-total-stock-float{
        position: absolute;
        left: 12px;
        bottom: 10px;
        font-size: 12px;
        color: #64748b;
        background: rgba(255,255,255,.92);
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
    }

    .m-group-title{
        padding:10px 12px;
        background:#f8fafc;
        border-bottom:1px solid #eef2f7;
        font-weight:800;
        color:#334155;
        font-size:12px;
        text-transform: uppercase;
        letter-spacing:.02em;
    }
</style>

<div class="container-fluid">
    @include('utils.alerts')

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between">
                <div class="mb-2">
                    <div class="d-flex align-items-center flex-wrap">
                        <h4 class="mb-0 mr-2">Confirm Sale Delivery</h4>
                        <span class="badge-soft">
                            <i class="bi bi-truck mr-1"></i> {{ $saleDelivery->reference }}
                        </span>
                    </div>
                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong>
                        <span class="mx-1">•</span>
                        Flow: <strong>Pick Items (Warehouse/Rack/Condition) → Auto Qty</strong>
                    </div>
                </div>

                <div class="mb-2">
                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                        <i class="bi bi-arrow-left mr-1"></i> Back
                    </a>
                </div>
            </div>

            <hr class="my-3">

            <div class="row g-2">
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">1</div>
                        <div>
                            <div class="t">Pick Items per Product</div>
                            <div class="d">Set Warehouse/Rack/Condition di modal.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">2</div>
                        <div>
                            <div class="t">GOOD pakai Qty</div>
                            <div class="d">GOOD bisa &gt; 1 (input qty, max = stock).</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">3</div>
                        <div>
                            <div class="t">DEFECT/DAMAGED 1 PC</div>
                            <div class="d">Pick ID (tiap ID = 1 pc).</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mb-0 mt-2 d-flex align-items-start">
                <i class="bi bi-info-circle mr-2" style="font-size:1.2rem;"></i>
                <div>
                    <div class="font-weight-bold">Rules</div>
                    <div class="small mt-1">
                        • Semua item wajib pilih Warehouse (di modal)<br>
                        • Total selected (GOOD qty + DEFECT IDs + DAMAGED IDs) wajib sama dengan Expected<br>
                        • GOOD: input qty per rack (allocation otomatis terbentuk)<br>
                        • DEFECT/DAMAGED: tiap ID selalu 1 pc
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-danger d-none" id="rowErrorBox">
        <div class="d-flex align-items-start">
            <i class="bi bi-exclamation-triangle mr-2" style="font-size:1.2rem;"></i>
            <div>
                <div class="font-weight-bold">Masih ada item yang belum valid.</div>
                <div class="small">
                    Buka “Pick Items” per item, pilih warehouse, lalu pastikan total selected sama dengan Expected.
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('sale-deliveries.confirm.store', $saleDelivery->id) }}" id="confirmForm">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                @forelse($saleDelivery->items as $i => $it)
                    @php
                        $itemId = (int) $it->id;
                        $pid = (int) $it->product_id;
                        $expected = (int) ($it->quantity ?? 0);
                        $productName = $it->product?->product_name ?? ('Product #'.$pid);
                        $productCode = $it->product?->product_code ?? '';
                        $modalId = "pickModal_{$i}";
                    @endphp

                    <div class="card shadow-sm mb-3 confirm-card border"
                         data-idx="{{ $i }}"
                         data-item-id="{{ $itemId }}"
                         data-product-id="{{ $pid }}"
                         data-expected="{{ $expected }}">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-start justify-content-between">
                                <div class="mb-2">
                                    <div class="font-weight-bold">{{ $productName }}</div>
                                    <div class="text-muted small">
                                        @if($productCode) <span class="mr-2">Code: <b>{{ $productCode }}</b></span> @endif
                                        <span class="mr-2">product_id: <b>{{ $pid }}</b></span>
                                        <span>item_id: <b>{{ $itemId }}</b></span>
                                    </div>
                                </div>

                                <div class="text-right mb-2">
                                    <div class="small text-muted">Expected</div>
                                    <div class="badge badge-secondary px-3 py-2">{{ number_format($expected) }}</div>
                                </div>
                            </div>

                            <hr class="my-3">

                            {{-- REQUIRED BASE --}}
                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $itemId }}">

                            {{-- warehouse chosen in modal --}}
                            <input type="hidden" name="items[{{ $i }}][warehouse_id]" class="h-warehouse" value="{{ (int)old("items.$i.warehouse_id", 0) }}">

                            {{-- auto hidden qty (from modal selection) --}}
                            <input type="hidden" name="items[{{ $i }}][good]" class="h-good" value="{{ (int)old("items.$i.good", 0) }}">
                            <input type="hidden" name="items[{{ $i }}][defect]" class="h-defect" value="{{ (int)old("items.$i.defect", 0) }}">
                            <input type="hidden" name="items[{{ $i }}][damaged]" class="h-damaged" value="{{ (int)old("items.$i.damaged", 0) }}">

                            {{-- dynamic holders --}}
                            <div class="h-good-alloc"></div>
                            <div class="h-defect-ids"></div>
                            <div class="h-damaged-ids"></div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
                                <div class="small">
                                    <span class="badge badge-light border">
                                        Warehouse: <b class="t-warehouse">-</b>
                                    </span>
                                    <span class="badge badge-light border ml-1">
                                        Selected: <b class="t-total">0</b> / {{ $expected }}
                                    </span>
                                    <span class="badge badge-light border ml-1">
                                        GOOD: <b class="t-good">0</b>
                                    </span>
                                    <span class="badge badge-light border ml-1">
                                        DEFECT: <b class="t-defect">0</b> <span class="text-muted">(1 pc/ID)</span>
                                    </span>
                                    <span class="badge badge-light border ml-1">
                                        DAMAGED: <b class="t-damaged">0</b> <span class="text-muted">(1 pc/ID)</span>
                                    </span>
                                </div>

                                <div class="d-flex" style="gap:8px;">
                                    <button type="button"
                                            class="btn btn-dark btn-open-pick"
                                            data-toggle="modal" data-target="#{{ $modalId }}"
                                            data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                        <i class="bi bi-ui-checks-grid mr-1"></i> Pick Items
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="small text-muted mb-1">GOOD Allocations (auto):</div>
                                <div class="t-good-alloc text-muted small">-</div>

                                <div class="small text-muted mt-2 mb-1">Selected DEFECT IDs:</div>
                                <div class="t-defect-chips"></div>

                                <div class="small text-muted mt-2 mb-1">Selected DAMAGED IDs:</div>
                                <div class="t-damaged-chips"></div>

                                <div class="small text-muted mt-2">
                                    Status: <span class="badge badge-secondary t-status">Checking...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- MODAL --}}
                    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <div class="w-100">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
                                            <h5 class="modal-title mb-0" style="font-weight:900;">Pick Items</h5>

                                            <div class="pick-selected-pill">
                                                <span class="text-muted">Selected</span>
                                                <span class="m-selected">0</span>
                                                <span class="text-muted">/ {{ $expected }}</span>
                                            </div>
                                        </div>

                                        <div class="small text-muted mt-1">
                                            Filter berdasarkan <b>Warehouse / Rack / Condition</b>.
                                            <br>
                                            <span class="legend-pill mr-1">GOOD: input qty</span>
                                            <span class="legend-pill mr-1">DEFECT: 1 pc / ID</span>
                                            <span class="legend-pill">DAMAGED: 1 pc / ID</span>
                                        </div>
                                    </div>

                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:1.5rem;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">

                                    <div class="pick-box mb-3">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="small text-muted mb-1 d-block">Warehouse</label>
                                                <select class="form-control m-wh">
                                                    <option value="">All warehouse</option>
                                                    @foreach($warehouses as $w)
                                                        <option value="{{ $w->id }}">{{ $w->warehouse_name }} {{ $w->is_main ? '(Main)' : '' }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="small text-muted mt-1">
                                                    Saat Save, warehouse wajib dipilih (bukan All).
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="small text-muted mb-1 d-block">Racks</label>
                                                <select class="form-control m-rack">
                                                    <option value="">All Racks</option>
                                                </select>
                                                <div class="small text-muted mt-1">
                                                    Rack filter aktif jika warehouse dipilih.
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="small text-muted mb-1 d-block">Conditions</label>
                                                <select class="form-control m-cond">
                                                    <option value="">All Conditions</option>
                                                    <option value="good">Good</option>
                                                    <option value="defect">Defect</option>
                                                    <option value="damaged">Damaged</option>
                                                </select>
                                                <div class="small text-muted mt-1">
                                                    Kondisi membantu fokus list.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-2" style="gap:10px;">
                                            <div class="small text-muted">
                                                Required Total: <b class="m-required">{{ $expected }}</b>
                                                <span class="mx-2">•</span>
                                                GOOD: <b class="m-good">0</b>
                                                <span class="mx-2">•</span>
                                                DEFECT: <b class="m-defect">0</b>
                                                <span class="mx-2">•</span>
                                                DAMAGED: <b class="m-damaged">0</b>
                                            </div>

                                            <div class="d-flex" style="gap:8px;">
                                                <button type="button" class="btn btn-outline-secondary m-reset">Reset</button>
                                                <button type="button" class="btn btn-primary m-apply">Apply</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pick-list-wrap">
                                        <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-bottom:1px solid #f1f5f9;">
                                            <div class="small text-muted">
                                                List item sesuai filter (GOOD pakai qty, DEFECT/DAMAGED 1pc per ID).
                                            </div>
                                            <div class="small text-muted">
                                                Total Stock: <b class="m-total-stock">0</b> Pcs
                                            </div>
                                        </div>

                                        <div class="pick-scroll m-body">
                                            <div class="text-center text-muted py-4">Memuat data...</div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-top:1px solid #f1f5f9;">
                                            <div class="small text-muted">
                                                Selected Total: <b class="m-selected2">0</b> / {{ $expected }}
                                            </div>
                                            <div class="small text-muted">
                                                * Save akan menyimpan pilihan ke card.
                                            </div>
                                        </div>

                                        <div class="pick-total-stock-float">
                                            Total Stock: <b class="m-total-stock2">0</b> Pcs
                                        </div>
                                    </div>

                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-dark m-save" data-dismiss="modal">
                                        Save Selection
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                @empty
                    <div class="card shadow-sm">
                        <div class="card-body text-center text-muted py-5">No items.</div>
                    </div>
                @endforelse
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm sticky-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">Validation</div>
                            <span class="badge badge-secondary" id="validStat">Checking...</span>
                        </div>

                        <div class="text-muted small mt-2">
                            Tombol confirm akan nonaktif jika ada item invalid.
                        </div>

                        <hr class="my-3">

                        <label class="form-label font-weight-bold">Confirm Note (optional)</label>
                        <textarea name="confirm_note" class="form-control" rows="4">{{ old('confirm_note') }}</textarea>
                        <div class="text-muted small mt-1">Catatan internal untuk histori confirm.</div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                                <i class="bi bi-check2-circle mr-1"></i> Confirm
                            </button>
                            <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-outline-secondary btn-block">
                                Cancel
                            </a>
                        </div>

                        <div class="small text-muted mt-3">
                            Tips: per item klik “Pick Items” → pilih warehouse → atur GOOD/DEFECT/DAMAGED sampai total = Expected.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    const DEFECT_DATA = @json($defectData ?? []);
    const DAMAGED_DATA = @json($damagedData ?? []);
    const RACKS_BY_WAREHOUSE = @json($racksByWarehouse ?? []);
    const WAREHOUSES = @json($warehouseList ?? []);
    const STOCK_RACK_DATA = @json($stockRackData ?? []);

    function toInt(v){
        v = (v === null || v === undefined) ? '0' : String(v);
        v = v.trim();
        if (v === '') v = '0';
        return parseInt(v, 10) || 0;
    }

    function fmtText(t, max){
        max = max || 70;
        t = (t === null || t === undefined) ? '' : String(t);
        t = t.trim();
        if (!t) return '';
        if (t.length <= max) return t;
        return t.substring(0, max) + '...';
    }

    function racksForWarehouse(wid){
        const arr = RACKS_BY_WAREHOUSE[wid] || RACKS_BY_WAREHOUSE[String(wid)] || [];
        return Array.isArray(arr) ? arr : [];
    }

    function rackLabel(r){
        const code = (r && r.code) ? String(r.code) : '';
        const name = (r && r.name) ? String(r.name) : '';
        const base = [code, name].filter(Boolean).join(' - ');
        return base || ('Rack #' + (r && r.id ? r.id : ''));
    }

    function buildRackOptions(wid, includeAll){
        const racks = racksForWarehouse(wid);
        let html = '';
        if (includeAll) html += `<option value="">All Racks</option>`;
        racks.forEach(r => {
            html += `<option value="${toInt(r.id)}">${rackLabel(r)}</option>`;
        });
        return html;
    }

    function getRackStock(pid, wid, rid){
        pid = toInt(pid); wid = toInt(wid); rid = toInt(rid);
        const p = STOCK_RACK_DATA[pid] || STOCK_RACK_DATA[String(pid)];
        if (!p) return { total:0, good:0, defect:0, damaged:0 };

        const w = p[wid] || p[String(wid)];
        if (!w) return { total:0, good:0, defect:0, damaged:0 };

        const r = w[rid] || w[String(rid)];
        if (!r) return { total:0, good:0, defect:0, damaged:0 };

        return {
            total: toInt(r.total),
            good: toInt(r.good),
            defect: toInt(r.defect),
            damaged: toInt(r.damaged),
        };
    }

    function getAvailableDefectList(pid, wid){
        if (!pid || !wid) return [];
        if (!DEFECT_DATA[pid]) return [];
        return DEFECT_DATA[pid][wid] || DEFECT_DATA[pid][String(wid)] || [];
    }

    function getAvailableDamagedList(pid, wid){
        if (!pid || !wid) return [];
        if (!DAMAGED_DATA[pid]) return [];
        return DAMAGED_DATA[pid][wid] || DAMAGED_DATA[pid][String(wid)] || [];
    }

    function buildGoodRows(pid, wid){
        const racks = racksForWarehouse(wid);
        return racks.map(r => {
            const rid = toInt(r.id);
            const st = getRackStock(pid, wid, rid); // ✅ NEW
            return {
                type: 'good',
                warehouse_id: toInt(wid),
                rack_id: rid,
                rack_label: rackLabel(r),
                stock_total: toInt(st.total),
                stock_good: toInt(st.good),
                stock_defect: toInt(st.defect),
                stock_damaged: toInt(st.damaged),
            };
        });
    }

    function setHiddenArray(container, name, values){
        container.innerHTML = '';
        (values || []).forEach(v => {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = name;
            i.value = String(v);
            container.appendChild(i);
        });
    }

    function setHiddenAllocations(container, idx, alloc){
        container.innerHTML = '';
        (alloc || []).forEach((a, k) => {
            const rid = toInt(a.from_rack_id);
            const qty = toInt(a.qty);
            if (rid <= 0 || qty <= 0) return;

            const i1 = document.createElement('input');
            i1.type = 'hidden';
            i1.name = `items[${idx}][good_allocations][${k}][from_rack_id]`;
            i1.value = String(rid);
            container.appendChild(i1);

            const i2 = document.createElement('input');
            i2.type = 'hidden';
            i2.name = `items[${idx}][good_allocations][${k}][qty]`;
            i2.value = String(qty);
            container.appendChild(i2);
        });
    }

    function renderChips(box, ids){
        if (!box) return;
        box.innerHTML = '';
        if (!ids || !ids.length) {
            box.innerHTML = '<span class="text-muted small">-</span>';
            return;
        }
        ids.forEach(id => {
            const chip = document.createElement('span');
            chip.className = 'id-chip';
            chip.innerHTML = `<span class="badge badge-dark">#${id}</span>`;
            box.appendChild(chip);
        });
    }

    function getWarehouseName(wid){
        wid = toInt(wid);
        if (!wid) return '-';
        const w = WAREHOUSES.find(x => toInt(x.id) === wid);
        if (!w) return 'Warehouse #' + wid;
        return w.name + (w.is_main ? ' (Main)' : '');
    }

    function openModal(modalEl){
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return true;
            }
        } catch(e) {}

        try {
            if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
                jQuery(modalEl).modal('show');
                return true;
            }
        } catch(e) {}

        return false;
    }

    function updateCard(card){
        const expected = toInt(card.dataset.expected);

        const hWarehouse = card.querySelector('.h-warehouse');
        const hGood = card.querySelector('.h-good');
        const hDef = card.querySelector('.h-defect');
        const hDam = card.querySelector('.h-damaged');

        const wid = toInt(hWarehouse ? hWarehouse.value : 0);
        const good = toInt(hGood ? hGood.value : 0);
        const defect = toInt(hDef ? hDef.value : 0);
        const damaged = toInt(hDam ? hDam.value : 0);
        const total = good + defect + damaged;

        const tWh = card.querySelector('.t-warehouse');
        if (tWh) tWh.textContent = getWarehouseName(wid);

        const tGood = card.querySelector('.t-good');
        const tDef  = card.querySelector('.t-defect');
        const tDam  = card.querySelector('.t-damaged');
        const tTot  = card.querySelector('.t-total');
        if (tGood) tGood.textContent = String(good);
        if (tDef)  tDef.textContent = String(defect);
        if (tDam)  tDam.textContent = String(damaged);
        if (tTot)  tTot.textContent = String(total);

        const okWarehouse = wid > 0;
        const okTotal = total === expected;
        const ok = okWarehouse && okTotal;

        card.classList.toggle('border-danger', !ok);
        card.classList.toggle('border-success', ok);

        const st = card.querySelector('.t-status');
        if (st) {
            if (!okWarehouse) {
                st.textContent = 'Pick warehouse';
                st.className = 'badge badge-danger t-status';
            } else if (!okTotal) {
                st.textContent = 'Qty mismatch';
                st.className = 'badge badge-danger t-status';
            } else {
                st.textContent = 'OK';
                st.className = 'badge badge-success t-status';
            }
        }

        return ok;
    }

    function refreshGlobal(){
        const cards = document.querySelectorAll('.confirm-card');
        let hasErr = false;
        cards.forEach(c => { if (!updateCard(c)) hasErr = true; });

        const box = document.getElementById('rowErrorBox');
        const btn = document.getElementById('submitBtn');
        const stat = document.getElementById('validStat');

        if (box) box.classList.toggle('d-none', !hasErr);
        if (btn) btn.disabled = hasErr;

        if (stat) {
            stat.textContent = hasErr ? 'Invalid' : 'Ready';
            stat.className = 'badge ' + (hasErr ? 'badge-danger' : 'badge-success');
        }
    }

    function mountModal(card, modalEl){
        const idx = toInt(card.dataset.idx);
        const pid = toInt(card.dataset.productId);
        const expected = toInt(card.dataset.expected);

        const hWarehouse = card.querySelector('.h-warehouse');
        const hGood = card.querySelector('.h-good');
        const hDef = card.querySelector('.h-defect');
        const hDam = card.querySelector('.h-damaged');

        const allocBox = card.querySelector('.h-good-alloc');
        const defBox = card.querySelector('.h-defect-ids');
        const damBox = card.querySelector('.h-damaged-ids');

        const selWh = modalEl.querySelector('.m-wh');
        const selRack = modalEl.querySelector('.m-rack');
        const selCond = modalEl.querySelector('.m-cond');
        const body = modalEl.querySelector('.m-body');

        const tReq = modalEl.querySelector('.m-required');
        const tGood= modalEl.querySelector('.m-good');
        const tDef = modalEl.querySelector('.m-defect');
        const tDam = modalEl.querySelector('.m-damaged');
        const tSel = modalEl.querySelector('.m-selected');
        const tSel2= modalEl.querySelector('.m-selected2');
        const tStock= modalEl.querySelector('.m-total-stock');
        const tStock2= modalEl.querySelector('.m-total-stock2');

        const btnReset = modalEl.querySelector('.m-reset');
        const btnApply = modalEl.querySelector('.m-apply');
        const btnSave  = modalEl.querySelector('.m-save');

        let chosenWid = toInt(hWarehouse ? hWarehouse.value : 0);

        // hydrate existing selection from hidden inputs
        let goodAlloc = [];
        let defectIds = [];
        let damagedIds = [];

        goodAlloc = [];
        if (allocBox) {
            const ins = Array.from(allocBox.querySelectorAll('input[type="hidden"]'));
            const map = {};
            ins.forEach(i => {
                const name = String(i.name || '');
                const m = name.match(/good_allocations\]\[(\d+)\]\[(from_rack_id|qty)\]$/);
                if (!m) return;
                const k = toInt(m[1]);
                const key = m[2];
                map[k] = map[k] || {};
                map[k][key] = toInt(i.value);
            });
            Object.keys(map).forEach(k => {
                const row = map[k];
                if (toInt(row.from_rack_id) > 0 && toInt(row.qty) > 0) {
                    goodAlloc.push({ from_rack_id: toInt(row.from_rack_id), qty: toInt(row.qty) });
                }
            });
        }

        defectIds = [];
        if (defBox) defectIds = Array.from(defBox.querySelectorAll('input[type="hidden"]')).map(x => String(x.value));

        damagedIds = [];
        if (damBox) damagedIds = Array.from(damBox.querySelectorAll('input[type="hidden"]')).map(x => String(x.value));

        function sumGood(){
            return goodAlloc.reduce((a,b) => a + toInt(b.qty), 0);
        }

        function renderTop(){
            const g = sumGood();
            const d = defectIds.length;
            const dm = damagedIds.length;
            const sel = g + d + dm;

            if (tReq) tReq.textContent = String(expected);
            if (tGood) tGood.textContent = String(g);
            if (tDef)  tDef.textContent = String(d);
            if (tDam)  tDam.textContent = String(dm);
            if (tSel)  tSel.textContent = String(sel);
            if (tSel2) tSel2.textContent = String(sel);
        }

        // ✅ FIX #1: rack dropdown auto update on warehouse change,
        // ✅ and DO NOT reset rack selection if still valid
        function rebuildRacks(preserve){
            const wid = toInt(selWh ? selWh.value : 0);

            if (!selRack) return;

            // if All warehouse: disable rack filter (avoid ambiguous rack across WH)
            if (wid <= 0) {
                selRack.innerHTML = `<option value="">All Racks</option>`;
                selRack.value = '';
                selRack.disabled = true;
                return;
            }

            const prev = preserve ? toInt(selRack.value) : 0;

            selRack.disabled = false;
            selRack.innerHTML = buildRackOptions(wid, true);

            // try keep previous selection if exists in new options
            if (prev > 0 && selRack.querySelector(`option[value="${prev}"]`)) {
                selRack.value = String(prev);
            } else {
                // keep as is if current still exists, else reset
                const cur = toInt(selRack.value);
                if (cur > 0 && !selRack.querySelector(`option[value="${cur}"]`)) {
                    selRack.value = '';
                }
            }
        }

        // ✅ FIX #2 + Requested behavior:
        // default All warehouse => show ALL list (all WH in active branch),
        // warehouse optional for browsing, but required for Save.
        function buildList(){
            if (!body) return;

            const wid = toInt(selWh ? selWh.value : 0);
            const rackFilter = toInt(selRack ? selRack.value : 0);
            const cond = selCond ? String(selCond.value || '') : '';

            const goodAllocMap = {};
            goodAlloc.forEach(a => { goodAllocMap[toInt(a.from_rack_id)] = toInt(a.qty); });

            let totalStock = 0;
            body.innerHTML = '';

            // helper to append row UI
            function appendRow(r){
                const row = document.createElement('div');
                row.className = 'pick-row';

                if (r.type === 'good') {
                    const rid = toInt(r.rack_id);
                    const avail = toInt(r.stock_total); // ✅ NEW: TOTAL pcs available
                    const disabled = avail <= 0;

                    let curQty = toInt(goodAllocMap[rid] || 0);
                    if (curQty > avail) curQty = avail; // clamp existing selection

                    row.innerHTML = `
                        <div style="width:24px; padding-top:2px;">
                            <input type="checkbox"
                                class="m-good-check"
                                data-rack="${rid}"
                                ${curQty > 0 ? 'checked' : ''}
                                ${disabled ? 'disabled' : ''}>
                        </div>
                        <div class="meta">
                            <div class="idline">GOOD</div>
                            <div class="sub">${r.sub}</div>
                        </div>
                        <div class="right">
                            <span class="badge badge-light border">Avail: <b>${avail}</b></span>
                            <input type="number"
                                class="form-control form-control-sm qty-mini m-good-qty"
                                data-rack="${rid}"
                                min="0"
                                max="${avail}"
                                value="${curQty}"
                                placeholder="Qty"
                                ${disabled ? 'disabled' : ''}>
                            <span class="badge badge-light border">GOOD</span>
                        </div>
                    `;
                } else {
                    const checked = (r.type === 'defect') ? defectIds.includes(r.id) : damagedIds.includes(r.id);

                    row.innerHTML = `
                        <div style="width:24px; padding-top:2px;">
                            <input type="checkbox" class="m-id-check" data-type="${r.type}" value="${r.id}" ${checked ? 'checked' : ''}>
                        </div>
                        <div class="meta">
                            <div class="idline">ID#${r.id}</div>
                            <div class="sub">${r.sub}</div>
                        </div>
                        <div class="right">
                            <span class="badge badge-light border">${r.title}</span>
                        </div>
                    `;
                }

                body.appendChild(row);
            }

            function appendGroupTitle(text){
                const d = document.createElement('div');
                d.className = 'm-group-title';
                d.textContent = text;
                body.appendChild(d);
            }

            function rowsForWarehouse(oneWid){
                let rows = [];

                // GOOD rows (per rack)
                let goodRows = buildGoodRows(pid, oneWid).map(r => ({
                    type: 'good',
                    rack_id: r.rack_id,
                    warehouse_id: toInt(oneWid),
                    id: 'GOOD@' + oneWid + '@' + r.rack_id,
                    title: 'GOOD',
                    stock_total: toInt(r.stock_total),
                    stock_good: toInt(r.stock_good),
                    stock_defect: toInt(r.stock_defect),
                    stock_damaged: toInt(r.stock_damaged),
                    sub: `Warehouse: ${getWarehouseName(oneWid)} | ${r.rack_label}
                        | Avail: ${toInt(r.stock_total)} (G${toInt(r.stock_good)}/D${toInt(r.stock_defect)}/DM${toInt(r.stock_damaged)})`,
                }));

                // rack filter only meaningful when specific warehouse selected
                if (wid > 0 && rackFilter > 0) {
                    goodRows = goodRows.filter(x => toInt(x.rack_id) === rackFilter);
                }

                let defectRows = getAvailableDefectList(pid, oneWid).map(r => ({
                    type: 'defect',
                    rack_id: toInt(r.rack_id),
                    id: String(r.id),
                    title: 'DEFECT',
                    sub: [
                        `Warehouse: ${getWarehouseName(oneWid)}`,
                        'DEFECT (1 pc)',
                        (r.defect_type ? String(r.defect_type) : ''),
                        (r.description ? fmtText(r.description, 70) : ''),
                        (r.rack_id ? ('Rack #' + String(r.rack_id)) : ''),
                    ].filter(Boolean).join(' | ')
                }));

                let damagedRows = getAvailableDamagedList(pid, oneWid).map(r => ({
                    type: 'damaged',
                    rack_id: toInt(r.rack_id),
                    id: String(r.id),
                    title: 'DAMAGED',
                    sub: [
                        `Warehouse: ${getWarehouseName(oneWid)}`,
                        'DAMAGED (1 pc)',
                        (r.reason ? fmtText(r.reason, 70) : ''),
                        (r.rack_id ? ('Rack #' + String(r.rack_id)) : ''),
                    ].filter(Boolean).join(' | ')
                }));

                // condition filter
                if (cond === '' || cond === 'good') rows = rows.concat(goodRows);
                if (cond === '' || cond === 'defect') rows = rows.concat(defectRows);
                if (cond === '' || cond === 'damaged') rows = rows.concat(damagedRows);

                // rack filter for defect/damaged also (only when specific warehouse)
                if (wid > 0 && rackFilter > 0) rows = rows.filter(x => toInt(x.rack_id) === rackFilter);

                // totalStock counting (real pcs: defect+damaged)
                totalStock += defectRows.length + damagedRows.length;

                return rows;
            }

            // build rows
            if (wid > 0) {
                const rows = rowsForWarehouse(wid);

                if (!rows.length) {
                    body.innerHTML = `<div class="text-center text-muted py-4">Tidak ada data untuk filter ini.</div>`;
                } else {
                    rows.forEach(r => appendRow(r));
                }
            } else {
                // ✅ show ALL warehouses by default
                let any = false;
                WAREHOUSES.forEach(w => {
                    const oneWid = toInt(w.id);
                    const rows = rowsForWarehouse(oneWid);
                    if (!rows.length) return;

                    any = true;
                    appendGroupTitle(getWarehouseName(oneWid));
                    rows.forEach(r => appendRow(r));
                });

                if (!any) {
                    body.innerHTML = `<div class="text-center text-muted py-4">Tidak ada data stock untuk item ini.</div>`;
                }
            }

            if (tStock) tStock.textContent = String(totalStock);
            if (tStock2) tStock2.textContent = String(totalStock);

            // handlers
            Array.from(body.querySelectorAll('.m-id-check')).forEach(ch => {
                ch.addEventListener('change', () => {
                    const type = String(ch.dataset.type || '');
                    const id = String(ch.value || '');

                    if (type === 'defect') {
                        if (ch.checked && !defectIds.includes(id)) defectIds.push(id);
                        if (!ch.checked) defectIds = defectIds.filter(x => x !== id);
                    } else if (type === 'damaged') {
                        if (ch.checked && !damagedIds.includes(id)) damagedIds.push(id);
                        if (!ch.checked) damagedIds = damagedIds.filter(x => x !== id);
                    }

                    renderTop();
                });
            });

            function rebuildGoodAllocFromUI(){
                const boxes = Array.from(body.querySelectorAll('.m-good-qty'));
                const next = [];

                boxes.forEach(inp => {
                    if (inp.disabled) return;

                    const rid = toInt(inp.dataset.rack);
                    let qty = toInt(inp.value);

                    const max = toInt(inp.getAttribute('max'));
                    if (max > 0 && qty > max) qty = max;
                    if (qty < 0) qty = 0;

                    const chk = body.querySelector('.m-good-check[data-rack="'+rid+'"]');
                    if (!chk || chk.disabled) return;

                    const use = chk.checked;
                    if (use && qty > 0) next.push({ from_rack_id: rid, qty: qty });
                });

                goodAlloc = next;
                renderTop();
            }

            Array.from(body.querySelectorAll('.m-good-check')).forEach(chk => {
                chk.addEventListener('change', () => {
                    const rid = toInt(chk.dataset.rack);
                    const inp = body.querySelector('.m-good-qty[data-rack="'+rid+'"]');
                    if (!chk.checked && inp) inp.value = '0';
                    rebuildGoodAllocFromUI();
                });
            });

            Array.from(body.querySelectorAll('.m-good-qty')).forEach(inp => {
                inp.addEventListener('input', () => {
                    const rid = toInt(inp.dataset.rack);
                    const chk = body.querySelector('.m-good-check[data-rack="'+rid+'"]');

                    const max = toInt(inp.getAttribute('max'));
                    let v = toInt(inp.value);

                    // ✅ clamp
                    if (max > 0 && v > max) v = max;
                    if (v < 0) v = 0;
                    inp.value = String(v);

                    if (chk && v > 0) chk.checked = true;
                    if (chk && v <= 0) chk.checked = false;

                    rebuildGoodAllocFromUI();
                });
            });

            renderTop();
        }

        function doReset(){
            if (selWh) selWh.value = '';
            if (selCond) selCond.value = '';
            if (selRack) selRack.value = '';
            rebuildRacks(false);
            buildList();
        }

        function doApply(){
            // ✅ Apply now only rebuilds list, DOES NOT nuke rack selection
            rebuildRacks(true);
            buildList();
        }

        function doSave(){
            const wid = toInt(selWh ? selWh.value : 0);
            if (wid <= 0) {
                alert('Warehouse wajib dipilih sebelum Save.');
                return;
            }

            const g = sumGood();
            const d = defectIds.length;
            const dm = damagedIds.length;
            const sel = g + d + dm;

            if (sel !== expected) {
                alert('Total selected harus sama dengan Expected: ' + expected + '. (Sekarang: ' + sel + ')');
                return;
            }

            if (hWarehouse) hWarehouse.value = String(wid);
            if (hGood) hGood.value = String(g);
            if (hDef)  hDef.value = String(d);
            if (hDam)  hDam.value = String(dm);

            setHiddenAllocations(allocBox, idx, goodAlloc);
            setHiddenArray(defBox, `items[${idx}][selected_defect_ids][]`, defectIds);
            setHiddenArray(damBox, `items[${idx}][selected_damaged_ids][]`, damagedIds);

            const tGoodAlloc = card.querySelector('.t-good-alloc');
            if (tGoodAlloc) {
                if (!goodAlloc.length) tGoodAlloc.textContent = '-';
                else {
                    tGoodAlloc.innerHTML = goodAlloc.map(a => {
                        return `<span class="badge badge-light border mr-1">Rack #${toInt(a.from_rack_id)}: <b>${toInt(a.qty)}</b></span>`;
                    }).join(' ');
                }
            }

            renderChips(card.querySelector('.t-defect-chips'), defectIds);
            renderChips(card.querySelector('.t-damaged-chips'), damagedIds);

            updateCard(card);
            refreshGlobal();
        }

        if (btnReset) btnReset.onclick = doReset;
        if (btnApply) btnApply.onclick = doApply;
        if (btnSave)  btnSave.onclick  = doSave;

        // ✅ NEW: change events so you don't have to click Apply just to see list
        if (selWh) {
            selWh.onchange = function(){
                rebuildRacks(false);
                buildList();
            };
        }
        if (selRack) {
            selRack.onchange = function(){
                buildList();
            };
        }
        if (selCond) {
            selCond.onchange = function(){
                buildList();
            };
        }

        // init
        if (selWh) selWh.value = chosenWid > 0 ? String(chosenWid) : '';
        rebuildRacks(false);
        buildList();
        renderTop();
    }

    const cards = document.querySelectorAll('.confirm-card');
    cards.forEach(card => {
        renderChips(card.querySelector('.t-defect-chips'), []);
        renderChips(card.querySelector('.t-damaged-chips'), []);
        const goodAllocTxt = card.querySelector('.t-good-alloc');
        if (goodAllocTxt) goodAllocTxt.textContent = '-';

        const btn = card.querySelector('.btn-open-pick');
        if (btn) {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
                const modal = target ? document.querySelector(target) : null;
                if (!modal) return;

                mountModal(card, modal);
                openModal(modal);
            });
        }

        updateCard(card);
    });

    refreshGlobal();

    const form = document.getElementById('confirmForm');
    if (form) {
        form.addEventListener('submit', function(e){
            refreshGlobal();
            const anyErr = Array.from(document.querySelectorAll('.confirm-card')).some(c => !updateCard(c));
            if (anyErr) {
                e.preventDefault();
                const box = document.getElementById('rowErrorBox');
                if (box) box.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            return true;
        });
    }
})();
</script>
@endsection
