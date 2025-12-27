@extends('layouts.app')

@section('title', 'Confirm Transfer')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Confirm</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        Confirm Transfer #{{ $transfer->reference }}
                    </div>
                    <div class="card-body">
                        @include('utils.alerts')

                        <p><strong>Date:</strong> {{ $transfer->date }}</p>
                        <p><strong>From Warehouse:</strong> {{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</p>
                        <p><strong>To Branch:</strong> {{ $transfer->toBranch->name ?? '-' }}</p>
                        <p><strong>Note:</strong> {{ $transfer->note ?? '-' }}</p>

                        <hr>

                        <h5>Items</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center" style="width: 120px;">Qty Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($transfer->items as $item)
                                        <tr>
                                            <td>{{ $item->product->product_name ?? '-' }}</td>
                                            <td class="text-center">
                                                <span class="badge badge-primary">{{ (int) $item->quantity }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <form id="confirm-form" action="{{ route('transfers.confirm.store', $transfer->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="form-group mt-4">
                                <label for="to_warehouse_id">Destination Warehouse <span class="text-danger">*</span></label>
                                <select name="to_warehouse_id" class="form-control" required>
                                    <option value="">-- Select Warehouse --</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                            {{ $wh->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('to_warehouse_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mt-3">
                                <label for="delivery_code">Delivery Code (from Surat Jalan) <span class="text-danger">*</span></label>
                                <input type="text"
                                    name="delivery_code"
                                    class="form-control"
                                    maxlength="6"
                                    required
                                    value="{{ old('delivery_code') }}"
                                    placeholder="Contoh: A1B2C3">
                                @error('delivery_code')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr class="mt-4">

                            <h5 class="mb-2">Receive Details (per item)</h5>
                            <div class="alert alert-light border mb-3">
                                <div class="d-flex align-items-start">
                                    <div>
                                        <div class="font-weight-bold mb-1">Rule Validasi</div>
                                        <div class="text-muted">
                                            Isi qty yang diterima per produk. Sistem akan validasi:
                                            <strong>Received + Defect + Damaged = Sent</strong>.
                                        </div>
                                        <div class="text-muted mt-2">
                                            <small>
                                                - <strong>Defect</strong> tetap masuk stok, tapi dicatat sebagai defect (per unit).<br>
                                                - <strong>Damaged/Pecah</strong> dibuat mutasi IN lalu OUT (stok tidak bertambah bersih), dicatat sebagai damaged (per unit).
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-modern" id="receive-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 320px;">Product</th>
                                            <th class="text-center" style="width: 90px;">Sent</th>
                                            <th class="text-center" style="width: 110px;">Received</th>
                                            <th class="text-center" style="width: 110px;">Defect</th>
                                            <th class="text-center" style="width: 110px;">Damaged</th>
                                            <th class="text-center" style="width: 170px;">Per-Unit Notes</th>
                                            <th class="text-center" style="width: 140px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transfer->items as $idx => $item)
                                            @php
                                                $sent = (int) $item->quantity;
                                                $oldReceived = old("items.$idx.qty_received", $sent);
                                                $oldDefect   = old("items.$idx.qty_defect", 0);
                                                $oldDamaged  = old("items.$idx.qty_damaged", 0);

                                                $oldDefects  = old("items.$idx.defects", []);
                                                $oldDamages  = old("items.$idx.damaged_items", []);
                                            @endphp

                                            <tr class="receive-row"
                                                data-index="{{ $idx }}"
                                                data-sent="{{ $sent }}">
                                                <td class="align-middle">
                                                    <div class="d-flex align-items-start justify-content-between">
                                                        <div>
                                                            <div class="font-weight-bold">{{ $item->product->product_name ?? '-' }}</div>
                                                            <div class="text-muted"><small>{{ $item->product->product_code ?? '' }}</small></div>
                                                        </div>
                                                        <span class="badge badge-pill badge-light border px-2 py-1">
                                                            PID: {{ (int) $item->product_id }}
                                                        </span>
                                                    </div>

                                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ (int) $item->product_id }}">
                                                    <input type="hidden" name="items[{{ $idx }}][qty_sent]" value="{{ $sent }}">
                                                </td>

                                                <td class="text-center align-middle">
                                                    <span class="badge badge-primary sent-badge">{{ $sent }}</span>
                                                </td>

                                                <td class="text-center align-middle">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        class="form-control form-control-sm text-center qty-input qty-received"
                                                        name="items[{{ $idx }}][qty_received]"
                                                        value="{{ (int) $oldReceived }}"
                                                        required>
                                                </td>

                                                <td class="text-center align-middle">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        class="form-control form-control-sm text-center qty-input qty-defect"
                                                        name="items[{{ $idx }}][qty_defect]"
                                                        value="{{ (int) $oldDefect }}"
                                                        required>
                                                </td>

                                                <td class="text-center align-middle">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        class="form-control form-control-sm text-center qty-input qty-damaged"
                                                        name="items[{{ $idx }}][qty_damaged]"
                                                        value="{{ (int) $oldDamaged }}"
                                                        required>
                                                </td>

                                                <td class="text-center align-middle">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-notes"
                                                        data-target="#perUnitWrap-{{ $idx }}"
                                                    >
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
                                                <td colspan="7" class="perunit-td">
                                                    <div class="perunit-card">
                                                        <div class="perunit-card-header">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div class="font-weight-bold">
                                                                    Per-Unit Notes — {{ $item->product->product_name ?? '-' }}
                                                                </div>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-close-notes" data-target="#perUnitWrap-{{ $idx }}">
                                                                    Close
                                                                </button>
                                                            </div>
                                                            <div class="text-muted mt-1">
                                                                <small>
                                                                    Defect/Damaged disimpan <b>per unit</b> (masing-masing baris qty = 1), jadi tiap unit bisa punya catatan sendiri.
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <div class="perunit-card-body">
                                                            <div class="row">
                                                                <div class="col-lg-6">
                                                                    <div class="section-title defect-title">
                                                                        Defect Items (<span class="defect-count-text">0</span>)
                                                                    </div>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered mb-0 section-table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width: 55px;" class="text-center">#</th>
                                                                                    <th style="min-width: 160px;">Defect Type *</th>
                                                                                    <th>Defect Description</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody class="defect-tbody">
                                                                                {{-- built by JS --}}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div class="text-muted mt-2">
                                                                        <small>Contoh defect type: bubble, retak ringan, baret, distorsi.</small>
                                                                    </div>
                                                                </div>

                                                                <div class="col-lg-6 mt-3 mt-lg-0">
                                                                    <div class="section-title damaged-title">
                                                                        Damaged Items (<span class="damaged-count-text">0</span>)
                                                                    </div>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-bordered mb-0 section-table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width: 55px;" class="text-center">#</th>
                                                                                    <th>Damaged Reason *</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody class="damaged-tbody">
                                                                                {{-- built by JS --}}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div class="text-muted mt-2">
                                                                        <small>Contoh reason: pecah sudut kiri saat bongkar peti, pecah terkena paku tatakan.</small>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="text-muted mt-3">
                                                                <small>
                                                                    Tips: kalau defect/damaged tidak ada, biarkan qty = 0 dan notes tidak perlu diisi.
                                                                </small>
                                                            </div>

                                                            {{-- OLD VALUES for hydrate --}}
                                                            <textarea class="d-none old-defects-json" data-idx="{{ $idx }}">{{ json_encode($oldDefects) }}</textarea>
                                                            <textarea class="d-none old-damages-json" data-idx="{{ $idx }}">{{ json_encode($oldDamages) }}</textarea>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @error('items')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-muted">
                                    <small>
                                        Tips: Kalau tidak ada defect/damaged, cukup biarkan Defect = 0, Damaged = 0, dan Received = Sent.
                                    </small>
                                </div>
                                <button type="button" class="btn btn-success" onclick="confirmSubmit()">
                                    Confirm & Receive <i class="bi bi-check2-circle"></i>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_css')
<style>
    /* Modern table look */
    .table-modern thead th {
        background: #f8fafc;
        font-weight: 600;
        color: #334155;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-modern tbody td {
        vertical-align: middle;
    }

    /* Notes button modern */
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

    .badge-defect {
        background: #2563eb;
        color: #fff;
        font-weight: 700;
        padding: 5px 8px;
    }
    .badge-damaged {
        background: #ef4444;
        color: #fff;
        font-weight: 700;
        padding: 5px 8px;
    }

    /* Per-unit wrapper cell */
    .perunit-td {
        background: #f1f5f9;
        border-top: 0 !important;
        padding: 14px !important;
    }

    /* Per-unit card modern */
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
    .perunit-card-body {
        padding: 14px;
    }

    /* Section titles */
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

    /* Per-unit tables */
    .section-table thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
    }
    .section-table td, .section-table th {
        border-color: #e2e8f0 !important;
    }

    /* Status badge polish */
    .row-status.badge-success { background: #16a34a; }
    .row-status.badge-danger  { background: #ef4444; }
    .row-status.badge-warning { background: #f59e0b; color: #111827; }
    .row-status.badge-secondary { background: #64748b; }

    /* Inputs look a bit cleaner */
    .form-control-sm {
        border-radius: 10px;
        border-color: #e2e8f0;
    }
    .form-control-sm:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 0.2rem rgba(147,197,253,0.25);
    }
</style>
@endpush

@push('page_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function asInt(val) {
        const n = parseInt(val, 10);
        return isNaN(n) ? 0 : n;
    }

    function safeJsonParse(text) {
        try {
            if (!text) return [];
            return JSON.parse(text);
        } catch (e) {
            return [];
        }
    }

    function updateRowStatus(row) {
        const sent = asInt(row.dataset.sent);

        const receivedInput = row.querySelector('.qty-received');
        const defectInput   = row.querySelector('.qty-defect');
        const damagedInput  = row.querySelector('.qty-damaged');

        const received = asInt(receivedInput.value);
        const defect   = asInt(defectInput.value);
        const damaged  = asInt(damagedInput.value);

        const statusBadge = row.querySelector('.row-status');
        const hint = row.querySelector('.row-hint');

        if (received < 0 || defect < 0 || damaged < 0) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Qty tidak boleh negatif.';
            return false;
        }

        const total = received + defect + damaged;

        if (total !== sent) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'MISMATCH';
            hint.textContent = `Total (${total}) harus sama dengan Sent (${sent}).`;
            return false;
        }

        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);

        if (defect > 0) {
            if (!perWrap) {
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = 'Defect > 0, tapi per-unit section tidak ditemukan.';
                return false;
            }

            const defectRows = perWrap.querySelectorAll('.defect-tbody tr');
            if (defectRows.length !== defect) {
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = `Defect = ${defect}, tapi detail defect belum lengkap.`;
                return false;
            }

            for (let i = 0; i < defectRows.length; i++) {
                const typeInput = defectRows[i].querySelector('input.defect-type-input');
                if (!typeInput || !typeInput.value.trim()) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Defect Type wajib diisi untuk setiap defect item.';
                    return false;
                }
            }
        }

        if (damaged > 0) {
            if (!perWrap) {
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = 'Damaged > 0, tapi per-unit section tidak ditemukan.';
                return false;
            }

            const damagedRows = perWrap.querySelectorAll('.damaged-tbody tr');
            if (damagedRows.length !== damaged) {
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = `Damaged = ${damaged}, tapi detail damaged belum lengkap.`;
                return false;
            }

            for (let i = 0; i < damagedRows.length; i++) {
                const reasonInput = damagedRows[i].querySelector('textarea.damaged-reason-input');
                if (!reasonInput || !reasonInput.value.trim()) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Damaged Reason wajib diisi untuk setiap damaged item.';
                    return false;
                }
            }
        }

        statusBadge.className = 'badge badge-success row-status';
        statusBadge.textContent = 'OK';
        hint.textContent = `Total = ${total}`;
        return true;
    }

    function validateAllRows() {
        const rows = document.querySelectorAll('.receive-row');
        let ok = true;
        rows.forEach(row => {
            const rowOk = updateRowStatus(row);
            if (!rowOk) ok = false;
        });
        return ok;
    }

    function ensurePerUnitTablesBuilt(row) {
        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);
        if (!perWrap) return;

        const defectInput = row.querySelector('.qty-defect');
        const damagedInput = row.querySelector('.qty-damaged');

        const defectCount = asInt(defectInput.value);
        const damagedCount = asInt(damagedInput.value);

        const defectTbody = perWrap.querySelector('.defect-tbody');
        const damagedTbody = perWrap.querySelector('.damaged-tbody');

        const defectCountText = perWrap.querySelector('.defect-count-text');
        const damagedCountText = perWrap.querySelector('.damaged-count-text');

        if (!perWrap.dataset.hydrated) {
            const oldDefText = perWrap.querySelector('.old-defects-json')?.value || perWrap.querySelector('.old-defects-json')?.textContent || '';
            const oldDamText = perWrap.querySelector('.old-damages-json')?.value || perWrap.querySelector('.old-damages-json')?.textContent || '';

            const oldDef = safeJsonParse(oldDefText);
            const oldDam = safeJsonParse(oldDamText);

            perWrap.dataset.oldDefects = JSON.stringify(oldDef || []);
            perWrap.dataset.oldDamages = JSON.stringify(oldDam || []);
            perWrap.dataset.hydrated = '1';
        }

        const oldDefects = safeJsonParse(perWrap.dataset.oldDefects || '[]');
        const oldDamages = safeJsonParse(perWrap.dataset.oldDamages || '[]');

        defectTbody.innerHTML = '';
        for (let i = 0; i < defectCount; i++) {
            const prev = oldDefects[i] || {};
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td class="text-center align-middle">${i + 1}</td>
                <td class="align-middle">
                    <input
                        type="text"
                        class="form-control form-control-sm defect-type-input"
                        name="items[${idx}][defects][${i}][defect_type]"
                        value="${(prev.defect_type || '').replace(/"/g, '&quot;')}"
                        placeholder="contoh: bubble / retak ringan"
                        required
                    >
                </td>
                <td class="align-middle">
                    <textarea
                        class="form-control form-control-sm defect-desc-input"
                        name="items[${idx}][defects][${i}][defect_description]"
                        rows="2"
                        placeholder="keterangan defect (bagian mana / catatan)">${(prev.defect_description || '')}</textarea>
                </td>
            `;
            defectTbody.appendChild(tr);
        }

        damagedTbody.innerHTML = '';
        for (let i = 0; i < damagedCount; i++) {
            const prev = oldDamages[i] || {};
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td class="text-center align-middle">${i + 1}</td>
                <td class="align-middle">
                    <textarea
                        class="form-control form-control-sm damaged-reason-input"
                        name="items[${idx}][damaged_items][${i}][damaged_reason]"
                        rows="2"
                        placeholder="contoh: pecah di sudut kiri saat bongkar peti"
                        required>${(prev.damaged_reason || '')}</textarea>
                </td>
            `;
            damagedTbody.appendChild(tr);
        }

        defectCountText.textContent = defectCount;
        damagedCountText.textContent = damagedCount;

        const btn = row.querySelector('.btn-notes');
        if (btn) {
            const bd = btn.querySelector('.badge-defect-count');
            const bm = btn.querySelector('.badge-damaged-count');
            if (bd) bd.textContent = defectCount;
            if (bm) bm.textContent = damagedCount;
        }

        perWrap.querySelectorAll('input, textarea').forEach(el => {
            el.addEventListener('input', () => updateRowStatus(row));
        });

        const currentDef = [];
        perWrap.querySelectorAll('.defect-tbody tr').forEach((tr) => {
            currentDef.push({
                defect_type: tr.querySelector('input.defect-type-input')?.value || '',
                defect_description: tr.querySelector('textarea.defect-desc-input')?.value || ''
            });
        });

        const currentDam = [];
        perWrap.querySelectorAll('.damaged-tbody tr').forEach((tr) => {
            currentDam.push({
                damaged_reason: tr.querySelector('textarea.damaged-reason-input')?.value || ''
            });
        });

        perWrap.dataset.oldDefects = JSON.stringify(currentDef);
        perWrap.dataset.oldDamages = JSON.stringify(currentDam);
    }

    function togglePerUnit(targetSelector, show) {
        const el = document.querySelector(targetSelector);
        if (!el) return;
        el.style.display = show ? '' : 'none';
    }

    function initRow(row) {
        ensurePerUnitTablesBuilt(row);
        updateRowStatus(row);

        row.querySelectorAll('.qty-input').forEach(el => {
            el.addEventListener('input', function () {
                ensurePerUnitTablesBuilt(row);
                updateRowStatus(row);
            });
        });

        const btn = row.querySelector('.btn-notes');
        if (btn) {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-target');
                ensurePerUnitTablesBuilt(row);
                togglePerUnit(target, true);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.receive-row').forEach(row => initRow(row));

        document.querySelectorAll('.btn-close-notes').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-target');
                togglePerUnit(target, false);
            });
        });
    });

    function confirmSubmit() {
        const isValid = validateAllRows();

        if (!isValid) {
            Swal.fire({
                title: 'Ada data yang belum valid',
                text: 'Periksa kembali input qty dan per-unit notes. Pastikan total sama dengan qty sent.',
                icon: 'error',
            });
            return;
        }

        Swal.fire({
            title: 'Yakin ingin konfirmasi transfer ini?',
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
