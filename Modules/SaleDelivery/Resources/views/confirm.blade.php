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

    // preload available defect/damaged IDs per product_id
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
                            <li>Kalau kamu input DEFECT / DAMAGED, kamu bisa (dan disarankan) memilih ID itemnya agar tracking & soft-delete tepat.</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('sale-deliveries.confirm.store', $saleDelivery->id) }}">
                        @csrf

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th style="width: 28%;">Product</th>
                                    <th style="width: 8%;" class="text-center">Expected</th>
                                    <th style="width: 8%;">GOOD</th>
                                    <th style="width: 8%;">DEFECT</th>
                                    <th style="width: 8%;">DAMAGED</th>
                                    <th style="width: 20%;">Select DEFECT IDs</th>
                                    <th style="width: 20%;">Select DAMAGED IDs</th>
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

                                    <tr>
                                        <td>
                                            <strong>{{ $productName }}</strong>
                                            <div class="small text-muted">
                                                product_id: {{ $pid }}
                                            </div>

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
                                                   data-expected="{{ $expected }}"
                                                   required>
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="items[{{ $i }}][defect]"
                                                   class="form-control qty-defect"
                                                   min="0"
                                                   value="0"
                                                   data-expected="{{ $expected }}"
                                                   required>
                                        </td>

                                        <td>
                                            <input type="number"
                                                   name="items[{{ $i }}][damaged]"
                                                   class="form-control qty-damaged"
                                                   min="0"
                                                   value="0"
                                                   data-expected="{{ $expected }}"
                                                   required>
                                        </td>

                                        <td>
                                            <select name="items[{{ $i }}][selected_defect_ids][]"
                                                    class="form-control select-defect"
                                                    multiple
                                                    data-max="0"
                                                    data-item="{{ $itemId }}">
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
                                            <div class="small text-muted mt-1">
                                                Pilih sebanyak qty DEFECT. (Kalau kosong, sistem auto-ambil oldest.)
                                            </div>
                                        </td>

                                        <td>
                                            <select name="items[{{ $i }}][selected_damaged_ids][]"
                                                    class="form-control select-damaged"
                                                    multiple
                                                    data-max="0"
                                                    data-item="{{ $itemId }}">
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
                                            <div class="small text-muted mt-1">
                                                Pilih sebanyak qty DAMAGED. (Kalau kosong, sistem auto-ambil oldest.)
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
                            <button type="submit" class="btn btn-primary">
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
    function sumRow(tr){
        const good = parseInt(tr.querySelector('.qty-good')?.value || '0', 10);
        const defect = parseInt(tr.querySelector('.qty-defect')?.value || '0', 10);
        const damaged = parseInt(tr.querySelector('.qty-damaged')?.value || '0', 10);
        return {good, defect, damaged, total: good + defect + damaged};
    }

    function enforceMaxSelection(selectEl, max){
        if (!selectEl) return;
        const selected = Array.from(selectEl.selectedOptions || []);
        if (max <= 0) return;

        if (selected.length > max) {
            // keep first max
            for (let i = max; i < selected.length; i++) {
                selected[i].selected = false;
            }
            alert('Maksimal pilihan ID adalah: ' + max);
        }
    }

    function updateRow(tr){
        const expected = parseInt(tr.querySelector('.qty-good')?.dataset.expected || '0', 10);
        const sum = sumRow(tr);

        if (sum.total > expected) {
            alert('Total confirm (GOOD+DEFECT+DAMAGED) tidak boleh melebihi Expected (' + expected + ').');
        }

        // set max selection for defect/damaged selects
        const selDef = tr.querySelector('.select-defect');
        const selDam = tr.querySelector('.select-damaged');

        if (selDef) {
            selDef.dataset.max = String(sum.defect);
            enforceMaxSelection(selDef, sum.defect);
        }
        if (selDam) {
            selDam.dataset.max = String(sum.damaged);
            enforceMaxSelection(selDam, sum.damaged);
        }
    }

    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(tr => {
        ['.qty-good', '.qty-defect', '.qty-damaged'].forEach(cls => {
            const el = tr.querySelector(cls);
            if (el) el.addEventListener('input', () => updateRow(tr));
        });

        const selDef = tr.querySelector('.select-defect');
        if (selDef) selDef.addEventListener('change', () => {
            const max = parseInt(selDef.dataset.max || '0', 10);
            enforceMaxSelection(selDef, max);
        });

        const selDam = tr.querySelector('.select-damaged');
        if (selDam) selDam.addEventListener('change', () => {
            const max = parseInt(selDam.dataset.max || '0', 10);
            enforceMaxSelection(selDam, max);
        });

        updateRow(tr);
    });
})();
</script>
@endpush
