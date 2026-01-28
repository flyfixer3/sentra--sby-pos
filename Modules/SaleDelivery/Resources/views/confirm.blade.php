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
    $warehouseId = (int) ($saleDelivery->warehouse_id ?? 0);

    $availableDefectByProduct = [];
    $availableDamagedByProduct = [];

    $productIds = [];
    foreach ($saleDelivery->items as $it) {
        $pid = (int) $it->product_id;
        if ($pid > 0) $productIds[] = $pid;
    }
    $productIds = array_values(array_unique($productIds));

    if (!empty($productIds)) {
        $defRows = \Illuminate\Support\Facades\DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->whereNull('moved_out_at')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($defRows as $r) {
            $pid = (int) $r->product_id;
            if (!isset($availableDefectByProduct[$pid])) $availableDefectByProduct[$pid] = [];
            $availableDefectByProduct[$pid][] = $r;
        }

        $damRows = \Illuminate\Support\Facades\DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->where('damage_type', 'damaged')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($damRows as $r) {
            $pid = (int) $r->product_id;
            if (!isset($availableDamagedByProduct[$pid])) $availableDamagedByProduct[$pid] = [];
            $availableDamagedByProduct[$pid][] = $r;
        }
    }

    $fmt = function ($txt, $max=40) {
        $t = trim((string) ($txt ?? ''));
        if ($t === '') return '';
        if (mb_strlen($t) <= $max) return $t;
        return mb_substr($t, 0, $max) . '...';
    };
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">

            <div class="card mb-3">
                <div class="card-header d-flex align-items-center flex-wrap">
                    <div>
                        <strong>Confirm Sale Delivery</strong>
                        <div class="text-muted small">
                            Ref: <strong>{{ $saleDelivery->reference }}</strong> •
                            Date: {{ $saleDelivery->date ? \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y') : '-' }} •
                            Warehouse: <strong>{{ $saleDelivery->warehouse?->warehouse_name ?? ('WH#'.$saleDelivery->warehouse_id) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card-body">

                    <div class="alert alert-info">
                        <strong>Catatan:</strong>
                        <ul class="mb-0">
                            <li>Qty yang kamu confirm (GOOD + DEFECT + DAMAGED) <strong>tidak boleh melebihi</strong> Expected Qty.</li>
                            <li>Jika DEFECT/DAMAGED > 0, sistem akan meminta ID item (recommended) agar tracking & soft-delete tepat.</li>
                            <li>Jika kamu tidak pilih ID, sistem akan auto-pick dari data paling lama (oldest).</li>
                        </ul>
                    </div>

                    <div class="alert alert-danger d-none" id="rowErrorBox">
                        <strong>Perhatian:</strong> Ada baris item yang total confirm-nya melebihi Expected. Tolong perbaiki dulu sebelum submit.
                    </div>

                    <form method="POST" action="{{ route('sale-deliveries.confirm.store', $saleDelivery->id) }}" id="confirmForm">
                        @csrf

                        <div class="table-responsive">
                            <table class="table table-bordered" id="confirmTable">
                                <thead>
                                <tr>
                                    <th style="width: 28%;">Product</th>
                                    <th style="width: 8%;" class="text-center">Expected</th>
                                    <th style="width: 8%;">GOOD</th>
                                    <th style="width: 8%;">DEFECT</th>
                                    <th style="width: 8%;">DAMAGED</th>
                                    <th style="width: 20%;">DEFECT IDs</th>
                                    <th style="width: 20%;">DAMAGED IDs</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($saleDelivery->items as $i => $it)
                                    @php
                                        $itemId = (int) $it->id;
                                        $pid = (int) $it->product_id;
                                        $expected = (int) ($it->quantity ?? 0);
                                        $productName = $it->product?->product_name ?? ('Product #'.$pid);

                                        $defList = $availableDefectByProduct[$pid] ?? [];
                                        $damList = $availableDamagedByProduct[$pid] ?? [];
                                    @endphp

                                    <tr class="confirm-row" data-expected="{{ $expected }}">
                                        <td>
                                            <strong>{{ $productName }}</strong>
                                            <div class="small text-muted">product_id: {{ $pid }}</div>
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $itemId }}">
                                        </td>

                                        <td class="text-center">
                                            <span class="badge badge-secondary">{{ number_format($expected) }}</span>
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="items[{{ $i }}][good]"
                                                   class="form-control qty-good"
                                                   min="0"
                                                   value="0"
                                                   required>
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="items[{{ $i }}][defect]"
                                                   class="form-control qty-defect"
                                                   min="0"
                                                   value="0"
                                                   required>
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="items[{{ $i }}][damaged]"
                                                   class="form-control qty-damaged"
                                                   min="0"
                                                   value="0"
                                                   required>
                                        </td>

                                        <td>
                                            <div class="defect-wrap">
                                                @if(count($defList) > 0)
                                                    <select name="items[{{ $i }}][selected_defect_ids][]"
                                                            class="form-control select-defect"
                                                            multiple
                                                            disabled>
                                                        @foreach($defList as $r)
                                                            @php
                                                                $label = "ID {$r->id}";
                                                                $extra = [];
                                                                if (!empty($r->defect_type)) $extra[] = (string) $r->defect_type;
                                                                if (!empty($r->description)) $extra[] = $fmt($r->description, 35);
                                                                if (!empty($extra)) $label .= ' - ' . implode(' | ', $extra);
                                                            @endphp
                                                            <option value="{{ (int) $r->id }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-muted mt-1">Wajib pilih sebanyak qty DEFECT jika ingin manual.</div>
                                                @else
                                                    <div class="small text-danger">No DEFECT stock available for this product.</div>
                                                @endif
                                            </div>
                                        </td>

                                        <td>
                                            <div class="damaged-wrap">
                                                @if(count($damList) > 0)
                                                    <select name="items[{{ $i }}][selected_damaged_ids][]"
                                                            class="form-control select-damaged"
                                                            multiple
                                                            disabled>
                                                        @foreach($damList as $r)
                                                            @php
                                                                $label = "ID {$r->id}";
                                                                $extra = [];
                                                                if (!empty($r->reason)) $extra[] = $fmt($r->reason, 35);
                                                                if (!empty($extra)) $label .= ' - ' . implode(' | ', $extra);
                                                            @endphp
                                                            <option value="{{ (int) $r->id }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-muted mt-1">Wajib pilih sebanyak qty DAMAGED jika ingin manual.</div>
                                                @else
                                                    <div class="small text-danger">No DAMAGED stock available for this product.</div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group mt-3">
                            <label class="form-label">Confirm Note (optional)</label>
                            <textarea name="confirm_note" class="form-control" rows="3">{{ old('confirm_note') }}</textarea>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="bi bi-check2-circle"></i> Confirm & Apply Mutation Out
                            </button>
                            <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
(function(){
    function toInt(v){ v = (v === null || v === undefined) ? '0' : String(v); v = v.trim(); if (v === '') v = '0'; return parseInt(v, 10) || 0; }

    function getRowValues(tr){
        const expected = toInt(tr.dataset.expected);
        const good = toInt(tr.querySelector('.qty-good')?.value);
        const defect = toInt(tr.querySelector('.qty-defect')?.value);
        const damaged = toInt(tr.querySelector('.qty-damaged')?.value);
        return { expected, good, defect, damaged, total: good + defect + damaged };
    }

    function setSelectEnabled(selectEl, enabled){
        if (!selectEl) return;
        selectEl.disabled = !enabled;
        if (!enabled) {
            Array.from(selectEl.options).forEach(o => o.selected = false);
        }
    }

    function enforceMaxSelection(selectEl, max){
        if (!selectEl) return;
        const selected = Array.from(selectEl.selectedOptions || []);
        if (max <= 0) return;
        if (selected.length > max) {
            for (let i = max; i < selected.length; i++) selected[i].selected = false;
        }
    }

    function updateRow(tr){
        const v = getRowValues(tr);

        // highlight error if total > expected
        if (v.total > v.expected) tr.classList.add('table-danger');
        else tr.classList.remove('table-danger');

        // enable defect select only if defect > 0
        const selDef = tr.querySelector('.select-defect');
        if (selDef) {
            setSelectEnabled(selDef, v.defect > 0);
            enforceMaxSelection(selDef, v.defect);
        }

        // enable damaged select only if damaged > 0
        const selDam = tr.querySelector('.select-damaged');
        if (selDam) {
            setSelectEnabled(selDam, v.damaged > 0);
            enforceMaxSelection(selDam, v.damaged);
        }
    }

    function hasAnyError(){
        const rows = document.querySelectorAll('.confirm-row');
        for (const tr of rows) {
            const v = getRowValues(tr);
            if (v.total > v.expected) return true;
        }
        return false;
    }

    const rows = document.querySelectorAll('.confirm-row');
    rows.forEach(tr => {
        ['.qty-good', '.qty-defect', '.qty-damaged'].forEach(sel => {
            const el = tr.querySelector(sel);
            if (el) el.addEventListener('input', () => {
                updateRow(tr);

                const box = document.getElementById('rowErrorBox');
                if (box) box.classList.toggle('d-none', !hasAnyError());
            });
        });

        const selDef = tr.querySelector('.select-defect');
        if (selDef) selDef.addEventListener('change', () => {
            const v = getRowValues(tr);
            enforceMaxSelection(selDef, v.defect);
        });

        const selDam = tr.querySelector('.select-damaged');
        if (selDam) selDam.addEventListener('change', () => {
            const v = getRowValues(tr);
            enforceMaxSelection(selDam, v.damaged);
        });

        updateRow(tr);
    });

    const form = document.getElementById('confirmForm');
    if (form) {
        form.addEventListener('submit', function(e){
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
