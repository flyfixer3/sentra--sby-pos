@extends('layouts.app')

@section('title', "Confirm Sale Delivery #{$saleDelivery->reference}")

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}">{{ $saleDelivery->reference }}</a></li>
    <li class="breadcrumb-item active">Confirm</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @include('utils.alerts')

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Confirm Stock Out</h4>
                    <div class="text-muted small">
                        Once confirmed, stock will be deducted and cannot be confirmed again.
                    </div>
                    <div class="text-muted small mt-1">
                        Ref: <span class="fw-bold">{{ $saleDelivery->reference }}</span> •
                        Date: <span class="fw-bold">{{ $saleDelivery->getAttributes()['date'] ?? $saleDelivery->date }}</span> •
                        WH: <span class="fw-bold">{{ optional($saleDelivery->warehouse)->warehouse_name ?? '-' }}</span>
                    </div>
                </div>
                <div class="text-end">
                    @php $st = strtolower((string)$saleDelivery->status); @endphp
                    <span class="badge {{ $st==='pending' ? 'bg-warning text-dark' : 'bg-secondary' }}">
                        {{ strtoupper($saleDelivery->status) }}
                    </span>
                </div>
            </div>

            <hr class="my-3">

            <div class="alert alert-info mb-0">
                <div class="fw-bold mb-1">Rule Validasi</div>
                <div class="small">
                    - Good + Defect + Damaged boleh kurang dari Expected (partial), tapi tidak boleh melebihi Expected.<br>
                    - Defect/Damaged akan diambil dari stok per-unit yang sudah ada (product_defect_items / product_damaged_items).<br>
                    - Kalau stok defect/damaged tidak cukup, sistem akan menolak confirm.
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('sale-deliveries.confirm.store', $saleDelivery->id) }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 320px;">Product</th>
                                <th class="text-center" style="width:120px;">Expected</th>
                                <th class="text-center" style="width:140px;">Good</th>
                                <th class="text-center" style="width:140px;">Defect</th>
                                <th class="text-center" style="width:140px;">Damaged</th>
                                <th class="text-center" style="width:140px;">Remaining</th>
                                <th class="text-center" style="width:160px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($saleDelivery->items as $idx => $it)
                                @php
                                    $expected = (int)($it->quantity ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $it->product_name ?? optional($it->product)->product_name ?? '-' }}</div>
                                        <div class="small text-muted">PID: {{ (int)$it->product_id }}</div>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-primary">{{ $expected }}</span>
                                    </td>

                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][id]" value="{{ (int)$it->id }}">
                                        <input type="number" min="0" class="form-control text-center qty-input"
                                            name="items[{{ $idx }}][good]" value="{{ (int)($it->qty_good ?? 0) }}"
                                            data-expected="{{ $expected }}">
                                    </td>

                                    <td>
                                        <input type="number" min="0" class="form-control text-center qty-input"
                                            name="items[{{ $idx }}][defect]" value="{{ (int)($it->qty_defect ?? 0) }}"
                                            data-expected="{{ $expected }}">
                                    </td>

                                    <td>
                                        <input type="number" min="0" class="form-control text-center qty-input"
                                            name="items[{{ $idx }}][damaged]" value="{{ (int)($it->qty_damaged ?? 0) }}"
                                            data-expected="{{ $expected }}">
                                    </td>

                                    <td class="text-center">
                                        <span class="badge bg-secondary remaining-badge">0</span>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge status-badge bg-success">OK</span>
                                        <div class="small text-muted status-text mt-1">-</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-bold">Confirmation Note (General)</label>
                    <textarea name="confirm_note" class="form-control" rows="4"
                        placeholder="Isi catatan konfirmasi (contoh: dikirim partial karena stok kosong, sisanya menyusul)">{{ old('confirm_note') }}</textarea>
                    <div class="small text-muted mt-1">
                        Catatan ini akan tersimpan dan bisa ditampilkan di halaman detail Sale Delivery.
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">
                        Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Yes, Confirm <i class="bi bi-check-lg"></i>
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script>
(function(){
    function recalcRow(tr){
        const inputs = tr.querySelectorAll('.qty-input');
        let expected = 0;
        let sum = 0;

        inputs.forEach((inp) => {
            expected = parseInt(inp.getAttribute('data-expected') || '0', 10);
            const val = parseInt(inp.value || '0', 10);
            sum += isNaN(val) ? 0 : val;
        });

        const remaining = Math.max(expected - sum, 0);

        const remainingBadge = tr.querySelector('.remaining-badge');
        const statusBadge = tr.querySelector('.status-badge');
        const statusText = tr.querySelector('.status-text');

        if (remainingBadge) remainingBadge.textContent = remaining;

        if (sum > expected) {
            statusBadge.className = 'badge status-badge bg-danger';
            statusBadge.textContent = 'INVALID';
            statusText.textContent = 'Total melebihi expected';
        } else if (sum === 0) {
            statusBadge.className = 'badge status-badge bg-warning text-dark';
            statusBadge.textContent = 'EMPTY';
            statusText.textContent = 'Belum ada qty';
        } else {
            statusBadge.className = 'badge status-badge bg-success';
            statusBadge.textContent = 'OK';
            statusText.textContent = (sum < expected) ? 'Partial' : 'Full';
        }
    }

    document.querySelectorAll('table tbody tr').forEach((tr) => {
        recalcRow(tr);
        tr.querySelectorAll('.qty-input').forEach((inp) => {
            inp.addEventListener('input', () => recalcRow(tr));
        });
    });
})();
</script>
@endpush
