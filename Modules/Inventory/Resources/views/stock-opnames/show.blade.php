@extends('layouts.app')

@section('title', 'Detail Stock Opname')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.stock-opnames.index') }}">Stock Opname</a></li>
        <li class="breadcrumb-item active">{{ $stockOpname->reference }}</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between" style="gap:12px;">
                <div>
                    <div class="page-title">{{ $stockOpname->title }}</div>
                    <div class="text-muted small">{{ $stockOpname->reference }} | {{ optional($stockOpname->branch)->name }} | {{ optional($stockOpname->warehouse)->warehouse_name }}</div>
                    @if($stockOpname->note)
                        <div class="small mt-2">{{ $stockOpname->note }}</div>
                    @endif
                </div>
                <div class="text-right">
                    @php
                        $opnameStatusClass = match ($stockOpname->status) {
                            'finalized' => 'success',
                            'reviewed' => 'info',
                            default => 'secondary',
                        };
                    @endphp
                    <span class="badge badge-{{ $opnameStatusClass }} px-3 py-2">
                        {{ strtoupper($stockOpname->status) }}
                    </span>
                    @if($stockOpname->adjustment)
                        <div class="small text-muted mt-2">Adjustment: {{ $stockOpname->adjustment->reference }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-modern mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:10px;">
                        <div class="font-weight-bold">Action</div>
                        <div class="d-flex flex-wrap" style="gap:8px;">
                            @if($stockOpname->status === 'draft')
                                <a href="{{ route('inventory.stock-opnames.template', $stockOpname) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    Download Template
                                </a>
                                <form action="{{ route('inventory.stock-opnames.mark-missing-zero', $stockOpname) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3">Tandai Kosong Jadi 0</button>
                                </form>
                                <form action="{{ route('inventory.stock-opnames.review', $stockOpname) }}" method="POST" class="d-inline" onsubmit="return confirm('Kunci review ini? Qty fisik dan resolve tidak bisa diubah lagi setelah masuk tahap reviewed.');">
                                    @csrf
                                    <button type="submit" class="btn btn-info btn-sm rounded-pill px-3">Kunci Review</button>
                                </form>
                            @elseif($stockOpname->status === 'reviewed')
                                @can('create_adjustments')
                                <form action="{{ route('inventory.stock-opnames.finalize', $stockOpname) }}" method="POST" class="d-inline" onsubmit="return confirm('Finalize adjustment untuk item yang sudah ditandai adjustment?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Finalize Adjustment</button>
                                </form>
                                @endcan
                            @endif
                        </div>
                    </div>

                    @if($stockOpname->status === 'draft')
                    <form action="{{ route('inventory.stock-opnames.import', $stockOpname) }}" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <label class="small text-muted mb-1 d-block">Import hasil fisik</label>
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary btn-modern w-100">Import Hasil Opname</button>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            Tim isi kolom <b>physical_qty</b> di template. Jika menemukan kaca fisik yang tidak ada di list, tambahkan baris baru dengan <b>product_code</b> yang valid.
                        </div>
                    </form>

                    <form action="{{ route('inventory.stock-opnames.manual-item.store', $stockOpname) }}" method="POST" class="border rounded p-3 mb-3" id="manual-opname-form">
                        @csrf
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:10px;">
                            <div>
                                <div class="font-weight-bold">Input Fisik Manual</div>
                                <div class="small text-muted">Cari kode produk kaca, isi qty fisik, dan opsional rack. Cocok untuk input langsung tanpa Excel.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="add-manual-row">
                                <i class="bi bi-plus-circle mr-1"></i> Tambah Baris
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-2">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:34%">Cari Kode Produk</th>
                                        <th style="width:16%">Qty Sistem</th>
                                        <th style="width:16%">Qty Fisik</th>
                                        <th style="width:18%">Rack</th>
                                        <th>Catatan</th>
                                        <th style="width:60px"></th>
                                    </tr>
                                </thead>
                                <tbody id="manual-opname-body">
                                </tbody>
                            </table>
                        </div>

                        <div class="small text-muted mb-3">
                            Rack boleh dikosongkan. Jika diisi, pakai <b>kode rack</b> di gudang aktif. Jika tidak valid, sistem akan pakai rack snapshot/default existing.
                        </div>

                        <button type="submit" class="btn btn-primary btn-modern">Simpan Input Fisik Manual</button>
                    </form>
                    @endif

                    <form method="GET" action="{{ route('inventory.stock-opnames.show', $stockOpname) }}" class="row">
                        <div class="col-md-5">
                            <label class="small text-muted mb-1 d-block">Cari kode / nama</label>
                            <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Cari produk">
                        </div>
                        <div class="col-md-4">
                            <label class="small text-muted mb-1 d-block">Filter status</label>
                            <select name="status" class="form-control">
                                <option value="">Semua</option>
                                <option value="missing_input" {{ $status === 'missing_input' ? 'selected' : '' }}>Belum Diisi</option>
                                <option value="match" {{ $status === 'match' ? 'selected' : '' }}>Match</option>
                                <option value="plus" {{ $status === 'plus' ? 'selected' : '' }}>Plus</option>
                                <option value="minus" {{ $status === 'minus' ? 'selected' : '' }}>Minus</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100">Filter</button>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap mt-3" style="gap:8px;">
                        <a href="{{ route('inventory.stock-opnames.show', array_merge(['stockOpname' => $stockOpname->id], request()->except('page', 'status'))) }}"
                           class="btn btn-sm {{ $status === '' ? 'btn-primary' : 'btn-outline-primary' }} rounded-pill px-3">
                            Semua
                        </a>
                        <a href="{{ route('inventory.stock-opnames.show', array_merge(['stockOpname' => $stockOpname->id, 'status' => 'missing_input'], request()->except('page', 'status'))) }}"
                           class="btn btn-sm {{ $status === 'missing_input' ? 'btn-secondary' : 'btn-outline-secondary' }} rounded-pill px-3">
                            Belum Diisi ({{ number_format($summary['missing_input']) }})
                        </a>
                        <a href="{{ route('inventory.stock-opnames.show', array_merge(['stockOpname' => $stockOpname->id, 'status' => 'minus'], request()->except('page', 'status'))) }}"
                           class="btn btn-sm {{ $status === 'minus' ? 'btn-danger' : 'btn-outline-danger' }} rounded-pill px-3">
                            Minus ({{ number_format($summary['minus_count']) }})
                        </a>
                        <a href="{{ route('inventory.stock-opnames.show', array_merge(['stockOpname' => $stockOpname->id, 'status' => 'plus'], request()->except('page', 'status'))) }}"
                           class="btn btn-sm {{ $status === 'plus' ? 'btn-warning' : 'btn-outline-warning' }} rounded-pill px-3">
                            Plus ({{ number_format($summary['plus_count']) }})
                        </a>
                        <a href="{{ route('inventory.stock-opnames.show', array_merge(['stockOpname' => $stockOpname->id, 'status' => 'match'], request()->except('page', 'status'))) }}"
                           class="btn btn-sm {{ $status === 'match' ? 'btn-success' : 'btn-outline-success' }} rounded-pill px-3">
                            Match ({{ number_format($summary['match_count']) }})
                        </a>
                    </div>
                </div>
            </div>

            <div class="card card-modern">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Rack</th>
                                    <th class="text-right">Sistem</th>
                                    <th class="text-right">Fisik</th>
                                    <th class="text-right">Selisih</th>
                                    <th>Status</th>
                                    <th>Resolve</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    @php
                                        $statusText = 'Belum Diisi';
                                        $statusClass = 'secondary';
                                        if (!is_null($item->physical_qty)) {
                                            if ((int) $item->diff_qty === 0) { $statusText = 'Match'; $statusClass = 'success'; }
                                            elseif ((int) $item->diff_qty > 0) { $statusText = 'Plus'; $statusClass = 'warning'; }
                                            else { $statusText = 'Minus'; $statusClass = 'danger'; }
                                        }
                                        $needsResolve = !is_null($item->physical_qty) && (int) $item->diff_qty !== 0;
                                        $resolutionTypeLabels = [
                                            'missing_sale' => 'Lupa Input Penjualan',
                                            'missing_purchase' => 'Lupa Input Pembelian',
                                            'missing_transfer' => 'Lupa Input Transfer',
                                            'rack_movement' => 'Salah Rack / Perpindahan Rack',
                                            'adjustment' => 'Adjustment',
                                            'other' => 'Lainnya',
                                        ];
                                        $resolutionLabel = $item->resolution_type ? ($resolutionTypeLabels[$item->resolution_type] ?? strtoupper($item->resolution_type)) : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $item->product_code_snapshot }}</td>
                                        <td>{{ $item->product_name_snapshot }}</td>
                                        <td>
                                            @if($item->rack_code_snapshot || $item->rack_name_snapshot)
                                                <span class="badge badge-dark">{{ trim(($item->rack_code_snapshot ?? '') . ' - ' . ($item->rack_name_snapshot ?? ''), ' -') }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format((int) $item->system_qty) }}</td>
                                        <td class="text-right">{{ is_null($item->physical_qty) ? '-' : number_format((int) $item->physical_qty) }}</td>
                                        <td class="text-right {{ !is_null($item->diff_qty) && (int) $item->diff_qty !== 0 ? 'font-weight-bold' : '' }}">
                                            {{ is_null($item->diff_qty) ? '-' : number_format((int) $item->diff_qty) }}
                                        </td>
                                        <td><span class="badge badge-{{ $statusClass }}">{{ $statusText }}</span></td>
                                        <td style="min-width:280px;">
                                            @php
                                                $actionLink = $actionLinks[$item->id] ?? null;
                                            @endphp
                                            @if(!$needsResolve)
                                                <span class="text-muted small">Tidak perlu resolve</span>
                                            @elseif($stockOpname->status === 'finalized')
                                                <div class="small">
                                                    <div><span class="badge badge-{{ $item->resolution_type === 'adjustment' ? 'warning' : 'info' }}">{{ $resolutionLabel ?? 'Pending' }}</span></div>
                                                    @if($item->resolution_reference)
                                                        <div class="text-muted mt-1">Ref: {{ $item->resolution_reference }}</div>
                                                    @endif
                                                    @if($item->resolution_note)
                                                        <div class="text-muted mt-1">{{ $item->resolution_note }}</div>
                                                    @endif
                                                    @if($actionLink)
                                                        <div class="mt-2">
                                                            @if($actionLink['url'])
                                                                <a href="{{ $actionLink['url'] }}" target="_blank" class="btn btn-sm btn-outline-{{ $actionLink['style'] }} rounded-pill px-3">{{ $actionLink['label'] }}</a>
                                                            @else
                                                                <span class="badge badge-{{ $actionLink['style'] }}">{{ $actionLink['label'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @elseif($item->review_status === 'resolved')
                                                <div class="small border rounded p-2 bg-light">
                                                    <div class="mb-1">
                                                        <span class="badge badge-{{ $item->resolution_type === 'adjustment' ? 'warning' : 'info' }}">{{ $resolutionLabel ?? 'Resolved' }}</span>
                                                    </div>
                                                    @if($item->resolution_reference)
                                                        <div class="text-muted">Ref: {{ $item->resolution_reference }}</div>
                                                    @endif
                                                    @if($item->resolution_note)
                                                        <div class="text-muted mt-1">{{ $item->resolution_note }}</div>
                                                    @endif
                                                    <div class="text-muted mt-1">
                                                        Resolved {{ optional($item->resolved_at)->format('d/m/Y H:i') }}
                                                    </div>
                                                    @if($actionLink)
                                                        <div class="mt-2">
                                                            @if($actionLink['url'])
                                                                <a href="{{ $actionLink['url'] }}" target="_blank" class="btn btn-sm btn-outline-{{ $actionLink['style'] }} rounded-pill px-3">{{ $actionLink['label'] }}</a>
                                                            @else
                                                                <span class="badge badge-{{ $actionLink['style'] }}">{{ $actionLink['label'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($stockOpname->status === 'draft')
                                                        <div class="d-flex flex-wrap mt-2" style="gap:8px;">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 js-edit-resolve">Edit Resolve</button>
                                                            <form action="{{ route('inventory.stock-opnames.items.reset-resolve', [$stockOpname, $item]) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset resolve item ini?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">Reset Resolve</button>
                                                            </form>
                                                        </div>
                                                        <form action="{{ route('inventory.stock-opnames.items.resolve', [$stockOpname, $item]) }}" method="POST" class="resolve-form d-none mt-2">
                                                            @csrf
                                                            <div class="form-group mb-2">
                                                                <select name="resolution_type" class="form-control form-control-sm" required>
                                                                    <option value="">Pilih solusi</option>
                                                                    <option value="missing_sale" {{ $item->resolution_type === 'missing_sale' ? 'selected' : '' }}>Lupa Input Penjualan</option>
                                                                    <option value="missing_purchase" {{ $item->resolution_type === 'missing_purchase' ? 'selected' : '' }}>Lupa Input Pembelian</option>
                                                                    <option value="missing_transfer" {{ $item->resolution_type === 'missing_transfer' ? 'selected' : '' }}>Lupa Input Transfer</option>
                                                                    <option value="rack_movement" {{ $item->resolution_type === 'rack_movement' ? 'selected' : '' }}>Salah Rack / Perpindahan Rack</option>
                                                                    <option value="adjustment" {{ $item->resolution_type === 'adjustment' ? 'selected' : '' }}>Adjustment</option>
                                                                    <option value="other" {{ $item->resolution_type === 'other' ? 'selected' : '' }}>Lainnya</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group mb-2">
                                                                <input type="text" name="resolution_reference" class="form-control form-control-sm" value="{{ $item->resolution_reference }}" placeholder="Ref transaksi opsional">
                                                            </div>
                                                            <div class="form-group mb-2">
                                                                <textarea name="resolution_note" class="form-control form-control-sm" rows="2" placeholder="Catatan penyebab / tindakan">{{ $item->resolution_note }}</textarea>
                                                            </div>
                                                            <div class="d-flex flex-wrap" style="gap:8px;">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3">Update Resolve</button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 js-cancel-edit-resolve">Batal</button>
                                                            </div>
                                                        </form>
                                                    @endif
                                                </div>
                                            @else
                                                <form action="{{ route('inventory.stock-opnames.items.resolve', [$stockOpname, $item]) }}" method="POST" class="resolve-form">
                                                    @csrf
                                                    <div class="form-group mb-2">
                                                        <select name="resolution_type" class="form-control form-control-sm" required>
                                                            <option value="">Pilih solusi</option>
                                                            <option value="missing_sale">Lupa Input Penjualan</option>
                                                            <option value="missing_purchase">Lupa Input Pembelian</option>
                                                            <option value="missing_transfer">Lupa Input Transfer</option>
                                                            <option value="rack_movement">Salah Rack / Perpindahan Rack</option>
                                                            <option value="adjustment">Adjustment</option>
                                                            <option value="other">Lainnya</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <input type="text" name="resolution_reference" class="form-control form-control-sm" placeholder="Ref transaksi opsional">
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <textarea name="resolution_note" class="form-control form-control-sm" rows="2" placeholder="Catatan penyebab / tindakan"></textarea>
                                                    </div>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3">Simpan Resolve</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>{{ $item->note ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Tidak ada item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-modern mb-3">
                <div class="card-body">
                    <div class="font-weight-bold mb-3">Ringkasan</div>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="summary-label">Total Item</div>
                            <div class="summary-value">{{ number_format($summary['total_items']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Sudah Diisi</div>
                            <div class="summary-value">{{ number_format($summary['counted_items']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Belum Diisi</div>
                            <div class="summary-value text-secondary">{{ number_format($summary['missing_input']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Match</div>
                            <div class="summary-value text-success">{{ number_format($summary['match_count']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Plus</div>
                            <div class="summary-value text-warning">{{ number_format($summary['plus_count']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Minus</div>
                            <div class="summary-value text-danger">{{ number_format($summary['minus_count']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Belum Direview</div>
                            <div class="summary-value text-danger">{{ number_format($summary['unresolved_difference_count']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Sudah Direview</div>
                            <div class="summary-value text-info">{{ number_format($summary['resolved_difference_count']) }}</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Akan Adjustment</div>
                            <div class="summary-value text-warning">{{ number_format($summary['adjustment_count']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-modern">
                <div class="card-body">
                    <div class="font-weight-bold mb-3">Total Qty</div>
                    <div class="small mb-2">Qty Sistem: <b>{{ number_format($summary['system_total']) }}</b></div>
                    <div class="small mb-2">Qty Fisik Terinput: <b>{{ number_format($summary['physical_total']) }}</b></div>
                    <div class="small text-muted">
                        Draft dipakai untuk input fisik dan review penyebab. Setelah <b>Kunci Review</b>, qty fisik dikunci. Finalize hanya membuat mutation + adjustment untuk item yang di-resolve sebagai <b>Adjustment</b>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern{border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 22px rgba(15,23,42,.06)}
    .page-title{font-weight:800;font-size:18px;color:#0f172a;line-height:1.2}
    .btn-modern{border-radius:999px;padding:8px 14px;font-weight:700;box-shadow:0 6px 14px rgba(2,6,23,.12)}
    .summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .summary-card{border:1px solid #e2e8f0;border-radius:12px;padding:14px;background:#f8fafc}
    .summary-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.02em}
    .summary-value{font-size:22px;font-weight:800;color:#0f172a;line-height:1.1}
    .manual-search-box{position:relative}
    .manual-search-results{position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;border:1px solid #dbe4f0;border-radius:10px;box-shadow:0 10px 24px rgba(15,23,42,.12);max-height:280px;overflow:auto;display:none}
    .manual-search-item{padding:10px 12px;border-bottom:1px solid #eef2f7;cursor:pointer}
    .manual-search-item:hover{background:#f8fafc}
    .manual-search-code{font-weight:700;color:#0f172a}
    .manual-search-meta{font-size:12px;color:#64748b}
</style>
@endpush

@if($stockOpname->status === 'draft')
@push('page_scripts')
<script>
(function () {
    const body = document.getElementById('manual-opname-body');
    const addBtn = document.getElementById('add-manual-row');
    const searchUrl = @json(route('inventory.stock-opnames.products.search', $stockOpname));
    let rowIndex = 0;

    function makeRow() {
        const idx = rowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="manual-search-box">
                    <input type="text" class="form-control product-search-input" placeholder="Ketik code / nama produk">
                    <input type="hidden" name="items[${idx}][product_code]" class="product-code-hidden">
                    <div class="manual-search-results"></div>
                </div>
            </td>
            <td>
                <input type="text" class="form-control system-qty-display" value="-" readonly>
            </td>
            <td>
                <input type="number" min="0" name="items[${idx}][physical_qty]" class="form-control" required>
            </td>
            <td>
                <input type="text" name="items[${idx}][rack_code]" class="form-control" placeholder="Kode rack">
            </td>
            <td>
                <input type="text" name="items[${idx}][note]" class="form-control" placeholder="Opsional">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button>
            </td>
        `;
        bindRow(tr);
        return tr;
    }

    function bindRow(tr) {
        const input = tr.querySelector('.product-search-input');
        const hidden = tr.querySelector('.product-code-hidden');
        const resultBox = tr.querySelector('.manual-search-results');
        const systemQty = tr.querySelector('.system-qty-display');
        const removeBtn = tr.querySelector('.remove-row');
        let timer = null;

        removeBtn.addEventListener('click', function () {
            tr.remove();
        });

        input.addEventListener('input', function () {
            hidden.value = '';
            systemQty.value = '-';

            const q = input.value.trim();
            clearTimeout(timer);

            if (q.length < 2) {
                resultBox.style.display = 'none';
                resultBox.innerHTML = '';
                return;
            }

            timer = setTimeout(function () {
                fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(res => {
                    const items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                    if (!items.length) {
                        resultBox.innerHTML = '<div class="manual-search-item text-muted">Tidak ada hasil.</div>';
                        resultBox.style.display = 'block';
                        return;
                    }

                    resultBox.innerHTML = items.map(item => `
                        <div class="manual-search-item" 
                             data-code="${escapeHtml(item.product_code)}"
                             data-name="${escapeHtml(item.product_name)}"
                             data-system="${Number(item.system_qty || 0)}"
                             data-rack="${escapeHtml(item.rack_label || '')}">
                            <div class="manual-search-code">${escapeHtml(item.product_code)}</div>
                            <div>${escapeHtml(item.product_name)}</div>
                            <div class="manual-search-meta">Sistem: ${Number(item.system_qty || 0)}${item.rack_label ? ' | Rack: ' + escapeHtml(item.rack_label) : ''}</div>
                        </div>
                    `).join('');
                    resultBox.style.display = 'block';

                    resultBox.querySelectorAll('.manual-search-item').forEach(node => {
                        node.addEventListener('click', function () {
                            const code = node.getAttribute('data-code') || '';
                            const name = node.getAttribute('data-name') || '';
                            const system = node.getAttribute('data-system') || '0';
                            input.value = code + ' | ' + name;
                            hidden.value = code;
                            systemQty.value = system;
                            resultBox.style.display = 'none';
                        });
                    });
                })
                .catch(() => {
                    resultBox.innerHTML = '<div class="manual-search-item text-danger">Gagal mencari produk.</div>';
                    resultBox.style.display = 'block';
                });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!tr.contains(e.target)) {
                resultBox.style.display = 'none';
            }
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    addBtn.addEventListener('click', function () {
        body.appendChild(makeRow());
    });

    body.appendChild(makeRow());

    document.querySelectorAll('.js-edit-resolve').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const wrapper = btn.closest('.border');
            const form = wrapper ? wrapper.querySelector('.resolve-form') : null;
            if (form) {
                form.classList.remove('d-none');
                btn.parentElement.classList.add('d-none');
            }
        });
    });

    document.querySelectorAll('.js-cancel-edit-resolve').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = btn.closest('.resolve-form');
            if (form) {
                form.classList.add('d-none');
                const actions = form.parentElement.querySelector('.d-flex.flex-wrap.mt-2');
                if (actions) {
                    actions.classList.remove('d-none');
                }
            }
        });
    });
})();
</script>
@endpush
@endif
