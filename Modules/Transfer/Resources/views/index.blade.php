@extends('layouts.app')

@section('title', 'Mutations')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Mutations</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('transfers.create') }}" class="btn btn-primary">
                            Add Transfer <i class="bi bi-plus"></i>
                        </a>

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ Modal Global Cetak Ulang --}}
    <div class="modal fade" id="modalGlobalReprint" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Cetak Ulang Surat Jalan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p id="modal-reprint-body">Memuat informasi...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" target="_blank" id="modal-reprint-confirm" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Cetak Ulang
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}

    {{-- ✅ Bootstrap JS (jika belum ada di layout) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- ✅ Script Modal Dinamis --}}
    <script>
        const modal = document.getElementById('modalGlobalReprint');
        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            const id = button.getAttribute('data-id');
            const reference = button.getAttribute('data-reference');
            const count = button.getAttribute('data-count');

            const body = modal.querySelector('#modal-reprint-body');
            const link = modal.querySelector('#modal-reprint-confirm');

            body.innerHTML = `Surat jalan <strong>${reference}</strong> sudah dicetak sebanyak <strong>${count}x</strong>. Apakah kamu yakin ingin mencetak ulang?`;
            link.href = `/transfers/${id}/print-pdf`;
        });
    </script>
@endpush
