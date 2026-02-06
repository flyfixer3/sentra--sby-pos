@extends('layouts.app')

@section('title', 'Confirm Purchase Delivery')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-deliveries.index') }}">Purchase Deliveries</a></li>
        <li class="breadcrumb-item">
            <a href="{{ route('purchase-deliveries.show', $purchaseDelivery) }}">Details</a>
        </li>
        <li class="breadcrumb-item active">Confirm</li>
    </ol>
@endsection

@section('content')
@php
    $st = strtolower(trim((string)($purchaseDelivery->status ?? 'pending')));
    $details = $purchaseDelivery->purchaseDeliveryDetails ?? collect();

    // totals
    $totalExpected = $details->sum(fn($d) => (int)($d->quantity ?? 0));
    $totalAlreadyConfirmed = $details->sum(fn($d) => (int)($d->qty_received ?? 0) + (int)($d->qty_defect ?? 0) + (int)($d->qty_damaged ?? 0));
    $totalRemaining = max(0, $totalExpected - $totalAlreadyConfirmed);
@endphp

<div class="container-fluid mb-4">

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-1">
                    Confirm Purchase Delivery #{{ $purchaseDelivery->id }}
                    @if($st === 'partial')
                        <span class="badge badge-info text-uppercase ml-2">partial</span>
                    @elseif($st === 'pending')
                        <span class="badge badge-warning text-uppercase ml-2">pending</span>
                    @else
                        <span class="badge badge-secondary text-uppercase ml-2">{{ $st }}</span>
                    @endif
                </h5>

                <div class="text-muted">
                    PO: <strong>{{ $purchaseDelivery->purchaseOrder->reference ?? '-' }}</strong>
                    · WH: <strong>{{ $purchaseDelivery->warehouse->warehouse_name ?? '-' }}</strong>
                </div>

                <div class="text-muted mt-2">
                    <small>
                        Expected: <b>{{ (int)$totalExpected }}</b>
                        · Already Confirmed: <b>{{ (int)$totalAlreadyConfirmed }}</b>
                        · Remaining: <b>{{ (int)$totalRemaining }}</b>
                    </small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fill-remaining">
                    Fill Remaining as Good
                </button>
            </div>
        </div>
    </div>

    @include('utils.alerts')

    {{-- master options for rack select (dipakai JS untuk render select) --}}
    <select id="rack-options-master" class="d-none">
        <option value="">-- Select Rack --</option>
        @foreach(($racks ?? []) as $rack)
            @php
                $name = trim((string) ($rack->name ?? ''));
                $code = trim((string) ($rack->code ?? ''));
                if ($name === '') $name = 'Rack #' . (int) $rack->id;
                $rackLabel = $code !== '' ? ($code . ' - ' . $name) : $name;
            @endphp
            <option value="{{ (int) $rack->id }}">{{ $rackLabel }}</option>
        @endforeach
    </select>

    <form method="POST"
          action="{{ route('purchase-deliveries.confirm.store', $purchaseDelivery) }}"
          id="confirm-form"
          enctype="multipart/form-data">
        @csrf

        <div class="card">
            <div class="card-body p-0">

                <div class="p-3 border-bottom bg-light">
                    <div class="font-weight-bold mb-1">Rule Validasi (Batch Confirm)</div>
                    <div class="text-muted">
                        Kamu mengunci <b>qty tambahan</b> (batch) hari ini:
                        <b>Add Good + Add Defect + Add Damaged</b> boleh <b>kurang dari / sama dengan</b> Remaining,
                        tapi <b>tidak boleh melebihi</b> Remaining.
                    </div>
                    <div class="text-muted mt-2">
                        <small>
                            - <strong>Add Good</strong>: wajib isi tabel Allocation (bisa split rack). Total allocation harus sama dengan Add Good.<br>
                            - <strong>Add Defect</strong>: per unit (qty=1 per baris) + wajib defect type + wajib pilih rack.<br>
                            - <strong>Add Damaged</strong>: per unit (qty=1 per baris) + wajib reason + wajib pilih rack.<br>
                            - Setelah batch ini disubmit, batch <b>tidak bisa diubah</b>.
                        </small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="receive-table">
                        <thead class="thead-light">
                            <tr>
                                <th style="min-width: 320px;">Product</th>
                                <th class="text-center" style="width: 120px;">Expected</th>
                                <th class="text-center" style="width: 150px;">Already Confirmed</th>
                                <th class="text-center" style="width: 120px;">Remaining</th>

                                <th class="text-center" style="width: 110px;">Add Good</th>
                                <th class="text-center" style="width: 110px;">Add Defect</th>
                                <th class="text-center" style="width: 110px;">Add Damaged</th>

                                <th class="text-center" style="width: 140px;">New Remaining</th>
                                <th class="text-center" style="width: 170px;">Details</th>
                                <th class="text-center" style="width: 140px;">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        @foreach ($purchaseDelivery->purchaseDeliveryDetails as $idx => $detail)
                            @php
                                $expected = (int) $detail->quantity;
                                $alreadyConfirmed = (int)($detail->qty_received ?? 0) + (int)($detail->qty_defect ?? 0) + (int)($detail->qty_damaged ?? 0);
                                $remaining = max(0, $expected - $alreadyConfirmed);

                                // default input: kosong (0) untuk batch berikutnya
                                $oldAddGood    = (int) old("items.$idx.add_good", 0);
                                $oldAddDefect  = (int) old("items.$idx.add_defect", 0);
                                $oldAddDamaged = (int) old("items.$idx.add_damaged", 0);

                                $oldGoodAlloc = old("items.$idx.good_allocations", []);
                                $oldDefects   = old("items.$idx.defects", []);
                                $oldDamages   = old("items.$idx.damaged_items", []);
                            @endphp

                            <tr class="receive-row"
                                data-index="{{ $idx }}"
                                data-expected="{{ $expected }}"
                                data-already="{{ $alreadyConfirmed }}"
                                data-remaining="{{ $remaining }}">

                                <td class="align-middle">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div>
                                            <div class="font-weight-bold">{{ $detail->product_name ?? '-' }}</div>
                                            <div class="text-muted"><small>{{ $detail->product_code ?? '' }}</small></div>
                                        </div>
                                        <span class="badge badge-pill badge-light border px-2 py-1">
                                            DID: {{ (int) $detail->id }}
                                        </span>
                                    </div>

                                    <input type="hidden" name="items[{{ $idx }}][detail_id]" value="{{ (int) $detail->id }}">
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ (int) $detail->product_id }}">

                                    <input type="hidden" name="items[{{ $idx }}][expected]" value="{{ (int)$expected }}">
                                    <input type="hidden" name="items[{{ $idx }}][already_confirmed]" value="{{ (int)$alreadyConfirmed }}">
                                    <input type="hidden" name="items[{{ $idx }}][remaining]" value="{{ (int)$remaining }}">
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-primary">{{ $expected }}</span>
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-success">{{ $alreadyConfirmed }}</span>
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-secondary remaining-base-badge">{{ $remaining }}</span>
                                </td>

                                {{-- Add Good --}}
                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input add-good"
                                           name="items[{{ $idx }}][add_good]"
                                           value="{{ $oldAddGood }}"
                                           {{ $remaining <= 0 ? 'readonly' : '' }}
                                           required>
                                </td>

                                {{-- Add Defect --}}
                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input add-defect"
                                           name="items[{{ $idx }}][add_defect]"
                                           value="{{ $oldAddDefect }}"
                                           {{ $remaining <= 0 ? 'readonly' : '' }}
                                           required>
                                </td>

                                {{-- Add Damaged --}}
                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input add-damaged"
                                           name="items[{{ $idx }}][add_damaged]"
                                           value="{{ $oldAddDamaged }}"
                                           {{ $remaining <= 0 ? 'readonly' : '' }}
                                           required>
                                </td>

                                {{-- New Remaining (dynamic) --}}
                                <td class="text-center align-middle">
                                    <span class="badge badge-secondary new-remaining-badge">
                                        {{ max(0, $remaining - ($oldAddGood + $oldAddDefect + $oldAddDamaged)) }}
                                    </span>
                                </td>

                                {{-- Details --}}
                                <td class="text-center align-middle">
                                    <button type="button"
                                            class="btn btn-sm btn-notes"
                                            data-target="#perUnitWrap-{{ $idx }}"
                                            {{ $remaining <= 0 ? 'disabled' : '' }}>
                                        Details
                                        <span class="ml-2 badge badge-pill badge-good badge-good-count">0</span>
                                        <span class="ml-1 badge badge-pill badge-defect badge-defect-count">0</span>
                                        <span class="ml-1 badge badge-pill badge-damaged badge-damaged-count">0</span>
                                    </button>
                                </td>

                                {{-- Status --}}
                                <td class="text-center align-middle">
                                    <span class="badge badge-secondary row-status">CHECK</span>
                                    <div class="small text-muted mt-1 row-hint"></div>
                                </td>
                            </tr>

                            <tr class="perunit-row" id="perUnitWrap-{{ $idx }}" style="display:none;">
                                <td colspan="10" class="p-3" style="background:#f1f5f9;">
                                    <div class="bg-white border rounded p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="font-weight-bold">
                                                Placement & Quality Details (Batch) — {{ $detail->product_name ?? '-' }}
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-close-notes"
                                                    data-target="#perUnitWrap-{{ $idx }}">
                                                Close
                                            </button>
                                        </div>

                                        <div class="text-muted mt-1">
                                            <small>
                                                Ini khusus untuk <b>batch confirm sekarang</b>.<br>
                                                <b>Add Good</b> = isi tabel Allocation (bisa split rack).<br>
                                                <b>Add Defect/Damaged</b> = per unit (qty=1 per baris).
                                            </small>
                                        </div>

                                        <div class="row mt-3">
                                            {{-- GOOD Allocation --}}
                                            <div class="col-lg-12 mb-3">
                                                <div class="font-weight-bold mb-2" style="color:#0f766e;">
                                                    Good Allocation (<span class="good-count-text">0</span>)
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th style="min-width:240px;">Rack *</th>
                                                                <th style="width:140px;" class="text-center">Qty *</th>
                                                                <th>Note (optional)</th>
                                                                <th style="width:120px;" class="text-center">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="good-alloc-tbody"></tbody>
                                                    </table>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div class="text-muted">
                                                        <small>Kalau Add Good=10 dan semua masuk 1 rack, cukup 1 baris Qty=10.</small>
                                                    </div>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-success btn-add-good-alloc"
                                                            data-idx="{{ $idx }}">
                                                        + Add Allocation
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- DEFECT --}}
                                            <div class="col-lg-6">
                                                <div class="font-weight-bold mb-2" style="color:#1d4ed8;">
                                                    Defect Items (<span class="defect-count-text">0</span>)
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th style="min-width:200px;">Rack *</th>
                                                                <th style="min-width:160px;">Defect Type *</th>
                                                                <th>Defect Description</th>
                                                                <th style="width:190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="defect-tbody"></tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            {{-- DAMAGED --}}
                                            <div class="col-lg-6 mt-3 mt-lg-0">
                                                <div class="font-weight-bold mb-2" style="color:#b91c1c;">
                                                    Damaged Items (<span class="damaged-count-text">0</span>)
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th style="min-width:200px;">Rack *</th>
                                                                <th>Damaged Reason *</th>
                                                                <th style="width:190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="damaged-tbody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- OLD VALUES for hydrate (validation fail) --}}
                                        <textarea class="d-none old-good-alloc-json" data-idx="{{ $idx }}">{{ json_encode($oldGoodAlloc) }}</textarea>
                                        <textarea class="d-none old-defects-json" data-idx="{{ $idx }}">{{ json_encode($oldDefects) }}</textarea>
                                        <textarea class="d-none old-damages-json" data-idx="{{ $idx }}">{{ json_encode($oldDamages) }}</textarea>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top">
                    <label class="font-weight-bold mb-1">Confirmation Note (General)</label>
                    <textarea class="form-control"
                              name="confirm_note"
                              rows="3"
                              maxlength="1000"
                              placeholder="contoh: supplier kirim partial dulu, sisanya menyusul besok">{{ old('confirm_note') }}</textarea>
                    <div class="text-muted mt-2">
                        <small>Catatan ini akan overwrite confirm_note terakhir (sesuai controller kamu).</small>
                    </div>
                </div>

            </div>

            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Tip: kalau batch hari ini nggak ada defect/damaged, isi Add Defect=0, Add Damaged=0, Add Good=Remaining.
                </div>

                <div>
                    <a href="{{ route('purchase-deliveries.show', $purchaseDelivery) }}" class="btn btn-light">
                        Cancel
                    </a>
                    <button type="button" class="btn btn-primary" onclick="confirmSubmit()">
                        Confirm & Lock This Batch
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection

@push('page_css')
<style>
    .btn-notes{
        background:#fff;
        border:1px solid #dbeafe;
        color:#1d4ed8;
        border-radius:999px;
        padding:6px 12px;
        font-weight:600;
    }
    .btn-notes:hover{ background:#eff6ff; border-color:#93c5fd; }

    .badge-good{ background:#0f766e; color:#fff; font-weight:700; padding:5px 8px; }
    .badge-defect{ background:#2563eb; color:#fff; font-weight:700; padding:5px 8px; }
    .badge-damaged{ background:#ef4444; color:#fff; font-weight:700; padding:5px 8px; }

    .photo-input{
        border:1px dashed #cbd5e1;
        padding:6px;
        border-radius:10px;
        background:#fff;
        width:100%;
        font-size:12px;
    }

    .alloc-actions .btn{
        padding:4px 8px;
        font-size:12px;
        border-radius:8px;
        font-weight:800;
    }
</style>
@endpush

@push('page_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function asInt(val){ const n = parseInt(val,10); return isNaN(n)?0:n; }
    function safeJsonParse(text){ try{ if(!text) return []; return JSON.parse(text);}catch(e){ return []; } }

    function rackOptionsHtml(){
        const master = document.getElementById('rack-options-master');
        return master ? master.innerHTML : '<option value="">-- Select Rack --</option>';
    }

    function togglePerUnit(sel, show){
        const el = document.querySelector(sel);
        if (!el) return;
        el.style.display = show ? '' : 'none';
    }

    function captureGoodAlloc(perWrap){
        const out = [];
        perWrap.querySelectorAll('.good-alloc-tbody tr').forEach(tr=>{
            out.push({
                rack_id: tr.querySelector('.good-alloc-rack')?.value || '',
                qty: tr.querySelector('.good-alloc-qty')?.value || '0',
                note: tr.querySelector('.good-alloc-note')?.value || ''
            });
        });
        perWrap.dataset.oldGoodAlloc = JSON.stringify(out);
    }

    function capturePerUnit(perWrap){
        const out = { defect: [], damaged: [] };

        captureGoodAlloc(perWrap);

        perWrap.querySelectorAll('.defect-tbody tr').forEach(tr=>{
            out.defect.push({
                rack_id: tr.querySelector('.defect-rack')?.value || '',
                defect_type: tr.querySelector('.defect-type-input')?.value || '',
                defect_description: tr.querySelector('.defect-desc-input')?.value || ''
            });
        });

        perWrap.querySelectorAll('.damaged-tbody tr').forEach(tr=>{
            out.damaged.push({
                rack_id: tr.querySelector('.damaged-rack')?.value || '',
                damaged_reason: tr.querySelector('.damaged-reason-input')?.value || ''
            });
        });

        perWrap.dataset.oldDefects = JSON.stringify(out.defect);
        perWrap.dataset.oldDamages = JSON.stringify(out.damaged);
    }

    function buildGoodAllocRow(idx, rowIndex, prev){
        const rackId = (prev && (prev.rack_id || prev.rackId)) ? String(prev.rack_id || prev.rackId) : '';
        const qty = (prev && prev.qty) ? String(prev.qty) : '1';
        const note = (prev && prev.note) ? String(prev.note) : '';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center align-middle">${rowIndex + 1}</td>
            <td class="align-middle">
                <select class="form-control form-control-sm good-alloc-rack"
                        name="items[${idx}][good_allocations][${rowIndex}][rack_id]" required>
                    ${rackOptionsHtml()}
                </select>
            </td>
            <td class="align-middle">
                <input type="number"
                       min="1"
                       step="1"
                       class="form-control form-control-sm text-center good-alloc-qty"
                       name="items[${idx}][good_allocations][${rowIndex}][qty]"
                       value="${qty}"
                       required>
            </td>
            <td class="align-middle">
                <input type="text"
                       class="form-control form-control-sm good-alloc-note"
                       name="items[${idx}][good_allocations][${rowIndex}][note]"
                       value="${note.replace(/"/g,'&quot;')}"
                       placeholder="opsional">
            </td>
            <td class="text-center align-middle alloc-actions">
                <button type="button" class="btn btn-outline-danger btn-remove-alloc">Remove</button>
            </td>
        `;

        const sel = tr.querySelector('.good-alloc-rack');
        if (sel && rackId) sel.value = rackId;

        return tr;
    }

    function reindexGoodAlloc(perWrap, idx){
        const tbody = perWrap.querySelector('.good-alloc-tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach((tr, i)=>{
            tr.querySelector('td:first-child').textContent = (i + 1);
            tr.querySelector('.good-alloc-rack').name = `items[${idx}][good_allocations][${i}][rack_id]`;
            tr.querySelector('.good-alloc-qty').name  = `items[${idx}][good_allocations][${i}][qty]`;
            tr.querySelector('.good-alloc-note').name = `items[${idx}][good_allocations][${i}][note]`;
        });
    }

    function ensurePerUnitTablesBuilt(row){
        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);
        if (!perWrap) return;

        const addGood = asInt(row.querySelector('.add-good').value);
        const addDefect = asInt(row.querySelector('.add-defect').value);
        const addDamaged = asInt(row.querySelector('.add-damaged').value);

        capturePerUnit(perWrap);

        if (!perWrap.dataset.hydrated){
            const oldGoodAllocText = (perWrap.querySelector('.old-good-alloc-json')?.value || perWrap.querySelector('.old-good-alloc-json')?.textContent || '');
            const oldDefText  = (perWrap.querySelector('.old-defects-json')?.value || perWrap.querySelector('.old-defects-json')?.textContent || '');
            const oldDamText  = (perWrap.querySelector('.old-damages-json')?.value || perWrap.querySelector('.old-damages-json')?.textContent || '');

            perWrap.dataset.oldGoodAlloc = JSON.stringify(safeJsonParse(oldGoodAllocText) || []);
            perWrap.dataset.oldDefects = JSON.stringify(safeJsonParse(oldDefText) || []);
            perWrap.dataset.oldDamages = JSON.stringify(safeJsonParse(oldDamText) || []);
            perWrap.dataset.hydrated = '1';
        }

        const oldGoodAlloc = safeJsonParse(perWrap.dataset.oldGoodAlloc || '[]');
        const oldDefects = safeJsonParse(perWrap.dataset.oldDefects || '[]');
        const oldDamages = safeJsonParse(perWrap.dataset.oldDamages || '[]');

        // ===== GOOD allocation build =====
        const goodAllocTbody = perWrap.querySelector('.good-alloc-tbody');
        if (goodAllocTbody){
            goodAllocTbody.innerHTML = '';

            if (oldGoodAlloc.length > 0){
                oldGoodAlloc.forEach((prev, i)=>{
                    goodAllocTbody.appendChild(buildGoodAllocRow(idx, i, prev));
                });
            } else {
                if (addGood > 0){
                    goodAllocTbody.appendChild(buildGoodAllocRow(idx, 0, { qty: String(addGood) }));
                }
            }

            reindexGoodAlloc(perWrap, idx);

            goodAllocTbody.querySelectorAll('.btn-remove-alloc').forEach(btn=>{
                btn.onclick = () => {
                    btn.closest('tr')?.remove();
                    reindexGoodAlloc(perWrap, idx);
                    captureGoodAlloc(perWrap);
                    updateRowStatus(row);
                };
            });

            perWrap.querySelectorAll('.good-alloc-rack, .good-alloc-qty, .good-alloc-note').forEach(el=>{
                el.oninput = () => { captureGoodAlloc(perWrap); updateRowStatus(row); };
                el.onchange = () => { captureGoodAlloc(perWrap); updateRowStatus(row); };
            });
        }

        // ===== DEFECT build =====
        const defectTbody = perWrap.querySelector('.defect-tbody');
        defectTbody.innerHTML = '';
        for (let i=0;i<addDefect;i++){
            const prev = oldDefects[i] || {};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center align-middle">${i+1}</td>
                <td class="align-middle">
                    <select class="form-control form-control-sm defect-rack"
                            name="items[${idx}][defects][${i}][rack_id]" required>
                        ${rackOptionsHtml()}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="text"
                           class="form-control form-control-sm defect-type-input"
                           name="items[${idx}][defects][${i}][defect_type]"
                           value="${(prev.defect_type || '').replace(/"/g,'&quot;')}"
                           placeholder="bubble / baret / distorsi"
                           required>
                </td>
                <td class="align-middle">
                    <textarea class="form-control form-control-sm defect-desc-input"
                              name="items[${idx}][defects][${i}][defect_description]"
                              rows="2"
                              placeholder="opsional">${(prev.defect_description || '')}</textarea>
                </td>
                <td class="align-middle">
                    <input type="file"
                           accept="image/*"
                           class="photo-input"
                           name="items[${idx}][defects][${i}][photo]">
                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                </td>
            `;
            defectTbody.appendChild(tr);

            const sel = tr.querySelector('.defect-rack');
            if (sel && (prev.rack_id || prev.rackId)){
                sel.value = String(prev.rack_id || prev.rackId);
            }
        }

        // ===== DAMAGED build =====
        const damagedTbody = perWrap.querySelector('.damaged-tbody');
        damagedTbody.innerHTML = '';
        for (let i=0;i<addDamaged;i++){
            const prev = oldDamages[i] || {};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center align-middle">${i+1}</td>
                <td class="align-middle">
                    <select class="form-control form-control-sm damaged-rack"
                            name="items[${idx}][damaged_items][${i}][rack_id]" required>
                        ${rackOptionsHtml()}
                    </select>
                </td>
                <td class="align-middle">
                    <textarea class="form-control form-control-sm damaged-reason-input"
                              name="items[${idx}][damaged_items][${i}][damaged_reason]"
                              rows="2"
                              placeholder="contoh: pecah saat bongkar peti"
                              required>${(prev.damaged_reason || '')}</textarea>
                </td>
                <td class="align-middle">
                    <input type="file"
                           accept="image/*"
                           class="photo-input"
                           name="items[${idx}][damaged_items][${i}][photo]">
                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                </td>
            `;
            damagedTbody.appendChild(tr);

            const sel = tr.querySelector('.damaged-rack');
            if (sel && (prev.rack_id || prev.rackId)){
                sel.value = String(prev.rack_id || prev.rackId);
            }
        }

        perWrap.querySelector('.good-count-text').textContent = addGood;
        perWrap.querySelector('.defect-count-text').textContent = addDefect;
        perWrap.querySelector('.damaged-count-text').textContent = addDamaged;

        const btn = row.querySelector('.btn-notes');
        if (btn){
            btn.querySelector('.badge-good-count').textContent = addGood;
            btn.querySelector('.badge-defect-count').textContent = addDefect;
            btn.querySelector('.badge-damaged-count').textContent = addDamaged;
        }

        perWrap.querySelectorAll('.defect-tbody input, .defect-tbody textarea, .defect-tbody select, .damaged-tbody input, .damaged-tbody textarea, .damaged-tbody select')
            .forEach(el=>{
                el.addEventListener('input', ()=> updateRowStatus(row));
                el.addEventListener('change', ()=> updateRowStatus(row));
            });

        perWrap.querySelectorAll('.btn-add-good-alloc').forEach(btnAdd=>{
            btnAdd.onclick = () => {
                const tbody = perWrap.querySelector('.good-alloc-tbody');
                const cur = tbody.querySelectorAll('tr').length;
                tbody.appendChild(buildGoodAllocRow(idx, cur, { qty: '1' }));
                reindexGoodAlloc(perWrap, idx);

                tbody.querySelectorAll('.btn-remove-alloc').forEach(btn=>{
                    btn.onclick = () => {
                        btn.closest('tr')?.remove();
                        reindexGoodAlloc(perWrap, idx);
                        captureGoodAlloc(perWrap);
                        updateRowStatus(row);
                    };
                });

                perWrap.querySelectorAll('.good-alloc-rack, .good-alloc-qty, .good-alloc-note').forEach(el=>{
                    el.oninput = () => { captureGoodAlloc(perWrap); updateRowStatus(row); };
                    el.onchange = () => { captureGoodAlloc(perWrap); updateRowStatus(row); };
                });

                captureGoodAlloc(perWrap);
                updateRowStatus(row);
            };
        });

        capturePerUnit(perWrap);
    }

    function sumGoodAlloc(perWrap){
        let sum = 0;
        perWrap.querySelectorAll('.good-alloc-qty').forEach(inp=> sum += asInt(inp.value));
        return sum;
    }

    // ✅ dynamic New Remaining + validation based on base remaining
    function updateRowStatus(row){
        const remainingBase = asInt(row.dataset.remaining);

        const addGood = asInt(row.querySelector('.add-good').value);
        const addDefect = asInt(row.querySelector('.add-defect').value);
        const addDamaged = asInt(row.querySelector('.add-damaged').value);

        const statusBadge = row.querySelector('.row-status');
        const hint = row.querySelector('.row-hint');

        const newRemBadge = row.querySelector('.new-remaining-badge');
        const batchTotal = addGood + addDefect + addDamaged;
        const newRemaining = remainingBase - batchTotal;

        if (newRemBadge){
            newRemBadge.textContent = newRemaining >= 0 ? newRemaining : 'INVALID';
            newRemBadge.className = newRemaining >= 0 ? 'badge badge-secondary new-remaining-badge' : 'badge badge-danger new-remaining-badge';
        }

        if (addGood < 0 || addDefect < 0 || addDamaged < 0){
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Qty tidak boleh negatif.';
            return false;
        }

        if (batchTotal > remainingBase){
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'OVER';
            hint.textContent = `Batch total (${batchTotal}) tidak boleh > Remaining (${remainingBase}).`;
            return false;
        }

        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);
        if (!perWrap){
            statusBadge.className = 'badge badge-warning row-status';
            statusBadge.textContent = 'NEED INFO';
            hint.textContent = 'Detail panel tidak ditemukan.';
            return false;
        }

        // GOOD allocation validation
        if (addGood > 0){
            const sumAlloc = sumGoodAlloc(perWrap);
            if (sumAlloc !== addGood){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED ALLOC';
                hint.textContent = `Add Good = ${addGood}, total allocation = ${sumAlloc} (harus sama).`;
                return false;
            }

            const allocRows = perWrap.querySelectorAll('.good-alloc-tbody tr');
            if (allocRows.length === 0){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED ALLOC';
                hint.textContent = 'Good allocation wajib diisi minimal 1 baris.';
                return false;
            }

            for (let i=0;i<allocRows.length;i++){
                const r = allocRows[i];
                const rackSel = r.querySelector('.good-alloc-rack');
                const qtyInp  = r.querySelector('.good-alloc-qty');
                if (!rackSel || !(rackSel.value || '').trim()){
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED RACK';
                    hint.textContent = 'Good allocation: setiap baris wajib pilih Rack.';
                    return false;
                }
                if (!qtyInp || asInt(qtyInp.value) <= 0){
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED QTY';
                    hint.textContent = 'Good allocation: qty harus > 0.';
                    return false;
                }
            }
        } else {
            // kalau addGood=0, kosongkan allocation (biar nggak misleading)
            // (nggak wajib, tapi lebih rapih)
        }

        // DEFECT per-unit validation
        const defectRows = perWrap.querySelectorAll('.defect-tbody tr');
        if (defectRows.length !== addDefect){
            statusBadge.className = 'badge badge-warning row-status';
            statusBadge.textContent = 'NEED INFO';
            hint.textContent = `Add Defect = ${addDefect}, detail defect belum lengkap.`;
            return false;
        }

        for (let i=0;i<defectRows.length;i++){
            const rackSel = defectRows[i].querySelector('select.defect-rack');
            const typeInput = defectRows[i].querySelector('input.defect-type-input');

            if (!rackSel || !(rackSel.value || '').trim()){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED RACK';
                hint.textContent = 'Defect: setiap unit wajib pilih Rack.';
                return false;
            }
            if (!typeInput || !typeInput.value.trim()){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = 'Defect Type wajib diisi untuk setiap defect item.';
                return false;
            }
        }

        // DAMAGED per-unit validation
        const damagedRows = perWrap.querySelectorAll('.damaged-tbody tr');
        if (damagedRows.length !== addDamaged){
            statusBadge.className = 'badge badge-warning row-status';
            statusBadge.textContent = 'NEED INFO';
            hint.textContent = `Add Damaged = ${addDamaged}, detail damaged belum lengkap.`;
            return false;
        }

        for (let i=0;i<damagedRows.length;i++){
            const rackSel = damagedRows[i].querySelector('select.damaged-rack');
            const reasonInput = damagedRows[i].querySelector('textarea.damaged-reason-input');

            if (!rackSel || !(rackSel.value || '').trim()){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED RACK';
                hint.textContent = 'Damaged: setiap unit wajib pilih Rack.';
                return false;
            }
            if (!reasonInput || !reasonInput.value.trim()){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = 'Damaged Reason wajib diisi untuk setiap damaged item.';
                return false;
            }
        }

        if (batchTotal === 0){
            statusBadge.className = 'badge badge-secondary row-status';
            statusBadge.textContent = 'EMPTY';
            hint.textContent = 'Tidak ada qty batch yang dikunci untuk item ini.';
            return true;
        }

        if (newRemaining === 0){
            statusBadge.className = 'badge badge-success row-status';
            statusBadge.textContent = 'OK';
            hint.textContent = `Batch total = ${batchTotal} (remaining habis)`;
        } else {
            statusBadge.className = 'badge badge-info row-status';
            statusBadge.textContent = 'PARTIAL';
            hint.textContent = `Batch total = ${batchTotal} (sisa ${newRemaining})`;
        }

        return true;
    }

    function initRow(row){
        ensurePerUnitTablesBuilt(row);
        updateRowStatus(row);

        row.querySelectorAll('.qty-input').forEach(el=>{
            el.addEventListener('input', ()=>{
                ensurePerUnitTablesBuilt(row);
                updateRowStatus(row);
            });
        });

        const btn = row.querySelector('.btn-notes');
        if (btn){
            btn.addEventListener('click', ()=>{
                const target = btn.getAttribute('data-target');
                ensurePerUnitTablesBuilt(row);
                togglePerUnit(target, true);
            });
        }
    }

    function validateAllRows(){
        let ok = true;
        document.querySelectorAll('.receive-row').forEach(row=>{
            ensurePerUnitTablesBuilt(row);
            const rowOk = updateRowStatus(row);
            if (!rowOk) ok = false;
        });
        return ok;
    }

    function hasAnyBatchQty(){
        let any = false;
        document.querySelectorAll('.receive-row').forEach(row=>{
            const addGood = asInt(row.querySelector('.add-good').value);
            const addDefect = asInt(row.querySelector('.add-defect').value);
            const addDamaged = asInt(row.querySelector('.add-damaged').value);
            if ((addGood + addDefect + addDamaged) > 0) any = true;
        });
        return any;
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.receive-row').forEach(row => initRow(row));

        document.querySelectorAll('.btn-close-notes').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const target = btn.getAttribute('data-target');
                togglePerUnit(target, false);
            });
        });

        // Fill Remaining as Good
        document.getElementById('btn-fill-remaining')?.addEventListener('click', () => {
            document.querySelectorAll('.receive-row').forEach(row => {
                const rem = asInt(row.dataset.remaining);
                row.querySelector('.add-good').value = rem;
                row.querySelector('.add-defect').value = 0;
                row.querySelector('.add-damaged').value = 0;

                ensurePerUnitTablesBuilt(row);
                updateRowStatus(row);
            });
        });
    });

    function confirmSubmit(){
        const isValid = validateAllRows();
        if (!isValid){
            Swal.fire({
                title: 'Ada data yang belum valid',
                text: 'Periksa qty batch dan pastikan allocation/defect/damaged detail sesuai.',
                icon: 'error',
            });
            return;
        }

        if (!hasAnyBatchQty()){
            Swal.fire({
                title: 'Batch kosong',
                text: 'Isi minimal 1 qty (Add Good/Defect/Damaged) untuk dikunci.',
                icon: 'warning',
            });
            return;
        }

        Swal.fire({
            title: 'Lock batch confirm ini?',
            text: "Setelah dikunci, qty batch ini tidak dapat diubah. Jika masih ada remaining, kamu bisa confirm batch berikutnya nanti.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, lock batch',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('confirm-form').submit();
            }
        });
    }
</script>
@endpush
