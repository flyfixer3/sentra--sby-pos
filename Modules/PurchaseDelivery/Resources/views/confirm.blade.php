@extends('layouts.app')

@section('title', 'Confirm Purchase Delivery')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-deliveries.index') }}">Purchase Deliveries</a></li>
        <li class="breadcrumb-item active">Confirm</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid mb-4">

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-1">Confirm Purchase Delivery #{{ $purchaseDelivery->id }}</h5>
                <div class="text-muted">
                    PO: <strong>{{ $purchaseDelivery->purchaseOrder->reference ?? '-' }}</strong>
                    · Status: <span class="badge badge-warning">{{ $purchaseDelivery->status }}</span>
                    · WH: <strong>{{ $purchaseDelivery->warehouse->warehouse_name ?? '-' }}</strong>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fill-all">
                Fill All as Received
            </button>
        </div>
    </div>

    @include('utils.alerts')

    <form method="POST"
          action="{{ route('purchase-deliveries.confirm.store', $purchaseDelivery) }}"
          id="confirm-form"
          enctype="multipart/form-data">
        @csrf

        <div class="card">
            <div class="card-body p-0">

                <div class="p-3 border-bottom bg-light">
                    <div class="font-weight-bold mb-1">Rule Validasi</div>
                    <div class="text-muted">
                        Untuk Purchase Delivery: <strong>Received + Defect + Damaged</strong> boleh <strong>kurang dari</strong> Expected (partial),
                        tapi <strong>tidak boleh melebihi</strong> Expected.
                    </div>
                    <div class="text-muted mt-2">
                        <small>
                            - <strong>Defect</strong>: tetap masuk stok (mutasi IN), tapi dicatat ke defect table (per unit).<br>
                            - <strong>Damaged</strong>: tetap dicatat ke damaged table (per unit). (Mutasi bisa kamu atur belakangan, default: tetap masuk stok seperti barang masuk, lalu status/penanganan di modul defect/damaged).<br>
                            - Foto opsional, tapi disarankan.
                        </small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="receive-table">
                        <thead class="thead-light">
                            <tr>
                                <th style="min-width: 320px;">Product</th>
                                <th class="text-center" style="width: 90px;">Expected</th>
                                <th class="text-center" style="width: 110px;">Received</th>
                                <th class="text-center" style="width: 110px;">Defect</th>
                                <th class="text-center" style="width: 110px;">Damaged</th>
                                <th class="text-center" style="width: 110px;">Remaining</th>
                                <th class="text-center" style="width: 170px;">Per-Unit Notes</th>
                                <th class="text-center" style="width: 140px;">Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        @foreach ($purchaseDelivery->purchaseDeliveryDetails as $idx => $detail)
                            @php
                                $expected = (int) $detail->quantity;

                                // default: terima semua
                                $oldReceived = (int) old("items.$idx.qty_received", $expected);
                                $oldDefect   = (int) old("items.$idx.qty_defect", 0);
                                $oldDamaged  = (int) old("items.$idx.qty_damaged", 0);

                                $oldDefects  = old("items.$idx.defects", []);
                                $oldDamages  = old("items.$idx.damaged_items", []);
                            @endphp

                            <tr class="receive-row"
                                data-index="{{ $idx }}"
                                data-expected="{{ $expected }}">
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
                                    <input type="hidden" name="items[{{ $idx }}][expected]" value="{{ $expected }}">
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-primary">{{ $expected }}</span>
                                </td>

                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input qty-received"
                                           name="items[{{ $idx }}][qty_received]"
                                           value="{{ $oldReceived }}"
                                           required>
                                </td>

                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input qty-defect"
                                           name="items[{{ $idx }}][qty_defect]"
                                           value="{{ $oldDefect }}"
                                           required>
                                </td>

                                <td class="text-center align-middle">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           class="form-control form-control-sm text-center qty-input qty-damaged"
                                           name="items[{{ $idx }}][qty_damaged]"
                                           value="{{ $oldDamaged }}"
                                           required>
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-secondary remaining-badge">{{ max(0, $expected - ($oldReceived + $oldDefect + $oldDamaged)) }}</span>
                                </td>

                                <td class="text-center align-middle">
                                    <button type="button"
                                            class="btn btn-sm btn-notes"
                                            data-target="#perUnitWrap-{{ $idx }}">
                                        Notes
                                        <span class="ml-2 badge badge-pill badge-defect badge-defect-count">0</span>
                                        <span class="ml-1 badge badge-pill badge-damaged badge-damaged-count">0</span>
                                    </button>
                                </td>

                                <td class="text-center align-middle">
                                    <span class="badge badge-secondary row-status">CHECK</span>
                                    <div class="small text-muted mt-1 row-hint"></div>
                                </td>
                            </tr>

                            <tr class="perunit-row" id="perUnitWrap-{{ $idx }}" style="display:none;">
                                <td colspan="8" class="p-3" style="background:#f1f5f9;">
                                    <div class="bg-white border rounded p-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="font-weight-bold">
                                                Per-Unit Notes — {{ $detail->product_name ?? '-' }}
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-close-notes"
                                                    data-target="#perUnitWrap-{{ $idx }}">
                                                Close
                                            </button>
                                        </div>

                                        <div class="text-muted mt-1">
                                            <small>Defect/Damaged disimpan <b>per unit</b> (tiap baris qty = 1), jadi tiap unit bisa punya catatan + foto sendiri.</small>
                                        </div>

                                        <div class="row mt-3">
                                            <div class="col-lg-6">
                                                <div class="font-weight-bold mb-2" style="color:#1d4ed8;">
                                                    Defect Items (<span class="defect-count-text">0</span>)
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th style="min-width:160px;">Defect Type *</th>
                                                                <th>Defect Description</th>
                                                                <th style="width:190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="defect-tbody"></tbody>
                                                    </table>
                                                </div>
                                                <div class="text-muted mt-2">
                                                    <small>Contoh: bubble, baret, distorsi, retak ringan.</small>
                                                </div>
                                            </div>

                                            <div class="col-lg-6 mt-3 mt-lg-0">
                                                <div class="font-weight-bold mb-2" style="color:#b91c1c;">
                                                    Damaged Items (<span class="damaged-count-text">0</span>)
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width:55px" class="text-center">#</th>
                                                                <th>Damaged Reason *</th>
                                                                <th style="width:190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="damaged-tbody"></tbody>
                                                    </table>
                                                </div>
                                                <div class="text-muted mt-2">
                                                    <small>Contoh: pecah sudut saat bongkar peti, kena paku peti.</small>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- OLD VALUES for hydrate --}}
                                        <textarea class="d-none old-defects-json" data-idx="{{ $idx }}">{{ json_encode($oldDefects) }}</textarea>
                                        <textarea class="d-none old-damages-json" data-idx="{{ $idx }}">{{ json_encode($oldDamages) }}</textarea>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Tip: Kalau tidak ada defect/damaged, biarkan Defect=0, Damaged=0, dan Received=Expected.
                </div>

                <div>
                    <a href="{{ route('purchase-deliveries.show', $purchaseDelivery) }}" class="btn btn-light">
                        Cancel
                    </a>
                    <button type="button" class="btn btn-primary" onclick="confirmSubmit()">
                        Confirm Delivery
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
</style>
@endpush

@push('page_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function asInt(val){ const n = parseInt(val,10); return isNaN(n)?0:n; }
    function safeJsonParse(text){ try{ if(!text) return []; return JSON.parse(text);}catch(e){ return []; } }

    function updateRowStatus(row){
        const expected = asInt(row.dataset.expected);

        const received = asInt(row.querySelector('.qty-received').value);
        const defect   = asInt(row.querySelector('.qty-defect').value);
        const damaged  = asInt(row.querySelector('.qty-damaged').value);

        const statusBadge = row.querySelector('.row-status');
        const hint = row.querySelector('.row-hint');
        const remainingBadge = row.querySelector('.remaining-badge');

        if (received < 0 || defect < 0 || damaged < 0){
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Qty tidak boleh negatif.';
            return false;
        }

        const total = received + defect + damaged;
        const remaining = expected - total;

        if (remainingBadge){
            remainingBadge.textContent = remaining >= 0 ? remaining : 'INVALID';
            remainingBadge.className = remaining >= 0 ? 'badge badge-secondary remaining-badge' : 'badge badge-danger remaining-badge';
        }

        if (total > expected){
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'OVER';
            hint.textContent = `Total (${total}) tidak boleh > Expected (${expected}).`;
            return false;
        }

        // kalau defect/damaged > 0, wajib ada detail rows sesuai count
        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);

        if (defect > 0){
            const defectRows = perWrap ? perWrap.querySelectorAll('.defect-tbody tr') : [];
            if (!perWrap || defectRows.length !== defect){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = `Defect = ${defect}, detail defect belum lengkap.`;
                return false;
            }
            for (let i=0;i<defectRows.length;i++){
                const typeInput = defectRows[i].querySelector('input.defect-type-input');
                if (!typeInput || !typeInput.value.trim()){
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Defect Type wajib diisi untuk setiap defect item.';
                    return false;
                }
            }
        }

        if (damaged > 0){
            const damagedRows = perWrap ? perWrap.querySelectorAll('.damaged-tbody tr') : [];
            if (!perWrap || damagedRows.length !== damaged){
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = `Damaged = ${damaged}, detail damaged belum lengkap.`;
                return false;
            }
            for (let i=0;i<damagedRows.length;i++){
                const reasonInput = damagedRows[i].querySelector('textarea.damaged-reason-input');
                if (!reasonInput || !reasonInput.value.trim()){
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Damaged Reason wajib diisi untuk setiap damaged item.';
                    return false;
                }
            }
        }

        // OK / PARTIAL
        if (total === expected){
            statusBadge.className = 'badge badge-success row-status';
            statusBadge.textContent = 'OK';
            hint.textContent = `Total = ${total}`;
        } else {
            statusBadge.className = 'badge badge-info row-status';
            statusBadge.textContent = 'PARTIAL';
            hint.textContent = `Total = ${total} (remaining ${remaining})`;
        }

        return true;
    }

    function ensurePerUnitTablesBuilt(row){
        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);
        if (!perWrap) return;

        const defectCount = asInt(row.querySelector('.qty-defect').value);
        const damagedCount = asInt(row.querySelector('.qty-damaged').value);

        // ✅ capture current inputs sebelum rebuild
        const existingDef = [];
        perWrap.querySelectorAll('.defect-tbody tr').forEach(tr=>{
        existingDef.push({
            defect_type: tr.querySelector('.defect-type-input')?.value || '',
            defect_description: tr.querySelector('.defect-desc-input')?.value || ''
        });
        });

        const existingDam = [];
        perWrap.querySelectorAll('.damaged-tbody tr').forEach(tr=>{
        existingDam.push({
            damaged_reason: tr.querySelector('.damaged-reason-input')?.value || ''
        });
        });

        // merge ke old cache (pakai yang latest)
        if (existingDef.length) perWrap.dataset.oldDefects = JSON.stringify(existingDef);
        if (existingDam.length) perWrap.dataset.oldDamages = JSON.stringify(existingDam);

        const defectTbody = perWrap.querySelector('.defect-tbody');
        const damagedTbody = perWrap.querySelector('.damaged-tbody');

        if (!perWrap.dataset.hydrated){
            const oldDefText = (perWrap.querySelector('.old-defects-json')?.value || perWrap.querySelector('.old-defects-json')?.textContent || '');
            const oldDamText = (perWrap.querySelector('.old-damages-json')?.value || perWrap.querySelector('.old-damages-json')?.textContent || '');

            perWrap.dataset.oldDefects = JSON.stringify(safeJsonParse(oldDefText) || []);
            perWrap.dataset.oldDamages = JSON.stringify(safeJsonParse(oldDamText) || []);
            perWrap.dataset.hydrated = '1';
        }

        const oldDefects = safeJsonParse(perWrap.dataset.oldDefects || '[]');
        const oldDamages = safeJsonParse(perWrap.dataset.oldDamages || '[]');

        defectTbody.innerHTML = '';
        for (let i=0;i<defectCount;i++){
            const prev = oldDefects[i] || {};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center align-middle">${i+1}</td>
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
                              placeholder="keterangan defect (opsional)">${(prev.defect_description || '')}</textarea>
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
        }

        damagedTbody.innerHTML = '';
        for (let i=0;i<damagedCount;i++){
            const prev = oldDamages[i] || {};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center align-middle">${i+1}</td>
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
        }

        perWrap.querySelector('.defect-count-text').textContent = defectCount;
        perWrap.querySelector('.damaged-count-text').textContent = damagedCount;

        const btn = row.querySelector('.btn-notes');
        if (btn){
            btn.querySelector('.badge-defect-count').textContent = defectCount;
            btn.querySelector('.badge-damaged-count').textContent = damagedCount;
        }

        // update cache old values (biar pas qty naik turun gak hilang total)
        const currentDef = [];
        perWrap.querySelectorAll('.defect-tbody tr').forEach(tr=>{
            currentDef.push({
                defect_type: tr.querySelector('.defect-type-input')?.value || '',
                defect_description: tr.querySelector('.defect-desc-input')?.value || ''
            });
        });
        const currentDam = [];
        perWrap.querySelectorAll('.damaged-tbody tr').forEach(tr=>{
            currentDam.push({ damaged_reason: tr.querySelector('.damaged-reason-input')?.value || '' });
        });
        perWrap.dataset.oldDefects = JSON.stringify(currentDef);
        perWrap.dataset.oldDamages = JSON.stringify(currentDam);

        perWrap.querySelectorAll('input, textarea').forEach(el=>{
            el.addEventListener('input', ()=> updateRowStatus(row));
        });
    }

    function togglePerUnit(sel, show){
        const el = document.querySelector(sel);
        if (!el) return;
        el.style.display = show ? '' : 'none';
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
            const rowOk = updateRowStatus(row);
            if (!rowOk) ok = false;
        });
        return ok;
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.receive-row').forEach(row => initRow(row));

        document.querySelectorAll('.btn-close-notes').forEach(btn=>{
            btn.addEventListener('click', ()=>{
                const target = btn.getAttribute('data-target');
                togglePerUnit(target, false);
            });
        });

        // Fill all expected as received
        document.getElementById('btn-fill-all').addEventListener('click', () => {
            document.querySelectorAll('.receive-row').forEach(row => {
                const expected = asInt(row.dataset.expected);
                row.querySelector('.qty-received').value = expected;
                row.querySelector('.qty-defect').value = 0;
                row.querySelector('.qty-damaged').value = 0;
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
                text: 'Periksa input qty dan per-unit notes. Pastikan total tidak melebihi expected dan detail defect/damaged lengkap.',
                icon: 'error',
            });
            return;
        }

        Swal.fire({
            title: 'Yakin ingin konfirmasi delivery ini?',
            text: "Setelah dikonfirmasi, data tidak dapat diubah!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, konfirmasi',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('confirm-form').submit();
            }
        });
    }
</script>
@endpush
