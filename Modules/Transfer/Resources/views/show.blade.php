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
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        Reference: <strong>{{ $transfer->reference }}</strong>
                    </div>
                    <div class="d-print-none">
                        {{-- Tombol Cetak Surat Jalan --}}
                        @if ($transfer->status === 'pending')
                            <a href="{{ route('transfers.print.pdf', $transfer->id) }}"
                            class="btn btn-sm btn-dark" target="_blank">
                                <i class="bi bi-printer"></i> Cetak Surat Jalan
                            </a>
                        @endif


                        {{-- Tombol Konfirmasi Penerimaan --}}
                        @if ($transfer->status == 'pending')
                            <a href="{{ route('transfers.confirm', $transfer->id) }}" class="btn btn-sm btn-success">
                                <i class="bi bi-check-circle"></i> Konfirmasi Penerimaan
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <h6 class="text-muted">Dari Gudang</h6>
                            <p><strong>{{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</strong></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Ke Cabang</h6>
                            <p><strong>{{ $transfer->toBranch->name ?? '-' }}</strong></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted">Informasi Transfer</h6>
                            <p>
                                Tanggal: {{ \Carbon\Carbon::parse($transfer->date)->format('d M Y') }} <br>
                                Status: <span class="badge bg-{{ $transfer->status == 'confirmed' ? 'success' : ($transfer->status == 'pending' ? 'warning text-dark' : 'secondary') }}">
                                    {{ ucfirst($transfer->status) }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Produk</th>
                                    <th>Jumlah</th>
                                    <th>Satuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transfer->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item->product->name ?? '-' }}</td>
                                        <td>{{ number_format($item->quantity) }}</td>
                                        <td>{{ $item->unit ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada item dalam transfer ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Jika ada bukti pengiriman --}}
                    @if($transfer->delivery_proof_path)
                        <div class="mt-4">
                            <label><strong>Lampiran Surat Jalan:</strong></label><br>
                            <a href="{{ asset('storage/' . $transfer->delivery_proof_path) }}"
                               class="btn btn-sm btn-outline-primary"
                               target="_blank">
                                <i class="bi bi-file-earmark-arrow-down"></i> Lihat File
                            </a>
                        </div>
                    @endif
                    @if($transfer->printLogs->isNotEmpty())
                        <hr>
                        <h5 class="mt-4">Riwayat Cetak Surat Jalan</h5>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Tanggal Cetak</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transfer->printLogs as $index => $log)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $log->user->name ?? '-' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($log->printed_at)->format('d M Y H:i') }}</td>
                                            <td>{{ $log->ip_address ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                Belum ada cetakan surat jalan untuk transfer ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
