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
    $branchId = (int) ($saleDelivery->branch_id ?? 0);

    $fmt = function ($txt, $max=55) {
        $t = trim((string) ($txt ?? ''));
        if ($t === '') return '';
        if (mb_strlen($t) <= $max) return $t;
        return mb_substr($t, 0, $max) . '...';
    };

    $dateText = $saleDelivery->date
        ? (method_exists($saleDelivery->date, 'format') ? $saleDelivery->date->format('d M Y') : \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y'))
        : '-';
@endphp

<style>
    .id-chip {
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.2rem .5rem; border-radius:999px;
        border:1px solid #ddd; background:#fff;
        margin:.15rem .25rem .15rem 0; font-size:.85rem;
    }
    .id-chip .x { cursor:pointer; opacity:.65; margin-left:.35rem; }
    .id-chip .x:hover { opacity:1; }

    .pick-list .list-group-item{
        padding: .75rem 1rem;
    }
    .pick-list .list-group-item .form-check-input,
    .pick-list .list-group-item input[type="checkbox"]{
        margin-top: .2rem;
    }
    .pick-list .item-wrap{
        display:flex;
        align-items:flex-start;
        gap:.75rem;
    }
    .pick-list .check-wrap{
        width: 24px;
        flex: 0 0 24px;
        display:flex;
        justify-content:center;
    }

    .counter-badge{
        color:#fff !important;
        padding:.35rem .5rem;
        border-radius:.25rem;
        font-weight:600;
    }

    .btn-space > .btn { margin-right:.5rem; margin-bottom:.5rem; }
    .btn-space > .btn:last-child { margin-right:0; }

    .warehouse-required-hint{
        font-size:.85rem;
        color:#6c757d;
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
                        <span class="badge badge-light border">
                            <i class="bi bi-truck mr-1"></i> {{ $saleDelivery->reference }}
                        </span>
                    </div>
                    <div class="text-muted small mt-1">
                        Date: <strong>{{ $dateText }}</strong>
                        <span class="mx-1">•</span>
                        Warehouse: <strong>Per Item (selected below)</strong>
                    </div>
                </div>

                <div class="mb-2">
                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                        <i class="bi bi-arrow-left mr-1"></i> Back
                    </a>
                </div>
            </div>

            <hr class="my-3">

            <div class="alert alert-info mb-0 d-flex align-items-start">
                <i class="bi bi-info-circle mr-2" style="font-size:1.2rem;"></i>
                <div>
                    <div class="font-weight-bold">Rule (STRICT + IDs REQUIRED)</div>
                    <div class="small mt-1">
                        Untuk setiap item: <strong>WAREHOUSE wajib dipilih</strong> lalu
                        <strong>GOOD + DEFECT + DAMAGED wajib sama dengan Expected</strong>.
                        <br>
                        Jika <strong>DEFECT &gt; 0</strong> maka <strong>wajib pilih ID defect persis sebanyak DEFECT</strong>.
                        Jika <strong>DAMAGED &gt; 0</strong> maka <strong>wajib pilih ID damaged persis sebanyak DAMAGED</strong>.
                    </div>
                    <div class="small mt-2">
                        Legend:
                        <span class="badge badge-success">Good</span>
                        <span class="badge badge-warning">Defect</span>
                        <span class="badge badge-danger">Damaged</span>
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
                    Pastikan: (1) warehouse dipilih, (2) total qty cocok Expected, (3) DEFECT & DAMAGED kalau &gt; 0 wajib pilih ID-nya sesuai qty.
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
                         data-dam-available="0">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-start justify-content-between">
                                <div class="mb-2">
                                    <div class="font-weight-bold">{{ $productName }}</div>
                                    <div class="text-muted small">
                                        product_id: {{ $pid }}
                                        <span class="mx-1">•</span>
                                        item_id: {{ $itemId }}
                                    </div>
                                </div>

                                <div class="text-right mb-2">
                                    <div class="small text-muted">Expected</div>
                                    <div class="badge badge-secondary px-3 py-2">{{ number_format($expected) }}</div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="row">
                                <input type="hidden" name="items[{{ $i }}][id]" value="{{ $itemId }}">

                                {{-- ✅ WAREHOUSE per item --}}
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
                                    <div class="warehouse-required-hint mt-1">
                                        * Pilih warehouse dulu, baru pilih DEFECT/DAMAGED IDs.
                                    </div>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">GOOD</label>
                                    <input type="number" name="items[{{ $i }}][good]"
                                           class="form-control qty-good" min="0"
                                           value="{{ old("items.$i.good", 0) }}" required>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">DEFECT</label>
                                    <input type="number" name="items[{{ $i }}][defect]"
                                           class="form-control qty-defect" min="0"
                                           value="{{ old("items.$i.defect", 0) }}" required>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label mb-1">DAMAGED</label>
                                    <input type="number" name="items[{{ $i }}][damaged]"
                                           class="form-control qty-damaged" min="0"
                                           value="{{ old("items.$i.damaged", 0) }}" required>
                                </div>

                                <div class="col-12">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mt-2">
                                        <div class="small text-muted mb-2">
                                            Row Total:
                                            <span class="badge badge-light border row-total">0</span>
                                            <span class="ml-2">
                                                Status:
                                                <span class="badge badge-secondary row-status">Checking...</span>
                                            </span>
                                        </div>

                                        <div class="d-flex flex-wrap btn-space">
                                            <button type="button"
                                                    class="btn btn-sm btn-warning btn-pick-defect"
                                                    data-toggle="modal" data-target="#{{ $defModalId }}"
                                                    data-bs-toggle="modal" data-bs-target="#{{ $defModalId }}">
                                                <i class="bi bi-bug mr-1"></i> Pick DEFECT IDs
                                                <span class="badge badge-dark ml-1 pick-defect-count">0</span>
                                            </button>

                                            <button type="button"
                                                    class="btn btn-sm btn-danger btn-pick-damaged"
                                                    data-toggle="modal" data-target="#{{ $damModalId }}"
                                                    data-bs-toggle="modal" data-bs-target="#{{ $damModalId }}">
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
                                    </div>

                                    <div class="small text-muted mt-2">
                                        * Jika DEFECT/DAMAGED qty = 0, pilihan ID akan otomatis dibersihkan.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DEFECT MODAL (content dibuat dari JS sesuai warehouse) --}}
                    <div class="modal fade" id="{{ $defModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title mb-0">Pick DEFECT IDs</h5>
                                        <div class="small text-muted">
                                            Pilih persis sesuai qty DEFECT (warehouse mengikuti dropdown item).
                                        </div>
                                    </div>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <div class="alert alert-warning small mb-3">
                                        Available DEFECT stock:
                                        <strong class="def-available-text">0</strong>
                                        <span class="mx-1">•</span>
                                        Required = qty DEFECT pada item ini.
                                    </div>

                                    <div class="pick-defect-body"></div>
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

                    {{-- DAMAGED MODAL (content dibuat dari JS sesuai warehouse) --}}
                    <div class="modal fade" id="{{ $damModalId }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title mb-0">Pick DAMAGED IDs</h5>
                                        <div class="small text-muted">
                                            Pilih persis sesuai qty DAMAGED (warehouse mengikuti dropdown item).
                                        </div>
                                    </div>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <div class="alert alert-danger small mb-3">
                                        Available DAMAGED stock:
                                        <strong class="dam-available-text">0</strong>
                                        <span class="mx-1">•</span>
                                        Required = qty DAMAGED pada item ini.
                                    </div>

                                    <div class="pick-damaged-body"></div>
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

            <div class="col-lg-4">
                <div class="card shadow-sm position-sticky" style="top: 90px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="font-weight-bold">Validation</div>
                            <span class="badge badge-secondary" id="validStat">Checking...</span>
                        </div>

                        <div class="text-muted small mt-2">
                            Tombol confirm nonaktif jika ada item invalid (warehouse belum dipilih / qty mismatch / ID selection mismatch).
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
                            Tips: pilih warehouse dulu, lalu isi qty DEFECT/DAMAGED dan klik <strong>Pick IDs</strong>.
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
    const DEFECT_DATA = @json($defectData ?? []);
    const DAMAGED_DATA = @json($damagedData ?? []);

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

    function getAvailableDefectList(pid, wid){
        if (!pid || !wid) return [];
        if (!DEFECT_DATA[pid]) return [];
        return DEFECT_DATA[pid][wid] || [];
    }

    function getAvailableDamagedList(pid, wid){
        if (!pid || !wid) return [];
        if (!DAMAGED_DATA[pid]) return [];
        return DAMAGED_DATA[pid][wid] || [];
    }

    function getCardValues(card){
        const expected = toInt(card.dataset.expected);
        const good = toInt(card.querySelector('.qty-good') ? card.querySelector('.qty-good').value : 0);
        const defect = toInt(card.querySelector('.qty-defect') ? card.querySelector('.qty-defect').value : 0);
        const damaged = toInt(card.querySelector('.qty-damaged') ? card.querySelector('.qty-damaged').value : 0);
        return { expected, good, defect, damaged, total: good + defect + damaged };
    }

    function getTargetSelector(btn){
        if (!btn) return '';
        return btn.getAttribute('data-bs-target') || btn.getAttribute('data-target') || '';
    }

    function selectedIdsFromHidden(holder){
        return Array.from(holder.querySelectorAll('input[type="hidden"]')).map(i => String(i.value));
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

                const box = chipBox.classList.contains('defect-chips') ? card.querySelector('.defect-hidden') : card.querySelector('.damaged-hidden');
                const name = chipBox.classList.contains('defect-chips')
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

    function buildDefectListHtml(pid, wid){
        const list = getAvailableDefectList(pid, wid);
        if (!list.length) {
            return `<div class="alert alert-danger mb-0">No DEFECT stock available for this product in selected warehouse.</div>`;
        }

        let html = `<div class="list-group pick-list defect-list">`;
        list.forEach(r => {
            const label = `ID ${r.id}`;
            const extra = [];
            if (r.defect_type) extra.push(String(r.defect_type));
            if (r.description) extra.push(fmtText(r.description, 70));
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

    function buildDamagedListHtml(pid, wid){
        const list = getAvailableDamagedList(pid, wid);
        if (!list.length) {
            return `<div class="alert alert-danger mb-0">No DAMAGED stock available for this product in selected warehouse.</div>`;
        }

        let html = `<div class="list-group pick-list damaged-list">`;
        list.forEach(r => {
            const label = `ID ${r.id}`;
            const sub = r.reason ? fmtText(r.reason, 70) : '';
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

        // wajib pilih warehouse dulu
        if (btnDef) btnDef.disabled = !(wid > 0 && v.defect > 0 && defAvail > 0);
        if (btnDam) btnDam.disabled = !(wid > 0 && v.damaged > 0 && damAvail > 0);
    }

    function updateAvailabilityFromWarehouse(card){
        const pid = getProductId(card);
        const wid = getWarehouseId(card);

        const defAvail = getAvailableDefectList(pid, wid).length;
        const damAvail = getAvailableDamagedList(pid, wid).length;

        card.dataset.defAvailable = String(defAvail);
        card.dataset.damAvailable = String(damAvail);
    }

    function updateCard(card){
        const v = getCardValues(card);
        const wid = getWarehouseId(card);

        updateAvailabilityFromWarehouse(card);

        const defHidden = card.querySelector('.defect-hidden');
        const damHidden = card.querySelector('.damaged-hidden');

        const defIds = defHidden ? selectedIdsFromHidden(defHidden) : [];
        const damIds = damHidden ? selectedIdsFromHidden(damHidden) : [];

        const qtyOk = (v.total === v.expected);
        const whOk = (wid > 0);

        const defOk = (v.defect <= 0) ? true : (defIds.length === v.defect);
        const damOk = (v.damaged <= 0) ? true : (damIds.length === v.damaged);

        const defAvail = toInt(card.dataset.defAvailable);
        const damAvail = toInt(card.dataset.damAvailable);
        const defStockOk = (v.defect <= defAvail);
        const damStockOk = (v.damaged <= damAvail);

        const ok = whOk && qtyOk && defOk && damOk && defStockOk && damStockOk;

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
            else if (!defStockOk) msg = 'DEFECT > stock';
            else if (!damStockOk) msg = 'DAMAGED > stock';
            else if (!defOk) msg = 'Pick DEFECT IDs';
            else if (!damOk) msg = 'Pick DAMAGED IDs';

            statusEl.textContent = ok ? 'OK' : msg;
            statusEl.className = 'badge ' + (ok ? 'badge-success' : 'badge-danger') + ' row-status';
        }

        if (v.defect <= 0 && defHidden && defIds.length > 0) {
            setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
            renderChips(card.querySelector('.defect-chips'), []);
        }
        if (v.damaged <= 0 && damHidden && damIds.length > 0) {
            setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);
            renderChips(card.querySelector('.damaged-chips'), []);
        }

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

    // init
    const cards = document.querySelectorAll('.confirm-card');
    cards.forEach(card => {
        const defHidden = card.querySelector('.defect-hidden');
        const damHidden = card.querySelector('.damaged-hidden');

        setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
        setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);

        renderChips(card.querySelector('.defect-chips'), []);
        renderChips(card.querySelector('.damaged-chips'), []);

        // qty inputs
        ['.qty-good', '.qty-defect', '.qty-damaged'].forEach(sel => {
            const el = card.querySelector(sel);
            if (el) el.addEventListener('input', () => { updateCard(card); refreshGlobalState(); });
        });

        // warehouse change => update availability + clear selections (biar gak nyebrang gudang)
        const whSel = card.querySelector('.item-warehouse');
        if (whSel) {
            whSel.addEventListener('change', () => {
                // clear selected ids when warehouse changes
                setHiddenArray(defHidden, `items[${card.dataset.idx}][selected_defect_ids][]`, []);
                setHiddenArray(damHidden, `items[${card.dataset.idx}][selected_damaged_ids][]`, []);
                renderChips(card.querySelector('.defect-chips'), []);
                renderChips(card.querySelector('.damaged-chips'), []);
                updateCard(card);
                refreshGlobalState();
            });
        }

        // DEFECT modal
        const btnDef = card.querySelector('.btn-pick-defect');
        const defTarget = getTargetSelector(btnDef);
        const defModal = defTarget ? document.querySelector(defTarget) : null;

        if (defModal) {
            onModalShown(defModal, () => {
                const wid = getWarehouseId(card);
                const pid = getProductId(card);
                const v = getCardValues(card);
                const required = v.defect;

                if (wid <= 0) {
                    alert('Pilih warehouse dulu untuk item ini.');
                    if (window.jQuery) window.jQuery(defModal).modal('hide');
                    return;
                }

                const avail = getAvailableDefectList(pid, wid).length;
                const t = defModal.querySelector('.def-available-text');
                if (t) t.textContent = String(avail);

                const body = defModal.querySelector('.pick-defect-body');
                if (body) body.innerHTML = buildDefectListHtml(pid, wid);

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

        // DAMAGED modal
        const btnDam = card.querySelector('.btn-pick-damaged');
        const damTarget = getTargetSelector(btnDam);
        const damModal = damTarget ? document.querySelector(damTarget) : null;

        if (damModal) {
            onModalShown(damModal, () => {
                const wid = getWarehouseId(card);
                const pid = getProductId(card);
                const v = getCardValues(card);
                const required = v.damaged;

                if (wid <= 0) {
                    alert('Pilih warehouse dulu untuk item ini.');
                    if (window.jQuery) window.jQuery(damModal).modal('hide');
                    return;
                }

                const avail = getAvailableDamagedList(pid, wid).length;
                const t = damModal.querySelector('.dam-available-text');
                if (t) t.textContent = String(avail);

                const body = damModal.querySelector('.pick-damaged-body');
                if (body) body.innerHTML = buildDamagedListHtml(pid, wid);

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
