@extends('layouts.app')

@section('title', 'Detail Transfer')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfer</a></li>
    <li class="breadcrumb-item active">Detail</li>
</ol>
@endsection

@section('content')
@php
    $status = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

    $statusClass = match ($status) {
        'pending'   => 'bg-secondary text-white',
        'shipped'   => 'bg-primary text-white',
        'confirmed' => 'bg-success text-white',
        'issue'     => 'bg-warning text-dark',
        'cancelled' => 'bg-danger text-white',
        default     => 'bg-info text-white',
    };

    $activeBranch = session('active_branch');
    $isAll = ($activeBranch === 'all' || $activeBranch === null || $activeBranch === '');
    $isSender = (!$isAll && (int)$activeBranch === (int)$transfer->branch_id);
    $canPrintSender = $isSender && $status !== 'cancelled';
@endphp

<div class="container-fluid">
    @include('utils.alerts')
    {{-- ================= HEADER CARD ================= --}}
    <div class="card mb-3 shadow-sm">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <div class="text-muted small">Reference</div>
                <h5 class="mb-0">{{ $transfer->reference }}</h5>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">

                @can('print_transfers')
                    @if($canPrintSender)
                        <button type="button"
                                class="btn btn-sm btn-dark js-open-print-transfer"
                                data-transfer-id="{{ $transfer->id }}"
                                data-transfer-ref="{{ $transfer->reference }}"
                                title="Print Delivery Note">
                            <i class="bi bi-printer"></i> Cetak Surat Jalan
                        </button>
                    @endif
                @endcan

                @if (
                    $status === 'shipped'
                    && session('active_branch') !== 'all'
                    && (int) session('active_branch') === (int) $transfer->to_branch_id
                )
                    <a href="{{ route('transfers.confirm', $transfer->id) }}"
                       class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> Konfirmasi
                    </a>
                @endif

                {{-- Cancel modal + button --}}
                @include('transfer::partials.cancel-transfer-modal', ['transfer' => $transfer])
            </div>
        </div>
    </div>

    {{-- ================= INFO GRID ================= --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Dari Gudang</div>
                    <strong>{{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Ke Cabang</div>
                    <strong>{{ $transfer->toBranch->name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small mb-1">Informasi Transfer</div>

                    <div class="mb-1">
                        Tanggal:
                        <strong>{{ \Carbon\Carbon::parse($transfer->date)->format('d M Y') }}</strong>
                    </div>

                    <div class="mb-1">
                        Status:
                        <span class="badge {{ $statusClass }} text-uppercase" id="js-transfer-status-badge">
                            {{ strtoupper($status) }}
                        </span>
                    </div>

                    @if($transfer->delivery_code)
                        <div class="small text-muted" id="js-delivery-code-wrap">
                            Delivery Code:
                            <strong id="js-delivery-code">{{ $transfer->delivery_code }}</strong>
                        </div>
                    @else
                        <div class="small text-muted d-none" id="js-delivery-code-wrap">
                            Delivery Code:
                            <strong id="js-delivery-code"></strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================= AUDIT TRAIL ================= --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <strong>Audit Trail</strong>
        </div>

        <div class="card-body">
            @php
                $createdByName   = optional($transfer->creator)->name ?? optional($transfer->creator)->username ?? '-';
                $printedByName   = optional($transfer->printedBy)->name ?? optional($transfer->printedBy)->username ?? '-';
                $confirmedByName = optional($transfer->confirmedBy)->name ?? optional($transfer->confirmedBy)->username ?? '-';
                $cancelledByName = optional($transfer->cancelledBy)->name ?? optional($transfer->cancelledBy)->username ?? '-';
            @endphp

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Created By</div>
                        <div class="fw-semibold">{{ $createdByName }}</div>
                        <div class="text-muted small">
                            {{ $transfer->created_at ? \Carbon\Carbon::parse($transfer->created_at)->format('d M Y H:i') : '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Printed By</div>
                        <div class="fw-semibold" id="js-printed-by-name">{{ $transfer->printed_at ? $printedByName : '-' }}</div>
                        <div class="text-muted small" id="js-printed-at">
                            {{ $transfer->printed_at ? \Carbon\Carbon::parse($transfer->printed_at)->format('d M Y H:i') : '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Confirmed By</div>
                        <div class="fw-semibold">{{ $transfer->confirmed_at ? $confirmedByName : '-' }}</div>
                        <div class="text-muted small">
                            {{ $transfer->confirmed_at ? \Carbon\Carbon::parse($transfer->confirmed_at)->format('d M Y H:i') : '-' }}
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Cancelled By</div>
                        <div class="fw-semibold">{{ $transfer->cancelled_at ? $cancelledByName : '-' }}</div>
                        <div class="text-muted small">
                            {{ $transfer->cancelled_at ? \Carbon\Carbon::parse($transfer->cancelled_at)->format('d M Y H:i') : '-' }}
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($transfer->cancel_note))
                <div class="mt-3">
                    <div class="text-muted small mb-1">Cancel Note</div>
                    <div class="border rounded p-3 bg-light">
                        {{ $transfer->cancel_note }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ================= RACK MOVEMENT LOG ================= --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <strong>Rack Movement Log</strong>
        </div>

        <div class="card-body">
            @php
                $activeBranch = session('active_branch');
                $isAll = ($activeBranch === 'all' || $activeBranch === null || $activeBranch === '');
                $isSender = (!$isAll && (int)$activeBranch === (int)$transfer->branch_id);
                $isReceiver = (!$isAll && (int)$activeBranch === (int)$transfer->to_branch_id);
            @endphp

            @if($isSender)
                <div class="fw-semibold mb-2">Outgoing (Pengirim)</div>
                <ul class="mb-0">
                    @forelse(($rackLogsOutgoing ?? []) as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li class="text-muted">-</li>
                    @endforelse
                </ul>
            @endif

            @if($isReceiver)
                <hr>
                <div class="fw-semibold mb-2">Incoming (Penerima)</div>

                @if(in_array($status, ['confirmed','issue']))
                    <ul class="mb-0">
                        @forelse(($rackLogsIncoming ?? []) as $line)
                            <li>{{ $line }}</li>
                        @empty
                            <li class="text-muted">Belum ada data rack penerimaan.</li>
                        @endforelse
                    </ul>
                @else
                    <div class="text-muted">Belum ada log incoming karena transfer belum dikonfirmasi.</div>
                @endif
            @endif

            @if(!$isSender && !$isReceiver)
                <div class="text-muted">Pilih cabang pengirim/penerima untuk melihat log rack.</div>
            @endif
        </div>
    </div>

    {{-- ================= ITEM TABLE ================= --}}
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white">
            <strong>Daftar Produk</strong>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Produk</th>
                            <th class="text-center" width="120">Sent</th>

                            @if(in_array($status, ['confirmed','issue']))
                                <th class="text-center">Received</th>
                                <th class="text-center">Defect</th>
                                <th class="text-center">Damaged</th>
                                <th class="text-center">Missing</th>
                            @else
                                <th class="text-center">Jumlah</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($transfer->items as $i => $item)
                            @php
                                $product = $item->product;
                                $pid = (int) $item->product_id;
                                $summary = $itemSummaries[$pid] ?? [];
                            @endphp

                            @php
                                $cond = strtolower((string) ($item->condition ?? 'good'));

                                $condLabel = match ($cond) {
                                    'good' => 'GOOD',
                                    'defect' => 'DEFECT',
                                    'damaged' => 'DAMAGED',
                                    default => strtoupper($cond),
                                };

                                $condClass = match ($cond) {
                                    'good' => 'bg-success text-white',
                                    'defect' => 'bg-warning text-dark',
                                    'damaged' => 'bg-danger text-white',
                                    default => 'bg-secondary text-white',
                                };
                            @endphp

                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    @if($product)
                                        <span>{{ $product->product_name }}</span>
                                        <span class="badge {{ $condClass }}">{{ $condLabel }}</span>
                                        <div class="text-muted small">{{ $product->product_code }}</div>
                                    @else
                                        <span class="text-danger">Product ID {{ $pid }} not found</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-primary text-white fw-semibold">{{ $item->quantity }}</span>
                                </td>

                                @if(in_array($status, ['confirmed','issue']))
                                    <td class="text-center">
                                        <span class="badge bg-success">
                                            {{ $summary['received_good'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">
                                            {{ $summary['defect'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            {{ $summary['damaged'] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">
                                            {{ $summary['missing'] ?? 0 }}
                                        </span>
                                    </td>
                                @else
                                    <td class="text-center">{{ $item->quantity }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Tidak ada item dalam transfer ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================= DEFECT ================= --}}
    @if(in_array($status, ['confirmed','issue']) && $defects->isNotEmpty())
        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning bg-opacity-25 fw-semibold">
                Defect Details
            </div>

            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 55px;">#</th>
                            <th>Produk</th>
                            <th class="text-center" style="width: 70px;">Qty</th>
                            <th style="width: 180px;">Type</th>
                            <th>Desc</th>
                            <th class="text-center" style="width: 110px;">Photo</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($defects as $i => $d)
                            @php
                                $item = $transfer->items->firstWhere('product_id', $d->product_id);
                                $product = optional($item)->product;
                            @endphp

                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    {{ $product
                                        ? $product->product_name . ' (' . $product->product_code . ')'
                                        : 'Product ID ' . $d->product_id }}
                                </td>
                                <td class="text-center">{{ $d->quantity }}</td>
                                <td>{{ $d->defect_type }}</td>
                                <td>{{ $d->description ?? '-' }}</td>
                                <td class="text-center">
                                    @if(!empty($d->photo_path))
                                        <a href="{{ asset('storage/'.$d->photo_path) }}" target="_blank" title="Open Photo">
                                            <img
                                                src="{{ asset('storage/'.$d->photo_path) }}"
                                                alt="defect-photo"
                                                style="height:45px; width:auto; border-radius:6px; border:1px solid #e5e7eb;"
                                            >
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    @endif

    {{-- ================= DAMAGED ================= --}}
    @if(in_array($status, ['confirmed','issue']) && isset($damaged) && $damaged->isNotEmpty())
        <div class="card mb-4 shadow-sm border-danger">
            <div class="card-header bg-danger bg-opacity-10 fw-semibold">
                Damaged / Pecah Details
            </div>

            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 55px;">#</th>
                            <th>Produk</th>
                            <th class="text-center" style="width: 70px;">Qty</th>
                            <th>Reason</th>
                            <th class="text-center" style="width: 90px;">IN</th>
                            <th class="text-center" style="width: 90px;">OUT</th>
                            <th class="text-center" style="width: 110px;">Photo</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($damaged as $i => $dm)
                            @php
                                $item = $transfer->items->firstWhere('product_id', $dm->product_id);
                                $product = optional($item)->product;
                            @endphp

                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    {{ $product
                                        ? $product->product_name . ' (' . $product->product_code . ')'
                                        : 'Product ID ' . $dm->product_id }}
                                </td>
                                <td class="text-center">{{ $dm->quantity }}</td>
                                <td>{{ $dm->reason }}</td>
                                <td class="text-center">#{{ $dm->mutation_in_id }}</td>
                                <td class="text-center">#{{ $dm->mutation_out_id }}</td>
                                <td class="text-center">
                                    @if(!empty($dm->photo_path))
                                        <a href="{{ asset('storage/'.$dm->photo_path) }}" target="_blank" title="Open Photo">
                                            <img
                                                src="{{ asset('storage/'.$dm->photo_path) }}"
                                                alt="damaged-photo"
                                                style="height:45px; width:auto; border-radius:6px; border:1px solid #e5e7eb;"
                                            >
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    @endif

    {{-- ================= MISSING DETAILS ================= --}}
    @if(in_array($status, ['confirmed','issue']) && isset($missing) && $missing->isNotEmpty())
        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning bg-opacity-10 fw-semibold">
                Missing Details
            </div>

            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 55px;">#</th>
                            <th>Produk</th>
                            <th class="text-center" style="width: 70px;">Qty</th>
                            <th>Reason</th>
                            <th style="width: 140px;">Resolution</th>
                            <th style="width: 140px;">Responsible</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($missing as $i => $ms)
                            @php
                                $item = $transfer->items->firstWhere('product_id', $ms->product_id);
                                $product = optional($item)->product;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    {{ $product
                                        ? $product->product_name . ' (' . $product->product_code . ')'
                                        : 'Product ID ' . $ms->product_id }}
                                </td>
                                <td class="text-center">{{ (int) $ms->quantity }}</td>
                                <td>{{ $ms->reason ?? '-' }}</td>
                                <td>{{ $ms->resolution_status ?? '-' }}</td>
                                <td>{{ optional($ms->responsibleUser)->name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    @endif

</div>

{{-- ✅ Print Confirm Modal --}}
<div class="modal fade" id="printTransferModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Delivery Note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size:24px;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warning mb-2">
                    <strong>Konfirmasi:</strong> Aksi ini akan melakukan <strong>print log</strong> dan menambah hitungan <strong>COPY</strong>.
                    Jika ini print pertama, status otomatis menjadi <strong>SHIPPED</strong>.
                </div>

                <div class="small text-muted">Reference</div>
                <div class="fw-bold" id="jsPrintRef">{{ $transfer->reference }}</div>

                <div class="mt-3 small text-muted" id="jsPrintHint">Klik "Yes, Print" untuk lanjut.</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="jsConfirmPrintBtn">
                    <i class="bi bi-printer"></i> Yes, Print
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openModal(modalId) {
    const modalEl = document.getElementById(modalId);

    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
    } catch(e) {}

    try {
        if (typeof coreui !== 'undefined' && coreui.Modal) {
            coreui.Modal.getOrCreateInstance(modalEl).show();
            return true;
        }
    } catch(e) {}

    try {
        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
            jQuery(modalEl).modal('show');
            return true;
        }
    } catch(e) {}

    return false;
}

function closeModal(modalId) {
    const modalEl = document.getElementById(modalId);

    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            return true;
        }
    } catch(e) {}

    try {
        if (typeof coreui !== 'undefined' && coreui.Modal) {
            coreui.Modal.getOrCreateInstance(modalEl).hide();
            return true;
        }
    } catch(e) {}

    try {
        if (window.jQuery && typeof jQuery(modalEl).modal === 'function') {
            jQuery(modalEl).modal('hide');
            return true;
        }
    } catch(e) {}

    return false;
}

document.addEventListener('click', function (e) {

    const cancelBtn = e.target.closest('[data-open-cancel-modal]');
    if (cancelBtn) {
        const modalId = cancelBtn.getAttribute('data-open-cancel-modal');
        if (!modalId) return;
        openModal(modalId);
        return;
    }

    const printBtn = e.target.closest('.js-open-print-transfer');
    if (printBtn) {
        window.__printTransferId = printBtn.getAttribute('data-transfer-id');
        window.__printTransferRef = printBtn.getAttribute('data-transfer-ref') || '';
        document.getElementById('jsPrintRef').innerText = window.__printTransferRef;
        openModal('printTransferModal');
        return;
    }
});

document.addEventListener('DOMContentLoaded', function () {

    const confirmBtn = document.getElementById('jsConfirmPrintBtn');
    if (!confirmBtn) return;

    confirmBtn.addEventListener('click', function () {
        const id = window.__printTransferId;
        if (!id) return;

        confirmBtn.disabled = true;
        document.getElementById('jsPrintHint').innerText = 'Processing...';

        fetch("{{ route('transfers.print.prepare', ['transfer' => $transfer->id]) }}".replace("{{ $transfer->id }}", id), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                "Accept": "application/json"
            },
            body: JSON.stringify({})
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message || 'Failed to print.';
                throw new Error(msg);
            }
            return data;
        })
        .then((data) => {
            closeModal('printTransferModal');

            if (data.pdf_url) {
                window.open(data.pdf_url, '_blank');
            }

            // update badge status quickly (best effort)
            if (data.status) {
                const badge = document.getElementById('js-transfer-status-badge');
                if (badge) {
                    badge.innerText = String(data.status).toUpperCase();
                    badge.className = 'badge bg-primary text-uppercase';
                }
            }

            // info delivery code on UI: optional - cannot fetch code from response right now
            // If you want, we can return delivery_code too from controller.

        })
        .catch((err) => {
            alert(err.message || 'Print failed.');
        })
        .finally(() => {
            confirmBtn.disabled = false;
            document.getElementById('jsPrintHint').innerText = 'Klik "Yes, Print" untuk lanjut.';
        });
    });

});
</script>
@endpush
