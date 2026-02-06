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
                                        @php
                                            $cond = strtolower((string) ($item->condition ?? 'good'));
                                            $condLabel = match ($cond) {
                                                'good' => 'GOOD',
                                                'defect' => 'DEFECT',
                                                'damaged' => 'DAMAGED',
                                                default => strtoupper($cond),
                                            };
                                            $condClass = match ($cond) {
                                                'good' => 'badge-success',
                                                'defect' => 'badge-warning',
                                                'damaged' => 'badge-danger',
                                                default => 'badge-secondary',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $item->product->product_name ?? '-' }}
                                                <span class="badge {{ $condClass }} ml-2">{{ $condLabel }}</span>
                                                <div class="text-muted small">{{ $item->product->product_code ?? '' }}</div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-primary">{{ (int) $item->quantity }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <form id="confirm-form"
                              action="{{ route('transfers.confirm.store', $transfer->id) }}"
                              method="POST"
                              enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="confirm_issue" id="confirm_issue" value="0">

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
                                            <strong>Good + Defect + Damaged ≤ Sent</strong>.
                                        </div>
                                        <div class="text-muted mt-2">
                                            <small>
                                                - Jika <strong>total &lt; sent</strong>, maka sisanya dianggap <strong>Remaining/Missing</strong> dan butuh konfirmasi “Complete with issue”.<br>
                                                - <strong>GOOD</strong> bisa dibagi ke beberapa rack (split).<br>
                                                - <strong>Defect/Damaged</strong> dicatat <strong>per unit</strong> dan tiap unit wajib pilih rack.<br>
                                                - Foto opsional, tapi sangat disarankan untuk audit.
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
                                            <th class="text-center" style="width: 110px;">Good</th>
                                            <th class="text-center" style="width: 110px;">Defect</th>
                                            <th class="text-center" style="width: 110px;">Damaged</th>

                                            <th class="text-center" style="width: 180px;">Rack Allocation</th>
                                            <th class="text-center" style="width: 170px;">Per-Unit Notes</th>
                                            <th class="text-center" style="width: 140px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transfer->items as $idx => $item)
                                           @php
                                                $sent = (int) $item->quantity;
                                                $cond = strtolower((string) ($item->condition ?? 'good'));

                                                $defaultGood = $cond === 'good' ? $sent : 0;
                                                $defaultDef  = $cond === 'defect' ? $sent : 0;
                                                $defaultDam  = $cond === 'damaged' ? $sent : 0;

                                                $oldReceived = old("items.$idx.qty_received", $defaultGood);
                                                $oldDefect   = old("items.$idx.qty_defect", $defaultDef);
                                                $oldDamaged  = old("items.$idx.qty_damaged", $defaultDam);

                                                $oldGoodAlloc = old("items.$idx.good_allocations", []);
                                                $oldDefects = old("items.$idx.defects", []);
                                                $oldDamages = old("items.$idx.damaged_items", []);
                                            @endphp

                                            <tr class="receive-row"
                                                data-index="{{ $idx }}"
                                                data-sent="{{ $sent }}"
                                                data-condition="{{ $cond }}">
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

                                                    <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ (int) $item->id }}">
                                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ (int) $item->product_id }}">
                                                    <input type="hidden" name="items[{{ $idx }}][condition]" value="{{ $cond }}">
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
                                                        class="btn btn-sm btn-outline-primary btn-good-rack"
                                                        data-target="#goodAllocWrap-{{ $idx }}">
                                                        Good Racks
                                                    </button>
                                                </td>

                                                <td class="text-center align-middle">
                                                    <button
                                                        type="button"
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

                                            {{-- GOOD ALLOCATION --}}
                                            <tr class="goodalloc-row" id="goodAllocWrap-{{ $idx }}" style="display:none;">
                                                <td colspan="8" class="perunit-td">
                                                    <div class="perunit-card">
                                                        <div class="perunit-card-header">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div class="font-weight-bold">
                                                                    Good Rack Allocation — {{ $item->product->product_name ?? '-' }}
                                                                </div>
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-secondary btn-close-goodalloc"
                                                                        data-target="#goodAllocWrap-{{ $idx }}">
                                                                    Close
                                                                </button>
                                                            </div>
                                                            <div class="text-muted mt-1">
                                                                <small>
                                                                    Total qty pada tabel ini harus sama dengan nilai <b>Good</b>.
                                                                    Kamu bisa split Good ke beberapa rack.
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <div class="perunit-card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <div class="text-muted">
                                                                    <small>Rack wajib dipilih hanya untuk baris yang qty &gt; 0.</small>
                                                                </div>
                                                                <button type="button"
                                                                        class="btn btn-sm btn-primary btn-add-goodalloc"
                                                                        data-idx="{{ $idx }}">
                                                                    + Add Row
                                                                </button>
                                                            </div>

                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered mb-0 section-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th class="text-center" style="width: 55px;">#</th>
                                                                            <th style="width: 260px;">To Rack *</th>
                                                                            <th class="text-center" style="width: 160px;">Qty</th>
                                                                            <th class="text-center" style="width: 90px;">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="goodalloc-tbody" data-idx="{{ $idx }}">
                                                                        {{-- built by JS --}}
                                                                    </tbody>
                                                                    <tfoot>
                                                                        <tr>
                                                                            <th colspan="2" class="text-right">Total</th>
                                                                            <th class="text-center">
                                                                                <span class="goodalloc-total" data-idx="{{ $idx }}">0</span>
                                                                            </th>
                                                                            <th></th>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>

                                                            <textarea class="d-none old-goodalloc-json" data-idx="{{ $idx }}">{{ json_encode($oldGoodAlloc) }}</textarea>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            {{-- PER-UNIT NOTES --}}
                                            <tr class="perunit-row" id="perUnitWrap-{{ $idx }}" style="display:none;">
                                                <td colspan="8" class="perunit-td">
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
                                                                    Defect/Damaged disimpan <b>per unit</b> (masing-masing baris qty = 1),
                                                                    jadi tiap unit bisa punya catatan + foto + rack sendiri.
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
                                                                                    <th style="width: 190px;">To Rack *</th>
                                                                                    <th style="min-width: 160px;">Defect Type *</th>
                                                                                    <th>Defect Description</th>
                                                                                    <th style="width: 190px;">Photo (optional)</th>
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
                                                                                    <th style="width: 190px;">To Rack *</th>
                                                                                    <th>Damaged Reason *</th>
                                                                                    <th style="width: 190px;">Photo (optional)</th>
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
                                        Tips: Kalau tidak ada defect/damaged, cukup biarkan Defect = 0, Damaged = 0, dan Good = Sent.
                                    </small>
                                </div>
                                <button type="button" class="btn btn-success" onclick="confirmSubmit()">
                                    Confirm & Receive <i class="bi bi-check2-circle"></i>
                                </button>
                            </div>
                        </form>

                        <script>
                            window.RACKS_BY_WAREHOUSE = @json(
                                $racksByWarehouse->map(fn($rows) =>
                                    $rows->map(fn($r) => [
                                        'id'    => (int) $r->id,
                                        'label' => trim(($r->code ? $r->code.' - ' : '').($r->name ?? 'Rack')),
                                    ])->values()
                                )
                            );
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_css')
<style>
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

    function getWarehouseId() {
        const whSelect = document.querySelector('select[name="to_warehouse_id"]');
        return whSelect ? whSelect.value : null;
    }

    function buildRackOptionsHtml(whId, selectedValue) {
        let html = `<option value="">-- Select Rack --</option>`;
        if (!whId || !window.RACKS_BY_WAREHOUSE || !window.RACKS_BY_WAREHOUSE[whId]) return html;

        window.RACKS_BY_WAREHOUSE[whId].forEach(r => {
            const sel = (selectedValue && String(selectedValue) === String(r.id)) ? 'selected' : '';
            html += `<option value="${r.id}" ${sel}>${r.label}</option>`;
        });

        return html;
    }

    // ==========================
    // GOOD ALLOCATION
    // ==========================
    function rebuildGoodAlloc(idx, keepOld = true) {
        const whId = getWarehouseId();
        const wrap = document.getElementById('goodAllocWrap-' + idx);
        if (!wrap) return;

        const tbody = wrap.querySelector('.goodalloc-tbody');
        const totalSpan = wrap.querySelector('.goodalloc-total');

        let allocations = [];

        if (keepOld) {
            const oldText = wrap.querySelector('.old-goodalloc-json')?.value || wrap.querySelector('.old-goodalloc-json')?.textContent || '';
            allocations = safeJsonParse(oldText);
        }

        if (!Array.isArray(allocations) || allocations.length === 0) {
            allocations = [
                { to_rack_id: '', qty: 0 }
            ];
        }

        tbody.innerHTML = '';

        allocations.forEach((a, i) => {
            const rackVal = a.to_rack_id ?? '';
            const qtyVal = a.qty ?? 0;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center align-middle">${i + 1}</td>
                <td class="align-middle">
                    <select class="form-control form-control-sm goodalloc-rack"
                            name="items[${idx}][good_allocations][${i}][to_rack_id]">
                        ${buildRackOptionsHtml(whId, rackVal)}
                    </select>
                </td>
                <td class="align-middle">
                    <input type="number"
                           min="0"
                           step="1"
                           class="form-control form-control-sm text-center goodalloc-qty"
                           name="items[${idx}][good_allocations][${i}][qty]"
                           value="${asInt(qtyVal)}">
                </td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-danger btn-remove-goodalloc" data-idx="${idx}" data-row="${i}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('select, input').forEach(el => {
            el.addEventListener('change', () => syncGoodAllocTotal(idx));
            el.addEventListener('input', () => syncGoodAllocTotal(idx));
        });

        tbody.querySelectorAll('.btn-remove-goodalloc').forEach(btn => {
            btn.addEventListener('click', () => {
                removeGoodAllocRow(idx, asInt(btn.getAttribute('data-row')));
            });
        });

        syncGoodAllocTotal(idx);
    }

    function readGoodAllocations(idx) {
        const wrap = document.getElementById('goodAllocWrap-' + idx);
        if (!wrap) return [];

        const rows = wrap.querySelectorAll('.goodalloc-tbody tr');
        const result = [];

        rows.forEach((tr) => {
            const rack = tr.querySelector('select.goodalloc-rack')?.value || '';
            const qty = asInt(tr.querySelector('input.goodalloc-qty')?.value || 0);
            result.push({ to_rack_id: rack, qty: qty });
        });

        return result;
    }

    function syncGoodAllocTotal(idx) {
        const wrap = document.getElementById('goodAllocWrap-' + idx);
        if (!wrap) return;

        const totalSpan = wrap.querySelector('.goodalloc-total');
        const allocations = readGoodAllocations(idx);

        let total = 0;
        allocations.forEach(a => total += asInt(a.qty || 0));
        if (totalSpan) totalSpan.textContent = String(total);

        const row = document.querySelector(`.receive-row[data-index="${idx}"]`);
        if (row) updateRowStatus(row);
    }

    function addGoodAllocRow(idx) {
        const wrap = document.getElementById('goodAllocWrap-' + idx);
        if (!wrap) return;

        const tbody = wrap.querySelector('.goodalloc-tbody');
        const rowCount = tbody.querySelectorAll('tr').length;

        const whId = getWarehouseId();

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center align-middle">${rowCount + 1}</td>
            <td class="align-middle">
                <select class="form-control form-control-sm goodalloc-rack"
                        name="items[${idx}][good_allocations][${rowCount}][to_rack_id]">
                    ${buildRackOptionsHtml(whId, '')}
                </select>
            </td>
            <td class="align-middle">
                <input type="number"
                       min="0"
                       step="1"
                       class="form-control form-control-sm text-center goodalloc-qty"
                       name="items[${idx}][good_allocations][${rowCount}][qty]"
                       value="0">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm btn-danger btn-remove-goodalloc" data-idx="${idx}" data-row="${rowCount}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);

        rebuildGoodAlloc(idx, false);
    }

    function removeGoodAllocRow(idx, rowIndex) {
        const wrap = document.getElementById('goodAllocWrap-' + idx);
        if (!wrap) return;

        const tbody = wrap.querySelector('.goodalloc-tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length <= 1) return;

        if (rows[rowIndex]) rows[rowIndex].remove();
        rebuildGoodAlloc(idx, false);
    }

    // ==========================
    // PER-UNIT TABLES (DEFECT/DAMAGED)
    // ==========================
    function ensurePerUnitTablesBuilt(row) {
        const idx = row.dataset.index;
        const perWrap = document.getElementById('perUnitWrap-' + idx);
        if (!perWrap) return;

        const whId = getWarehouseId();

        const defectInput = row.querySelector('.qty-defect');
        const damagedInput = row.querySelector('.qty-damaged');

        const defectCount = asInt(defectInput.value);
        const damagedCount = asInt(damagedInput.value);

        const defectTbody = perWrap.querySelector('.defect-tbody');
        const damagedTbody = perWrap.querySelector('.damaged-tbody');

        const defectCountText = perWrap.querySelector('.defect-count-text');
        const damagedCountText = perWrap.querySelector('.damaged-count-text');

        // ============================================
        // ✅ FIX 1: setiap kali dipanggil, snapshot dulu input terbaru dari DOM
        // supaya kalau terjadi rebuild, data user tidak hilang
        // ============================================
        const snapshotCurrentDomToDataset = () => {
            const currentDef = [];
            perWrap.querySelectorAll('.defect-tbody tr').forEach((tr) => {
                currentDef.push({
                    to_rack_id: tr.querySelector('select.defect-rack-select')?.value || '',
                    defect_type: tr.querySelector('input.defect-type-input')?.value || '',
                    defect_description: tr.querySelector('textarea.defect-desc-input')?.value || ''
                });
            });

            const currentDam = [];
            perWrap.querySelectorAll('.damaged-tbody tr').forEach((tr) => {
                currentDam.push({
                    to_rack_id: tr.querySelector('select.damaged-rack-select')?.value || '',
                    damaged_reason: tr.querySelector('textarea.damaged-reason-input')?.value || ''
                });
            });

            perWrap.dataset.oldDefects = JSON.stringify(currentDef);
            perWrap.dataset.oldDamages = JSON.stringify(currentDam);
        };

        // snapshot dulu supaya input terakhir tidak hilang
        if (perWrap.dataset.hydrated === '1') {
            snapshotCurrentDomToDataset();
        }

        // ============================================
        // ✅ FIX 2: jangan rebuild kalau tidak perlu
        // rebuild hanya jika:
        // - belum pernah build
        // - atau jumlah row sekarang mismatch dengan qty defect/damaged
        // ============================================
        const currentDefRows = defectTbody ? defectTbody.querySelectorAll('tr').length : 0;
        const currentDamRows = damagedTbody ? damagedTbody.querySelectorAll('tr').length : 0;

        const needRebuild =
            (perWrap.dataset.hydrated !== '1') ||
            (currentDefRows !== defectCount) ||
            (currentDamRows !== damagedCount);

        // kalau tidak perlu rebuild, cukup update counter/badge aja
        if (!needRebuild) {
            if (defectCountText) defectCountText.textContent = defectCount;
            if (damagedCountText) damagedCountText.textContent = damagedCount;

            const btn = row.querySelector('.btn-notes');
            if (btn) {
                const bd = btn.querySelector('.badge-defect-count');
                const bm = btn.querySelector('.badge-damaged-count');
                if (bd) bd.textContent = defectCount;
                if (bm) bm.textContent = damagedCount;
            }
            return;
        }

        // ============================================
        // first hydrate: ambil old JSON dari hidden textarea
        // ============================================
        if (perWrap.dataset.hydrated !== '1') {
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

        // ============================================
        // rebuild defect rows sesuai defectCount
        // ============================================
        defectTbody.innerHTML = '';
        for (let i = 0; i < defectCount; i++) {
            const prev = oldDefects[i] || {};
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td class="text-center align-middle">${i + 1}</td>

                <td class="align-middle">
                    <select class="form-control form-control-sm defect-rack-select"
                            name="items[${idx}][defects][${i}][to_rack_id]"
                            required>
                        ${buildRackOptionsHtml(whId, prev.to_rack_id || '')}
                    </select>
                </td>

                <td class="align-middle">
                    <input
                        type="text"
                        class="form-control form-control-sm defect-type-input"
                        name="items[${idx}][defects][${i}][defect_type]"
                        value="${String(prev.defect_type || '').replace(/"/g, '&quot;')}"
                        placeholder="contoh: bubble / retak ringan"
                        required
                    >
                </td>

                <td class="align-middle">
                    <textarea
                        class="form-control form-control-sm defect-desc-input"
                        name="items[${idx}][defects][${i}][defect_description]"
                        rows="2"
                        placeholder="keterangan defect (bagian mana / catatan)">${String(prev.defect_description || '')}</textarea>
                </td>

                <td class="align-middle">
                    <input
                        type="file"
                        accept="image/*"
                        class="photo-input"
                        name="items[${idx}][defects][${i}][photo]"
                    >
                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                </td>
            `;
            defectTbody.appendChild(tr);
        }

        // ============================================
        // rebuild damaged rows sesuai damagedCount
        // ============================================
        damagedTbody.innerHTML = '';
        for (let i = 0; i < damagedCount; i++) {
            const prev = oldDamages[i] || {};
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td class="text-center align-middle">${i + 1}</td>

                <td class="align-middle">
                    <select class="form-control form-control-sm damaged-rack-select"
                            name="items[${idx}][damaged_items][${i}][to_rack_id]"
                            required>
                        ${buildRackOptionsHtml(whId, prev.to_rack_id || '')}
                    </select>
                </td>

                <td class="align-middle">
                    <textarea
                        class="form-control form-control-sm damaged-reason-input"
                        name="items[${idx}][damaged_items][${i}][damaged_reason]"
                        rows="2"
                        placeholder="contoh: pecah di sudut kiri saat bongkar peti"
                        required>${String(prev.damaged_reason || '')}</textarea>
                </td>

                <td class="align-middle">
                    <input
                        type="file"
                        accept="image/*"
                        class="photo-input"
                        name="items[${idx}][damaged_items][${i}][photo]"
                    >
                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                </td>
            `;
            damagedTbody.appendChild(tr);
        }

        if (defectCountText) defectCountText.textContent = defectCount;
        if (damagedCountText) damagedCountText.textContent = damagedCount;

        const btn = row.querySelector('.btn-notes');
        if (btn) {
            const bd = btn.querySelector('.badge-defect-count');
            const bm = btn.querySelector('.badge-damaged-count');
            if (bd) bd.textContent = defectCount;
            if (bm) bm.textContent = damagedCount;
        }

        // ============================================
        // ✅ FIX 3: setiap input/change, update status & snapshot lagi
        // ============================================
        perWrap.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', () => {
                snapshotCurrentDomToDataset();
                updateRowStatus(row);
            });
            el.addEventListener('change', () => {
                snapshotCurrentDomToDataset();
                updateRowStatus(row);
            });
        });

        // setelah rebuild, snapshot supaya dataset sinkron
        snapshotCurrentDomToDataset();
    }

    function toggleSection(targetSelector, show) {
        const el = document.querySelector(targetSelector);
        if (!el) return;
        el.style.display = show ? '' : 'none';
    }

    // ==========================
    // VALIDATION STATUS
    // ==========================
    function updateRowStatus(row) {
        const sent = asInt(row.dataset.sent);
        const cond = (row.dataset.condition || 'good').toLowerCase();

        const statusBadge = row.querySelector('.row-status');
        const hint = row.querySelector('.row-hint');

        const receivedInput = row.querySelector('.qty-received');
        const defectInput   = row.querySelector('.qty-defect');
        const damagedInput  = row.querySelector('.qty-damaged');

        const received = asInt(receivedInput.value);
        const defect   = asInt(defectInput.value);
        const damaged  = asInt(damagedInput.value);

        if (received < 0 || defect < 0 || damaged < 0) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Qty tidak boleh negatif.';
            return false;
        }

        if (cond === 'defect' && received > 0) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Item ini dikirim dalam kondisi DEFECT, jadi tidak boleh diterima sebagai GOOD.';
            return false;
        }

        if (cond === 'damaged' && (received > 0 || defect > 0)) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'INVALID';
            hint.textContent = 'Item ini dikirim dalam kondisi DAMAGED, jadi tidak boleh diterima sebagai GOOD/DEFECT.';
            return false;
        }

        const total = received + defect + damaged;

        if (total > sent) {
            statusBadge.className = 'badge badge-danger row-status';
            statusBadge.textContent = 'MISMATCH';
            hint.textContent = `Total (${total}) tidak boleh lebih dari Sent (${sent}).`;
            return false;
        }

        const idx = row.dataset.index;

        // ✅ jika ada GOOD > 0, wajib ada allocation & racknya valid
        if (received > 0) {
            const allocs = readGoodAllocations(idx);
            let sumAlloc = 0;
            for (const a of allocs) {
                const q = asInt(a.qty);
                sumAlloc += q;
                if (q > 0 && (!a.to_rack_id || String(a.to_rack_id).trim() === '')) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'GOOD > 0: setiap baris allocation yang qty > 0 wajib pilih rack.';
                    return false;
                }
            }
            if (sumAlloc !== received) {
                statusBadge.className = 'badge badge-warning row-status';
                statusBadge.textContent = 'NEED INFO';
                hint.textContent = `Total rack allocation (${sumAlloc}) harus sama dengan GOOD (${received}).`;
                return false;
            }
        }

        // ✅ DEFECT per unit: wajib rack + defect type
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
                const rackSel = defectRows[i].querySelector('select.defect-rack-select');
                const typeInput = defectRows[i].querySelector('input.defect-type-input');

                if (!rackSel || !rackSel.value) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'To Rack wajib dipilih untuk setiap defect unit.';
                    return false;
                }

                if (!typeInput || !typeInput.value.trim()) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Defect Type wajib diisi untuk setiap defect item.';
                    return false;
                }
            }
        }

        // ✅ DAMAGED per unit: wajib rack + reason
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
                const rackSel = damagedRows[i].querySelector('select.damaged-rack-select');
                const reasonInput = damagedRows[i].querySelector('textarea.damaged-reason-input');

                if (!rackSel || !rackSel.value) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'To Rack wajib dipilih untuk setiap damaged unit.';
                    return false;
                }

                if (!reasonInput || !reasonInput.value.trim()) {
                    statusBadge.className = 'badge badge-warning row-status';
                    statusBadge.textContent = 'NEED INFO';
                    hint.textContent = 'Damaged Reason wajib diisi untuk setiap damaged item.';
                    return false;
                }
            }
        }

        if (total === sent) {
            statusBadge.className = 'badge badge-success row-status';
            statusBadge.textContent = 'OK';
            hint.textContent = `Total = ${total}`;
            return true;
        }

        statusBadge.className = 'badge badge-warning row-status';
        statusBadge.textContent = 'REMAINING';
        hint.textContent = `Remaining/Missing: ${sent - total}`;
        return true;
    }

    function validateAllRows() {
        const rows = document.querySelectorAll('.receive-row');
        let ok = true;
        rows.forEach(row => {
            ensurePerUnitTablesBuilt(row);
            const rowOk = updateRowStatus(row);
            if (!rowOk) ok = false;
        });
        return ok;
    }

    function getTotalMissing() {
        const rows = document.querySelectorAll('.receive-row');
        let missing = 0;

        rows.forEach(row => {
            const sent = asInt(row.dataset.sent);
            const received = asInt(row.querySelector('.qty-received').value);
            const defect = asInt(row.querySelector('.qty-defect').value);
            const damaged = asInt(row.querySelector('.qty-damaged').value);
            const total = received + defect + damaged;
            if (total < sent) missing += (sent - total);
        });

        return missing;
    }

    function initRow(row) {
        const idx = row.dataset.index;

        rebuildGoodAlloc(idx, true);
        syncGoodAllocTotal(idx);

        ensurePerUnitTablesBuilt(row);
        updateRowStatus(row);

        row.querySelectorAll('.qty-input').forEach(el => {
            el.addEventListener('input', function () {
                ensurePerUnitTablesBuilt(row);
                syncGoodAllocTotal(idx);
                updateRowStatus(row);
            });
        });

        const btnNotes = row.querySelector('.btn-notes');
        if (btnNotes) {
            btnNotes.addEventListener('click', () => {
                const target = btnNotes.getAttribute('data-target');
                ensurePerUnitTablesBuilt(row);
                toggleSection(target, true);
            });
        }

        const btnGood = row.querySelector('.btn-good-rack');
        if (btnGood) {
            btnGood.addEventListener('click', () => {
                const target = btnGood.getAttribute('data-target');
                rebuildGoodAlloc(idx, true);
                toggleSection(target, true);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const whSelect = document.querySelector('select[name="to_warehouse_id"]');
        if (whSelect) {
            whSelect.addEventListener('change', () => {
                // re-render all selects that depend on warehouse racks
                document.querySelectorAll('.receive-row').forEach(row => {
                    const idx = row.dataset.index;
                    rebuildGoodAlloc(idx, false);
                    ensurePerUnitTablesBuilt(row);
                    updateRowStatus(row);
                });
            });
        }

        document.querySelectorAll('.receive-row').forEach(row => initRow(row));

        document.querySelectorAll('.btn-close-notes').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-target');
                toggleSection(target, false);
            });
        });

        document.querySelectorAll('.btn-close-goodalloc').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-target');
                toggleSection(target, false);
            });
        });

        document.querySelectorAll('.btn-add-goodalloc').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = asInt(btn.getAttribute('data-idx'));
                addGoodAllocRow(idx);
            });
        });
    });

    function confirmSubmit() {
        const whId = getWarehouseId();
        if (!whId) {
            Swal.fire({
                title: 'Destination warehouse belum dipilih',
                text: 'Silakan pilih Destination Warehouse dulu.',
                icon: 'error',
            });
            return;
        }

        const isValid = validateAllRows();
        if (!isValid) {
            Swal.fire({
                title: 'Ada data yang belum valid',
                text: 'Periksa kembali qty, good rack allocation, rack per defect/damaged, dan notes.',
                icon: 'error',
            });
            return;
        }

        const totalMissing = getTotalMissing();

        const proceedMainConfirm = () => {
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
        };

        if (totalMissing > 0) {
            Swal.fire({
                title: 'Ada Remaining / Missing Qty',
                html: `Terdapat <b>${totalMissing}</b> item yang tidak diterima.<br>Apakah mau <b>complete</b> dengan status <b>bermasalah</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, complete with issue',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('confirm_issue').value = '1';
                    proceedMainConfirm();
                }
            });
            return;
        }

        document.getElementById('confirm_issue').value = '0';
        proceedMainConfirm();
    }
</script>
@endpush
