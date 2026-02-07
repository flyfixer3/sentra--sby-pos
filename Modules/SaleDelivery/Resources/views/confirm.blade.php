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

    .id-chip {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.25rem .55rem; border-radius:999px;
        border:1px solid #e9ecef; background:#fff;
        margin:.15rem .25rem .15rem 0; font-size:.85rem;
    }
    .id-chip .x { cursor:pointer; opacity:.65; margin-left:.25rem; }
    .id-chip .x:hover { opacity:1; }

    .section-title{
        display:flex; align-items:center; justify-content:space-between;
        margin-top: .25rem; margin-bottom:.5rem;
    }
    .section-title .ttl{ font-weight:700; }
    .section-title .sub{ font-size:.85rem; color:#6c757d; }

    .muted-hint{ font-size:.85rem; color:#6c757d; }

    .pick-list .list-group-item{ padding:.75rem 1rem; }
    .pick-list .item-wrap{ display:flex; align-items:flex-start; gap:.75rem; }
    .pick-list .check-wrap{ width:24px; flex:0 0 24px; display:flex; justify-content:center; }
    .pick-list input[type="checkbox"]{ margin-top:.2rem; }

    .counter-badge{
        color:#fff !important; padding:.35rem .5rem; border-radius:.35rem; font-weight:700;
    }

    .confirm-card.border-danger{ border-width:2px !important; }
    .confirm-card.border-success{ border-width:2px !important; }

    .inline-pill{
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.25rem .55rem; border-radius:999px;
        border:1px solid #e9ecef; background:#fff; font-size:.85rem;
        margin-right:.35rem;
    }

    .good-table td, .good-table th{ vertical-align:middle; }
    .good-table .btn{ padding:.25rem .45rem; }

    .modal .modal-header{
        border-bottom:1px solid #f1f3f5;
    }
    .modal .modal-footer{
        border-top:1px solid #f1f3f5;
    }

    .sticky-card{ position: sticky; top: 90px; }

    .btn-disabled-hint{
        font-size:.85rem; color:#6c757d;
    }
</style>

<div class="container-fluid">
    @include('utils.alerts')

    {{-- Header --}}
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
                        Flow: <strong>Warehouse → Qty → Rack / IDs</strong>
                    </div>
                </div>

                <div class="mb-2">
                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                        <i class="bi bi-arrow-left mr-1"></i> Back
                    </a>
                </div>
            </div>

            <hr class="my-3">

            {{-- Steps --}}
            <div class="row g-2">
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">1</div>
                        <div>
                            <div class="t">Choose Warehouse</div>
                            <div class="d">Wajib. Setelah ini rack & ID akan otomatis mengikuti warehouse.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">2</div>
                        <div>
                            <div class="t">Fill Qty (GOOD/DEFECT/DAMAGED)</div>
                            <div class="d"><strong>Total wajib = Expected</strong> untuk tiap item.</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="sd-step h-100">
                        <div class="n">3</div>
                        <div>
                            <div class="t">Allocate / Pick</div>
                            <div class="d">GOOD: pilih rack + qty. DEFECT/DAMAGED: pilih rack lalu pilih ID.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mb-0 mt-2 d-flex align-items-start">
                <i class="bi bi-info-circle mr-2" style="font-size:1.2rem;"></i>
                <div>
                    <div class="font-weight-bold">Rules</div>
                    <div class="small mt-1">
                        • Warehouse wajib dipilih per item<br>
                        • GOOD + DEFECT + DAMAGED wajib sama dengan Expected<br>
                        • Jika GOOD &gt; 0 → wajib ada alokasi rack dan jumlahnya sama dengan GOOD<br>
                        • Jika DEFECT/DAMAGED &gt; 0 → pilih rack dulu, lalu pilih ID sesuai qty
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Global Error --}}
    <div class="alert alert-danger d-none" id="rowErrorBox">
        <div class="d-flex align-items-start">
            <i class="bi bi-exclamation-triangle mr-2" style="font-size:1.2rem;"></i>
            <div>
                <div class="font-weight-bold">Masih ada item yang belum valid.</div>
                <div class="small">
                    Pastikan urutannya: Warehouse → Qty cocok Expected → GOOD allocation → Pick DEFECT/DAMAGED IDs.
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

                        $defModalId = "defModal{$i}";
                        $damModalId = "damModal{$i}";
                    @endphp

                    <div class="card shadow-sm mb-3 confirm-card"
                         data-idx="{{ $i }}"
                         data-item-id="{{ $itemId }}"
                         data-product-id="{{ $pid }}"
                         data-expected="{{ $expected }}"
                         data-def-available="0"
                         data-dam-available="0"
                         data-good-alloc-sum="0"
                         data-good-alloc-ok="0">
                        <div class="card-body">

                            <div class="d-flex flex-wrap align-items-start justify-content-between">
                                <div class="mb-2">
                                    <div class="font-weight-bold">{{ $productName }}</div>
                                    <div class="text-muted small">
                                        <span class="inline-pill">product_id: <strong>{{ $pid }}</strong></span>
                                        <span class="inline-pill">item_id: <strong>{{ $itemId }}</strong></span>
                                    </div>
                                </div>

                                <div class="text-right mb-2">
                                    <div class="small text-muted">Expected</div>
                                    <div class="badge badge-secondary px-3 py-2">{{ number_format($expected) }}</div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $itemId }}">

                            {{-- Row 1: Warehouse + Qty --}}
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label mb-1">Warehouse (Stock Out) <span class="text-danger">*</span></label>
                                    <select name="items[{{ $i }}][warehouse_id]" class="form-control item-warehouse" required>
                                        <option value="" selected disabled>-- Choose Warehouse --</option>
                                        @foreach($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ (int)old("items.$i.warehouse_id") === (int)$w->id ? 'selected' : '' }}>
                                                {{ $w->warehouse_name }} {{ $w->is_main ? '(Main)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="muted-hint mt-1">
                                        Pilih warehouse dulu supaya daftar rack & ID terfilter dengan benar.
                                    </div>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">GOOD</label>
                                    <input type="number"
                                           name="items[{{ $i }}][good]"
                                           class="form-control qty-good"
                                           min="0"
                                           value="{{ old("items.$i.good", 0) }}"
                                           required
                                           disabled>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">DEFECT</label>
                                    <input type="number"
                                           name="items[{{ $i }}][defect]"
                                           class="form-control qty-defect"
                                           min="0"
                                           value="{{ old("items.$i.defect", 0) }}"
                                           required
                                           disabled>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">DAMAGED</label>
                                    <input type="number"
                                           name="items[{{ $i }}][damaged]"
                                           class="form-control qty-damaged"
                                           min="0"
                                           value="{{ old("items.$i.damaged", 0) }}"
                                           required
                                           disabled>
                                </div>
                            </div>

                            {{-- GOOD Allocation --}}
                            <div class="mt-3 good-wrap">
                                <div class="section-title">
                                    <div>
                                        <div class="ttl">GOOD Rack Allocation</div>
                                        <div class="sub">Wajib jika GOOD &gt; 0. Total qty alokasi harus sama dengan GOOD.</div>
                                    </div>
                                    <div>
                                        <span class="badge badge-light border">
                                            Allocated: <strong class="good-alloc-sum-text">0</strong>
                                        </span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered good-table mb-2">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:60%">From Rack</th>
                                                <th style="width:20%">Qty</th>
                                                <th style="width:20%"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="good-allocation-body"></tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-primary btn-add-good-rack" disabled>
                                    <i class="bi bi-plus"></i> Add Rack
                                </button>
                                <div class="muted-hint mt-1">
                                    Tips: kalau stok GOOD keluar dari 2 rack, kamu bisa tambah 2 row.
                                </div>
                            </div>

                            {{-- DEFECT / DAMAGED --}}
                            <div class="mt-3">
                                <div class="section-title mb-2">
                                    <div>
                                        <div class="ttl">DEFECT / DAMAGED Selection</div>
                                        <div class="sub">Pilih rack dulu, lalu pilih ID sesuai qty.</div>
                                    </div>
                                    <div class="small text-muted">
                                        Row Total: <span class="badge badge-light border row-total">0</span>
                                        <span class="ml-2">Status: <span class="badge badge-secondary row-status">Checking...</span></span>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <div class="btn-disabled-hint mb-2">
                                        Tombol Pick akan aktif setelah warehouse dipilih & qty &gt; 0.
                                    </div>

                                    <div class="d-flex flex-wrap mb-2" style="gap:.5rem;">
                                        <button type="button"
                                                class="btn btn-sm btn-warning btn-pick-defect"
                                                data-toggle="modal" data-target="#{{ $defModalId }}"
                                                data-bs-toggle="modal" data-bs-target="#{{ $defModalId }}"
                                                disabled>
                                            <i class="bi bi-bug mr-1"></i> Pick DEFECT IDs
                                            <span class="badge badge-dark ml-1 pick-defect-count">0</span>
                                        </button>

                                        <button type="button"
                                                class="btn btn-sm btn-danger btn-pick-damaged"
                                                data-toggle="modal" data-target="#{{ $damModalId }}"
                                                data-bs-toggle="modal" data-bs-target="#{{ $damModalId }}"
                                                disabled>
                                            <i class="bi bi-exclamation-octagon mr-1"></i> Pick DAMAGED IDs
                                            <span class="badge badge-dark ml-1 pick-damaged-count">0</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="small text-muted mb-1">Selected DEFECT IDs:</div>
                                    <div class="defect-chips"></div>

                                    <div class="small text-muted mt-2 mb-1">Selected DAMAGED IDs:</div>
                                    <div class="damaged-chips"></div>

                                    <div class="defect-hidden"></div>
                                    <div class="damaged-hidden"></div>

                                    <div class="small text-muted mt-2">
                                        * Jika DEFECT/DAMAGED qty = 0, pilihan ID akan otomatis dibersihkan.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DEFECT MODAL --}}
                    <div class="modal fade" id="{{ $defModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title mb-0">Pick DEFECT IDs</h5>
                                        <div class="small text-muted">
                                            Step: pilih rack → pilih ID sesuai qty DEFECT.
                                        </div>
                                    </div>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label mb-1">Rack (Filter)</label>
                                            <select class="form-control modal-rack defect-rack">
                                                <option value="">-- All Racks --</option>
                                            </select>
                                            <div class="small text-muted mt-1">
                                                Disarankan pilih 1 rack supaya teknisi tidak bingung ambil barang.
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="alert alert-warning small mb-0">
                                                Available DEFECT:
                                                <strong class="def-available-text">0</strong>
                                                <span class="mx-1">•</span>
                                                Required:
                                                <strong class="def-required-text">0</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pick-defect-body mt-2"></div>
                                </div>

                                <div class="modal-footer">
                                    <div class="mr-auto small text-muted">
                                        Selected:
                                        <span class="counter-badge defect-selected-count" style="background:#343a40;">0</span>
                                        <span class="mx-1">•</span>
                                        Required:
                                        <span class="counter-badge defect-required-count" style="background:#f0ad4e;">0</span>
                                    </div>

                                    <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-warning btn-save-defect" data-dismiss="modal" data-bs-dismiss="modal">
                                        Save DEFECT Selection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DAMAGED MODAL --}}
                    <div class="modal fade" id="{{ $damModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title mb-0">Pick DAMAGED IDs</h5>
                                        <div class="small text-muted">
                                            Step: pilih rack → pilih ID sesuai qty DAMAGED.
                                        </div>
                                    </div>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label mb-1">Rack (Filter)</label>
                                            <select class="form-control modal-rack damaged-rack">
                                                <option value="">-- All Racks --</option>
                                            </select>
                                            <div class="small text-muted mt-1">
                                                Disarankan pilih 1 rack supaya teknisi tidak bingung ambil barang.
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <div class="alert alert-danger small mb-0">
                                                Available DAMAGED:
                                                <strong class="dam-available-text">0</strong>
                                                <span class="mx-1">•</span>
                                                Required:
                                                <strong class="dam-required-text">0</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pick-damaged-body mt-2"></div>
                                </div>

                                <div class="modal-footer">
                                    <div class="mr-auto small text-muted">
                                        Selected:
                                        <span class="counter-badge damaged-selected-count" style="background:#343a40;">0</span>
                                        <span class="mx-1">•</span>
                                        Required:
                                        <span class="counter-badge damaged-required-count" style="background:#d9534f;">0</span>
                                    </div>

                                    <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-danger btn-save-damaged" data-dismiss="modal" data-bs-dismiss="modal">
                                        Save DAMAGED Selection
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

            {{-- Side Validation --}}
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
                            Tips: pilih warehouse dulu → isi qty → lakukan allocation/pick.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script>
(function(){
    // Expect:
    // DEFECT_DATA[pid][wid] => [{id, defect_type, description, rack_id?}, ...]
    // DAMAGED_DATA[pid][wid] => [{id, reason, rack_id?}, ...]
    // RACKS_BY_WAREHOUSE[wid] => [{id, code, name}, ...]
    const DEFECT_DATA = @json($defectData ?? []);
    const DAMAGED_DATA = @json($damagedData ?? []);
    const RACKS_BY_WAREHOUSE = @json($racksByWarehouse ?? []);

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

    function getWarehouseId(card){
        const sel = card.querySelector('.item-warehouse');
        return sel ? toInt(sel.value) : 0;
    }

    function getProductId(card){
        return toInt(card.dataset.productId);
    }

    function getCardValues(card){
        const expected = toInt(card.dataset.expected);
        const good = toInt(card.querySelector('.qty-good') ? card.querySelector('.qty-good').value : 0);
        const defect = toInt(card.querySelector('.qty-defect') ? card.querySelector('.qty-defect').value : 0);
        const damaged = toInt(card.querySelector('.qty-damaged') ? card.querySelector('.qty-damaged').value : 0);
        return { expected, good, defect, damaged, total: good + defect + damaged };
    }

    function racksForWarehouse(wid){
        // wid keys might be string; normalize
        const arr = RACKS_BY_WAREHOUSE[wid] || RACKS_BY_WAREHOUSE[String(wid)] || [];
        // each item might be object with properties like id, code, name
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
        if (includeAll) html += `<option value="">-- All Racks --</option>`;
        racks.forEach(r => {
            html += `<option value="${toInt(r.id)}">${rackLabel(r)}</option>`;
        });
        return html;
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

    function selectedIdsFromHidden(holder){
        return Array.from(holder.querySelectorAll('input[type="hidden"]')).map(i => String(i.value));
    }

    function setHiddenArray(hiddenBox, name, ids){
        hiddenBox.innerHTML = '';
        (ids || []).forEach(id => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = name;
            h.value = id;
            hiddenBox.appendChild(h);
        });
    }

    function renderChips(chipBox, ids){
        chipBox.innerHTML = '';
        if (!ids || ids.length === 0) {
            chipBox.innerHTML = '<span class="text-muted small">-</span>';
            return;
        }
        ids.forEach(id => {
            const chip = document.createElement('span');
            chip.className = 'id-chip';
            chip.innerHTML = `<span class="badge badge-dark">#${id}</span><span class="x" title="Remove">&times;</span>`;
            chip.querySelector('.x').addEventListener('click', () => {
                const card = chipBox.closest('.confirm-card');
                if (!card) return;

                const isDef = chipBox.classList.contains('defect-chips');
                const box = isDef ? card.querySelector('.defect-hidden') : card.querySelector('.damaged-hidden');
                const name = isDef
                    ? `items[${card.dataset.idx}][selected_defect_ids][]`
                    : `items[${card.dataset.idx}][selected_damaged_ids][]`;

                const current = selectedIdsFromHidden(box);
                const next = current.filter(x => String(x) !== String(id));
                setHiddenArray(box, name, next);
                renderChips(chipBox, next);
                updateCard(card); refreshGlobalState();
            });
            chipBox.appendChild(chip);
        });
    }

    function enforceMaxChecks(listEl, selector, max){
        const checks = Array.from(listEl.querySelectorAll(selector));
        const checked = checks.filter(c => c.checked);

        if (max <= 0) {
            checks.forEach(c => { c.checked = false; c.disabled = true; });
            return;
        }
        checks.forEach(c => c.disabled = false);

        if (checked.length > max) {
            for (let i = max; i < checked.length; i++) checked[i].checked = false;
        }
        const nowChecked = checks.filter(c => c.checked);
        if (nowChecked.length >= max) {
            checks.forEach(c => { if (!c.checked) c.disabled = true; });
        }
    }

    function updateModalCounters(modalEl, selectedCount, requiredCount, type){
        const sel = modalEl.querySelector('.' + type + '-selected-count');
        const req = modalEl.querySelector('.' + type + '-required-count');
        if (sel) sel.textContent = String(selectedCount);
        if (req) req.textContent = String(requiredCount);
    }

    function updateAvailabilityFromWarehouse(card){
        const pid = getProductId(card);
        const wid = getWarehouseId(card);

        const defAvail = getAvailableDefectList(pid, wid).length;
        const damAvail = getAvailableDamagedList(pid, wid).length;

        card.dataset.defAvailable = String(defAvail);
        card.dataset.damAvailable = String(damAvail);
    }

    // ---------------------------
    // GOOD Allocation UI
    // ---------------------------
    function getGoodAllocRows(card){
        return Array.from(card.querySelectorAll('.good-alloc-row'));
    }

    function sumGoodAlloc(card){
        let sum = 0;
        getGoodAllocRows(card).forEach(tr => {
            const qtyEl = tr.querySelector('.good-alloc-qty');
            sum += toInt(qtyEl ? qtyEl.value : 0);
        });
        card.dataset.goodAllocSum = String(sum);
        const txt = card.querySelector('.good-alloc-sum-text');
        if (txt) txt.textContent = String(sum);
        return sum;
    }

    function rebuildGoodAllocNames(card){
        // set proper name: items[i][good_allocations][k][from_rack_id] and qty
        const idx = card.dataset.idx;
        getGoodAllocRows(card).forEach((tr, k) => {
            const rackEl = tr.querySelector('.good-alloc-rack');
            const qtyEl  = tr.querySelector('.good-alloc-qty');
            if (rackEl) rackEl.name = `items[${idx}][good_allocations][${k}][from_rack_id]`;
            if (qtyEl)  qtyEl.name  = `items[${idx}][good_allocations][${k}][qty]`;
        });
    }

    function addGoodAllocRow(card){
        const wid = getWarehouseId(card);
        if (wid <= 0) return;

        const tbody = card.querySelector('.good-allocation-body');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.className = 'good-alloc-row';
        tr.innerHTML = `
            <td>
                <select class="form-control form-control-sm good-alloc-rack">
                    <option value="" selected disabled>-- Choose Rack --</option>
                    ${buildRackOptions(wid, false)}
                </select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm good-alloc-qty" min="1" value="1">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-light border btn-remove-good-row" title="Remove">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        tr.querySelector('.btn-remove-good-row').addEventListener('click', () => {
            tr.remove();
            rebuildGoodAllocNames(card);
            sumGoodAlloc(card);
            updateCard(card); refreshGlobalState();
        });

        ['change','input'].forEach(ev => {
            const rackEl = tr.querySelector('.good-alloc-rack');
            const qtyEl  = tr.querySelector('.good-alloc-qty');
            if (rackEl) rackEl.addEventListener(ev, () => { rebuildGoodAllocNames(card); sumGoodAlloc(card); updateCard(card); refreshGlobalState(); });
            if (qtyEl)  qtyEl.addEventListener(ev, () => { rebuildGoodAllocNames(card); sumGoodAlloc(card); updateCard(card); refreshGlobalState(); });
        });

        rebuildGoodAllocNames(card);
        sumGoodAlloc(card);
    }

    function resetGoodAllocForWarehouse(card){
        const tbody = card.querySelector('.good-allocation-body');
        if (tbody) tbody.innerHTML = '';
        sumGoodAlloc(card);
        rebuildGoodAllocNames(card);
    }

    // ---------------------------
    // Modal list builders (rack filter)
    // ---------------------------
    function buildDefectListHtml(pid, wid, rackFilterId){
        let list = getAvailableDefectList(pid, wid);

        const rf = toInt(rackFilterId);
        if (rf > 0) {
            // Only works if data has rack_id
            list = list.filter(r => toInt(r.rack_id) === rf);
        }

        if (!list.length) {
            const msg = (rf > 0)
                ? `No DEFECT stock for selected rack.`
                : `No DEFECT stock available for this product in selected warehouse.`;
            return `<div class="alert alert-danger mb-0">${msg}</div>`;
        }

        let html = `<div class="list-group pick-list defect-list">`;
        list.forEach(r => {
            const label = `ID ${r.id}`;
            const extra = [];
            if (r.defect_type) extra.push(String(r.defect_type));
            if (r.description) extra.push(fmtText(r.description, 70));
            // show rack id hint (if exists)
            if (r.rack_id) extra.push('Rack #' + String(r.rack_id));
            const sub = extra.length ? extra.join(' | ') : '';
            html += `
                <label class="list-group-item">
                    <div class="item-wrap">
                        <div class="check-wrap">
                            <input class="defect-check" type="checkbox" value="${r.id}">
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold">${label}</div>
                            ${sub ? `<div class="small text-muted">${sub}</div>` : ``}
                        </div>
                    </div>
                </label>
            `;
        });
        html += `</div>`;
        return html;
    }

    function buildDamagedListHtml(pid, wid, rackFilterId){
        let list = getAvailableDamagedList(pid, wid);

        const rf = toInt(rackFilterId);
        if (rf > 0) {
            list = list.filter(r => toInt(r.rack_id) === rf);
        }

        if (!list.length) {
            const msg = (rf > 0)
                ? `No DAMAGED stock for selected rack.`
                : `No DAMAGED stock available for this product in selected warehouse.`;
            return `<div class="alert alert-danger mb-0">${msg}</div>`;
        }

        let html = `<div class="list-group pick-list damaged-list">`;
        list.forEach(r => {
            const label = `ID ${r.id}`;
            const extra = [];
            if (r.reason) extra.push(fmtText(r.reason, 70));
            if (r.rack_id) extra.push('Rack #' + String(r.rack_id));
            const sub = extra.length ? extra.join(' | ') : '';
            html += `
                <label class="list-group-item">
                    <div class="item-wrap">
                        <div class="check-wrap">
                            <input class="damaged-check" type="checkbox" value="${r.id}">
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold">${label}</div>
                            ${sub ? `<div class="small text-muted">${sub}</div>` : ``}
                        </div>
                    </div>
                </label>
            `;
        });
        html += `</div>`;
        return html;
    }

    function updatePickButtonState(card){
        const v = getCardValues(card);
        const wid = getWarehouseId(card);

        const btnDef = card.querySelector('.btn-pick-defect');
        const btnDam = card.querySelector('.btn-pick-damaged');

        const defAvail = toInt(card.dataset.defAvailable);
        const damAvail = toInt(card.dataset.damAvailable);

        if (btnDef) btnDef.disabled = !(wid > 0 && v.defect > 0 && defAvail > 0);
        if (btnDam) btnDam.disabled = !(wid > 0 && v.damaged > 0 && damAvail > 0);
    }

    function updateQtyInputsEnabled(card){
        const wid = getWarehouseId(card);
        const good = card.querySelector('.qty-good');
        const def  = card.querySelector('.qty-defect');
        const dam  = card.querySelector('.qty-damaged');
        [good, def, dam].forEach(el => { if (el) el.disabled = !(wid > 0); });

        // add good rack button enabled only if warehouse chosen
        const addBtn = card.querySelector('.btn-add-good-rack');
        if (addBtn) addBtn.disabled = !(wid > 0);
    }

    function updateCard(card){
        const v = getCardValues(card);
        const wid = getWarehouseId(card);

        updateAvailabilityFromWarehouse(card);
        updateQtyInputsEnabled(card);

        const defHidden = card.querySelector('.defect-hidden');
        const damHidden = card.querySelector('.damaged-hidden');

        const defIds = defHidden ? selectedIdsFromHidden(defHidden) : [];
        const damIds = damHidden ? selectedIdsFromHidden(damHidden) : [];

        // GOOD allocation validation
        const goodAllocSum = sumGoodAlloc(card);
        let goodAllocOk = true;
        if (v.good > 0) {
            const rows = getGoodAllocRows(card);
            if (rows.length <= 0) goodAllocOk = false;
            // every row must have rack_id and qty >= 1
            rows.forEach(tr => {
                const rackId = toInt(tr.querySelector('.good-alloc-rack') ? tr.querySelector('.good-alloc-rack').value : 0);
                const qty    = toInt(tr.querySelector('.good-alloc-qty') ? tr.querySelector('.good-alloc-qty').value : 0);
                if (rackId <= 0 || qty <= 0) goodAllocOk = false;
            });
            if (goodAllocSum !== v.good) goodAllocOk = false;
        }
        card.dataset.goodAllocOk = goodAllocOk ? '1' : '0';

        const qtyOk = (v.total === v.expected);
        const whOk = (wid > 0);

        const defOk = (v.defect <= 0) ? true : (defIds.length === v.defect);
        const damOk = (v.damaged <= 0) ? true : (damIds.length === v.damaged);

        const defAvail = toInt(card.dataset.defAvailable);
        const damAvail = toInt(card.dataset.damAvailable);

        const defStockOk = (v.defect <= defAvail);
        const damStockOk = (v.damaged <= damAvail);

        const ok = whOk && qtyOk && defOk && damOk && defStockOk && damStockOk && goodAllocOk;

        card.classList.add('border');
        card.classList.toggle('border-danger', !ok);
        card.classList.toggle('border-success', ok && v.expected > 0);

        const totalEl = card.querySelector('.row-total');
        if (totalEl) totalEl.textContent = String(v.total);

        const statusEl = card.querySelector('.row-status');
        if (statusEl) {
            let msg = 'OK';
            if (!whOk) msg = 'Choose warehouse';
            else if (!qtyOk) msg = 'Qty mismatch';
            else if (!goodAllocOk) msg = 'Allocate GOOD racks';
            else if (!defStockOk) msg = 'DEFECT > stock';
            else if (!damStockOk) msg = 'DAMAGED > stock';
            else if (!defOk) msg = 'Pick DEFECT IDs';
            else if (!damOk) msg = 'Pick DAMAGED IDs';

            statusEl.textContent = ok ? 'OK' : msg;
            statusEl.className = 'badge ' + (ok ? 'badge-success' : 'badge-danger') + ' row-status';
        }

        // auto clear IDs if qty becomes 0
        if (v.defect <= 0 && defHidden && defIds.length > 0) {
            setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
            renderChips(card.querySelector('.defect-chips'), []);
        }
        if (v.damaged <= 0 && damHidden && damIds.length > 0) {
            setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);
            renderChips(card.querySelector('.damaged-chips'), []);
        }

        // badges
        const defCountBadge = card.querySelector('.pick-defect-count');
        if (defCountBadge) defCountBadge.textContent = String(defIds.length);

        const damCountBadge = card.querySelector('.pick-damaged-count');
        if (damCountBadge) damCountBadge.textContent = String(damIds.length);

        updatePickButtonState(card);
        return ok;
    }

    function hasAnyError(){
        const cards = document.querySelectorAll('.confirm-card');
        for (const card of cards) {
            if (!updateCard(card)) return true;
        }
        return false;
    }

    function refreshGlobalState(){
        const err = hasAnyError();

        const box = document.getElementById('rowErrorBox');
        const btn = document.getElementById('submitBtn');
        const stat = document.getElementById('validStat');

        if (box) box.classList.toggle('d-none', !err);
        if (btn) btn.disabled = err;

        if (stat) {
            stat.textContent = err ? 'Invalid' : 'Ready';
            stat.className = 'badge ' + (err ? 'badge-danger' : 'badge-success');
        }
    }

    function onModalShown(modalEl, cb){
        if (!modalEl) return;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(modalEl).on('shown.bs.modal', cb);
        } else {
            modalEl.addEventListener('shown.bs.modal', cb);
        }
    }

    function onModalChange(modalEl, cb){
        if (!modalEl) return;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(modalEl).on('change', cb);
        } else {
            modalEl.addEventListener('change', cb);
        }
    }

    // init cards
    const cards = document.querySelectorAll('.confirm-card');
    cards.forEach(card => {
        const defHidden = card.querySelector('.defect-hidden');
        const damHidden = card.querySelector('.damaged-hidden');

        // initialize hidden inputs empty
        setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
        setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);

        renderChips(card.querySelector('.defect-chips'), []);
        renderChips(card.querySelector('.damaged-chips'), []);

        // Add good rack row button
        const addGoodBtn = card.querySelector('.btn-add-good-rack');
        if (addGoodBtn) {
            addGoodBtn.addEventListener('click', () => {
                addGoodAllocRow(card);
                updateCard(card); refreshGlobalState();
            });
        }

        // qty inputs update
        ['.qty-good', '.qty-defect', '.qty-damaged'].forEach(sel => {
            const el = card.querySelector(sel);
            if (el) el.addEventListener('input', () => { updateCard(card); refreshGlobalState(); });
        });

        // warehouse change
        const whSel = card.querySelector('.item-warehouse');
        if (whSel) {
            whSel.addEventListener('change', () => {
                // clear selections when warehouse changes
                setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
                setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);
                renderChips(card.querySelector('.defect-chips'), []);
                renderChips(card.querySelector('.damaged-chips'), []);

                // reset GOOD allocations because rack list changes
                resetGoodAllocForWarehouse(card);

                // enable qty inputs now
                updateQtyInputsEnabled(card);

                updateCard(card);
                refreshGlobalState();
            });
        }

        // DEFECT modal hooks
        const btnDef = card.querySelector('.btn-pick-defect');
        const defTarget = btnDef ? (btnDef.getAttribute('data-bs-target') || btnDef.getAttribute('data-target')) : '';
        const defModal = defTarget ? document.querySelector(defTarget) : null;

        if (defModal) {
            const rackSelect = defModal.querySelector('.defect-rack');

            function renderDefectModal(){
                const wid = getWarehouseId(card);
                const pid = getProductId(card);
                const v = getCardValues(card);
                const required = v.defect;

                if (wid <= 0) {
                    alert('Pilih warehouse dulu untuk item ini.');
                    if (window.jQuery) window.jQuery(defModal).modal('hide');
                    return;
                }

                // fill rack options based on warehouse
                if (rackSelect) {
                    rackSelect.innerHTML = buildRackOptions(wid, true);
                    rackSelect.value = ''; // default All
                }

                const availTotal = getAvailableDefectList(pid, wid).length;
                const tAvail = defModal.querySelector('.def-available-text');
                const tReq = defModal.querySelector('.def-required-text');
                if (tAvail) tAvail.textContent = String(availTotal);
                if (tReq) tReq.textContent = String(required);

                const body = defModal.querySelector('.pick-defect-body');
                if (body) body.innerHTML = buildDefectListHtml(pid, wid, '');

                const current = new Set(selectedIdsFromHidden(defHidden));
                const list = defModal.querySelector('.defect-list');
                if (list) {
                    Array.from(list.querySelectorAll('.defect-check')).forEach(ch => {
                        ch.checked = current.has(String(ch.value));
                    });
                    enforceMaxChecks(list, '.defect-check', required);
                    const now = Array.from(list.querySelectorAll('.defect-check')).filter(c => c.checked).length;
                    updateModalCounters(defModal, now, required, 'defect');
                } else {
                    updateModalCounters(defModal, 0, required, 'defect');
                }
            }

            onModalShown(defModal, renderDefectModal);

            if (rackSelect) {
                rackSelect.addEventListener('change', () => {
                    const wid = getWarehouseId(card);
                    const pid = getProductId(card);
                    const v = getCardValues(card);
                    const required = v.defect;

                    const body = defModal.querySelector('.pick-defect-body');
                    if (body) body.innerHTML = buildDefectListHtml(pid, wid, rackSelect.value);

                    const current = new Set(selectedIdsFromHidden(defHidden));
                    const list = defModal.querySelector('.defect-list');
                    if (list) {
                        Array.from(list.querySelectorAll('.defect-check')).forEach(ch => {
                            ch.checked = current.has(String(ch.value));
                        });
                        enforceMaxChecks(list, '.defect-check', required);
                        const now = Array.from(list.querySelectorAll('.defect-check')).filter(c => c.checked).length;
                        updateModalCounters(defModal, now, required, 'defect');
                    } else {
                        updateModalCounters(defModal, 0, required, 'defect');
                    }
                });
            }

            onModalChange(defModal, (e) => {
                if (!e.target || !e.target.classList.contains('defect-check')) return;
                const v = getCardValues(card);
                const required = v.defect;
                const list = defModal.querySelector('.defect-list');
                if (!list) return;
                enforceMaxChecks(list, '.defect-check', required);
                const now = Array.from(list.querySelectorAll('.defect-check')).filter(c => c.checked).length;
                updateModalCounters(defModal, now, required, 'defect');
            });

            const saveBtn = defModal.querySelector('.btn-save-defect');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    const list = defModal.querySelector('.defect-list');
                    const picked = list
                        ? Array.from(list.querySelectorAll('.defect-check')).filter(c => c.checked).map(c => String(c.value))
                        : [];
                    setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, picked);
                    renderChips(card.querySelector('.defect-chips'), picked);
                    updateCard(card);
                    refreshGlobalState();
                });
            }
        }

        // DAMAGED modal hooks
        const btnDam = card.querySelector('.btn-pick-damaged');
        const damTarget = btnDam ? (btnDam.getAttribute('data-bs-target') || btnDam.getAttribute('data-target')) : '';
        const damModal = damTarget ? document.querySelector(damTarget) : null;

        if (damModal) {
            const rackSelect = damModal.querySelector('.damaged-rack');

            function renderDamagedModal(){
                const wid = getWarehouseId(card);
                const pid = getProductId(card);
                const v = getCardValues(card);
                const required = v.damaged;

                if (wid <= 0) {
                    alert('Pilih warehouse dulu untuk item ini.');
                    if (window.jQuery) window.jQuery(damModal).modal('hide');
                    return;
                }

                if (rackSelect) {
                    rackSelect.innerHTML = buildRackOptions(wid, true);
                    rackSelect.value = '';
                }

                const availTotal = getAvailableDamagedList(pid, wid).length;
                const tAvail = damModal.querySelector('.dam-available-text');
                const tReq = damModal.querySelector('.dam-required-text');
                if (tAvail) tAvail.textContent = String(availTotal);
                if (tReq) tReq.textContent = String(required);

                const body = damModal.querySelector('.pick-damaged-body');
                if (body) body.innerHTML = buildDamagedListHtml(pid, wid, '');

                const current = new Set(selectedIdsFromHidden(damHidden));
                const list = damModal.querySelector('.damaged-list');
                if (list) {
                    Array.from(list.querySelectorAll('.damaged-check')).forEach(ch => {
                        ch.checked = current.has(String(ch.value));
                    });
                    enforceMaxChecks(list, '.damaged-check', required);
                    const now = Array.from(list.querySelectorAll('.damaged-check')).filter(c => c.checked).length;
                    updateModalCounters(damModal, now, required, 'damaged');
                } else {
                    updateModalCounters(damModal, 0, required, 'damaged');
                }
            }

            onModalShown(damModal, renderDamagedModal);

            if (rackSelect) {
                rackSelect.addEventListener('change', () => {
                    const wid = getWarehouseId(card);
                    const pid = getProductId(card);
                    const v = getCardValues(card);
                    const required = v.damaged;

                    const body = damModal.querySelector('.pick-damaged-body');
                    if (body) body.innerHTML = buildDamagedListHtml(pid, wid, rackSelect.value);

                    const current = new Set(selectedIdsFromHidden(damHidden));
                    const list = damModal.querySelector('.damaged-list');
                    if (list) {
                        Array.from(list.querySelectorAll('.damaged-check')).forEach(ch => {
                            ch.checked = current.has(String(ch.value));
                        });
                        enforceMaxChecks(list, '.damaged-check', required);
                        const now = Array.from(list.querySelectorAll('.damaged-check')).filter(c => c.checked).length;
                        updateModalCounters(damModal, now, required, 'damaged');
                    } else {
                        updateModalCounters(damModal, 0, required, 'damaged');
                    }
                });
            }

            onModalChange(damModal, (e) => {
                if (!e.target || !e.target.classList.contains('damaged-check')) return;
                const v = getCardValues(card);
                const required = v.damaged;
                const list = damModal.querySelector('.damaged-list');
                if (!list) return;
                enforceMaxChecks(list, '.damaged-check', required);
                const now = Array.from(list.querySelectorAll('.damaged-check')).filter(c => c.checked).length;
                updateModalCounters(damModal, now, required, 'damaged');
            });

            const saveBtn = damModal.querySelector('.btn-save-damaged');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    const list = damModal.querySelector('.damaged-list');
                    const picked = list
                        ? Array.from(list.querySelectorAll('.damaged-check')).filter(c => c.checked).map(c => String(c.value))
                        : [];
                    setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, picked);
                    renderChips(card.querySelector('.damaged-chips'), picked);
                    updateCard(card);
                    refreshGlobalState();
                });
            }
        }

        // initial state (warehouse not selected)
        updateQtyInputsEnabled(card);
        resetGoodAllocForWarehouse(card);
        updateCard(card);
    });

    refreshGlobalState();

    const form = document.getElementById('confirmForm');
    if (form) {
        form.addEventListener('submit', function(e){
            refreshGlobalState();
            if (hasAnyError()) {
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
@endpush
