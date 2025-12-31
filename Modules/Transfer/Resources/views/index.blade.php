@extends('layouts.app')

@section('title', 'Transfers')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Transfers</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">
            {{-- Header --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div>
                    <div class="page-title">Transfers</div>
                    <div class="page-subtitle text-muted">Kelola pengiriman antar cabang (Outgoing) & penerimaan (Incoming).</div>
                </div>

                <div class="ms-auto d-flex align-items-center gap-2">
                    @if(($activeTab ?? 'outgoing') === 'outgoing')
                        <a href="{{ route('transfers.create') }}" class="btn btn-primary btn-sm btn-modern">
                            <i class="bi bi-plus"></i> Add Transfer
                        </a>
                    @endif

                    {{-- Tabs --}}
                    <div class="btn-group tabs-modern" role="group" aria-label="tabs">
                        <a href="{{ route('transfers.index', ['tab' => 'outgoing']) }}"
                           class="btn btn-sm {{ ($activeTab ?? 'outgoing') === 'outgoing' ? 'active' : '' }}">
                            Outgoing
                            <span class="badge bg-light text-dark ms-1">Dikirim</span>
                        </a>
                        <a href="{{ route('transfers.index', ['tab' => 'incoming']) }}"
                           class="btn btn-sm {{ ($activeTab ?? 'outgoing') === 'incoming' ? 'active' : '' }}">
                            Incoming
                            <span class="badge bg-light text-dark ms-1">Diterima</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Divider --}}
            <div class="divider-soft mb-3"></div>

            {{-- ================= OUTGOING ================= --}}
            <div class="{{ ($activeTab ?? 'outgoing') === 'outgoing' ? '' : 'd-none' }}">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3 gap-2">
                    <div class="filter-wrap">
                        <label class="small text-muted mb-1 d-block">Filter Status</label>
                        <select id="filter_status_outgoing" class="form-control form-control-sm form-control-modern">
                            <option value="">-- All Status --</option>
                            <option value="pending">PENDING</option>
                            <option value="shipped">SHIPPED</option>
                            <option value="confirmed">CONFIRMED</option>
                            <option value="cancelled">CANCELLED</option>
                        </select>
                    </div>

                    <div class="text-muted small ms-auto">
                        Tip: pakai filter status untuk mempercepat pencarian transfer.
                    </div>
                </div>

                <div class="table-wrap">
                    {!! $outgoingTable->table(['class' => 'table table-striped table-bordered w-100 table-modern'], true) !!}
                </div>
            </div>

            {{-- ================= INCOMING ================= --}}
            <div class="{{ ($activeTab ?? 'outgoing') === 'incoming' ? '' : 'd-none' }}">
                <div class="d-flex flex-wrap align-items-end justify-content-between mb-3 gap-2">
                    <div class="filter-wrap">
                        <label class="small text-muted mb-1 d-block">Filter Status</label>
                        <select id="filter_status_incoming" class="form-control form-control-sm form-control-modern">
                            <option value="">-- All Status --</option>
                            <option value="pending">PENDING</option>
                            <option value="shipped">SHIPPED</option>
                            <option value="confirmed">CONFIRMED</option>
                            <option value="cancelled">CANCELLED</option>
                        </select>
                    </div>

                    <div class="text-muted small ms-auto">
                        Tip: incoming biasanya status <strong>shipped</strong> → butuh konfirmasi.
                    </div>
                </div>

                <div class="table-wrap">
                    {!! $incomingTable->table(['class' => 'table table-striped table-bordered w-100 table-modern'], true) !!}
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ✅ Global Cancel Modal (single modal) --}}
<div class="modal fade" id="cancelTransferModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form method="POST" id="cancelTransferForm" action="">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelTransferTitle">Cancel Transfer</h5>

                    {{-- Close (BS4/CoreUI) --}}
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
                        <span aria-hidden="true">&times;</span>
                    </button>

                    {{-- Close (BS5) --}}
                    <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <strong>Warning:</strong> Cancel akan membuat <strong>reversal mutation</strong> (log mutation tidak dihapus).
                    </div>

                    <div class="mb-2">
                        <div class="small text-muted">Reference</div>
                        <div class="fw-bold" id="cancelTransferRef">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cancel Reason / Note</label>
                        <textarea class="form-control" name="note" rows="4" required
                                  placeholder="Contoh: Barang rusak saat pengiriman, return ke gudang asal..."></textarea>
                        <div class="form-text">Wajib diisi agar histori jelas.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Yes, Cancel Transfer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern{ border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 8px 22px rgba(15,23,42,.06); }
    .page-title{ font-weight:800; font-size:18px; color:#0f172a; line-height:1.2; }
    .page-subtitle{ font-size:13px; }
    .divider-soft{ height:1px; background:#e2e8f0; opacity:.9; }
    .btn-modern{ border-radius:999px; padding:8px 14px; font-weight:700; box-shadow:0 6px 14px rgba(2,6,23,.12); }
    .tabs-modern{ background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px; padding:4px; gap:4px; }
    .tabs-modern .btn{ border:0; border-radius:999px !important; font-weight:700; color:#334155; background:transparent; padding:7px 12px; }
    .tabs-modern .btn.active{ background:#2563eb; color:#fff; box-shadow:0 8px 18px rgba(37,99,235,.25); }
    .tabs-modern .btn .badge{ border-radius:999px; font-weight:700; }
    .form-control-modern{ border-radius:10px; border-color:#e2e8f0; }
    .form-control-modern:focus{ border-color:#93c5fd; box-shadow:0 0 0 .2rem rgba(147,197,253,.25); }
    .filter-wrap{ min-width:220px; }
    .table-wrap{ border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
    .table-modern thead th{ background:#f8fafc !important; color:#334155; font-weight:800; border-bottom:1px solid #e2e8f0 !important; }
    .table-modern td, .table-modern th{ vertical-align:middle; }
</style>
@endpush

{{-- NOTE: kalau project kamu sudah include jquery + datatables dari layout, bagian CDN ini sebaiknya DIHAPUS biar gak double --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.3/css/buttons.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.print.min.js"></script>

{!! $outgoingTable->scripts() !!}
{!! $incomingTable->scripts() !!}

@push('page_scripts')
<script>
function openModal(modalId) {
    const modalEl = document.getElementById(modalId);

    // 1) Bootstrap 5
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
    } catch(e) {}

    // 2) CoreUI
    try {
        if (typeof coreui !== 'undefined' && coreui.Modal) {
            coreui.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
    } catch(e) {}

    // 3) Bootstrap 4 / CoreUI jQuery
    try {
        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
            jQuery(modalEl).modal('show');
            return true;
        }
    } catch(e) {}

    return false;
}

document.addEventListener("DOMContentLoaded", function () {

    // ✅ Cancel Transfer (delegated karena DataTables redraw)
    $(document).on('click', '.js-open-cancel-transfer', function () {
        const id  = $(this).data('transfer-id');
        const ref = $(this).data('transfer-ref') || ('#' + id);

        $('#cancelTransferTitle').text('Cancel Transfer - ' + ref);
        $('#cancelTransferRef').text(ref);
        $('#cancelTransferForm textarea[name="note"]').val('');

        const actionUrl = "{{ route('transfers.cancel', ':id') }}".replace(':id', id);
        $('#cancelTransferForm').attr('action', actionUrl);

        const opened = openModal('cancelTransferModal');
        if (!opened) {
            alert('Cancel modal: library modal tidak terdeteksi. Cek includes.main-js / bootstrap/coreui.');
        }
    });

    // Filter status DT
    $('#filter_status_outgoing').on('change', function () {
        window.LaravelDataTables['outgoing-transfers-table']?.ajax?.reload();
    });

    $('#filter_status_incoming').on('change', function () {
        window.LaravelDataTables['incoming-transfers-table']?.ajax?.reload();
    });
});
</script>
@endpush
