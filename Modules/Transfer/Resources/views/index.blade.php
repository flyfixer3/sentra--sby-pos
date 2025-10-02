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
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center">
            <div class="h5 m-0">Transfers</div>

            <div class="btn-group mfs-auto" role="group" aria-label="tabs">
                <a href="{{ route('transfers.index', ['tab' => 'outgoing']) }}"
                   class="btn btn-sm btn-outline-primary {{ ($activeTab ?? 'outgoing') === 'outgoing' ? 'active' : '' }}">
                    Outgoing (Dikirim)
                </a>
                <a href="{{ route('transfers.index', ['tab' => 'incoming']) }}"
                   class="btn btn-sm btn-outline-success {{ ($activeTab ?? 'outgoing') === 'incoming' ? 'active' : '' }}">
                    Incoming (Diterima)
                </a>
            </div>
        </div>

        <div class="card-body">
            {{-- OUTGOING --}}
            <div class="{{ ($activeTab ?? 'outgoing') === 'outgoing' ? '' : 'd-none' }}">
                <div class="d-flex align-items-center mb-3">
                    <a href="{{ route('transfers.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus"></i> Add Transfer
                    </a>
                </div>

                {{-- Empty State Outgoing --}}
                <div id="empty-outgoing" class="alert alert-info d-none">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-inbox me-2"></i>
                        <div>
                            <div class="fw-bold">Belum ada transfer yang dikirim dari cabang ini.</div>
                            <div class="small">Klik <strong>Add Transfer</strong> untuk membuat pengiriman baru.</div>
                        </div>
                    </div>
                </div>

                {!! $outgoingTable->table(['class' => 'table table-striped table-bordered w-100'], true) !!}
            </div>

            {{-- INCOMING --}}
            <div class="{{ ($activeTab ?? 'outgoing') === 'incoming' ? '' : 'd-none' }}">
                {{-- Empty State Incoming --}}
                <div id="empty-incoming" class="alert alert-warning d-none">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-archive me-2"></i>
                        <div>
                            <div class="fw-bold">Tidak ada transfer yang perlu diterima.</div>
                            <div class="small">Semua pengiriman ke cabang ini sudah dikonfirmasi, atau belum ada kiriman baru.</div>
                        </div>
                    </div>
                </div>

                {!! $incomingTable->table(['class' => 'table table-striped table-bordered w-100'], true) !!}
            </div>
        </div>
    </div>
</div>

{{-- ====== MUAT LIBRARY WAJIB (CDN) SEBELUM INISIALISASI DATATABLES ====== --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.3/css/buttons.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

{{-- Buttons (karena kita pakai dom: "Bfrtip" -> excel/print/reset/reload) --}}
<script src="https://cdn.datatables.net/buttons/2.4.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.3/js/buttons.print.min.js"></script>

{{-- ====== INISIALISASI DARI YAJRA (WAJIB SETELAH LIBRARY DI ATAS) ====== --}}
{!! $outgoingTable->scripts() !!}
{!! $incomingTable->scripts() !!}

<script>
    console.log('[Transfers] init DataTables...');

    (function () {
        function bindEmptyState(tableKey, emptyElId) {
            var dt = window.LaravelDataTables && window.LaravelDataTables[tableKey];
            var emptyEl = document.getElementById(emptyElId);
            if (!dt || !emptyEl) return;

            dt.on('draw', function () {
                var info = dt.page && dt.page.info ? dt.page.info() : null;
                var isEmpty = info ? info.recordsDisplay === 0 : false;
                emptyEl.classList.toggle('d-none', !isEmpty);
            });

            // Trigger awal
            setTimeout(function(){ dt.draw(false); }, 0);
        }

        bindEmptyState('outgoing-transfers-table', 'empty-outgoing');
        bindEmptyState('incoming-transfers-table', 'empty-incoming');
    })();
</script>
@endsection
