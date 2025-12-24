@extends('layouts.app')

@section('title', 'Inventory Stocks')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Daftar Stok Produk</h4>

    <!-- Filter -->
    <form method="GET" action="{{ route('inventory.stocks.index') }}" class="row mb-4">
        <div class="col-md-3">
            <select name="branch_id" class="form-control">
                <option value="">-- Semua Cabang --</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="warehouse_id" class="form-control">
                <option value="">-- Semua Gudang --</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="product" class="form-control" placeholder="Cari produk / kode"
                value="{{ request('product') }}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Table -->
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th>Kode Produk</th>
                        <th>Nama Produk</th>
                        <th>Cabang</th>
                        <th>Gudang</th>
                        <th class="text-end">Qty Tersedia</th>
                        <th width="10%">Detail Rak</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $i => $stock)
                    <tr>
                        <td>{{ $stocks->firstItem() + $i }}</td>
                        <td>{{ $stock->product->product_code ?? '-' }}</td>
                        <td>{{ $stock->product->product_name ?? '-' }}</td>
                        <td>{{ $stock->branch->name ?? '-' }}</td>
                        <td>{{ $stock->warehouse->name ?? '-' }}</td>
                        <td class="text-end">{{ number_format($stock->qty_available) }}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-info"
                                onclick="showRackDetails({{ $stock->product_id }}, {{ $stock->branch_id }}, {{ $stock->warehouse_id }})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $stocks->links() }}
        </div>
    </div>
</div>

<!-- Modal Detail Rak -->
<div class="modal fade" id="rackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Stok per Rak</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Kode Rak</th>
                            <th>Nama Rak</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="rackDetailBody">
                        <tr>
                            <td colspan="3" class="text-center text-muted">Memuat data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function showRackDetails(productId, branchId, warehouseId) {
        const modal = new bootstrap.Modal(document.getElementById('rackModal'));
        document.getElementById('rackDetailBody').innerHTML =
            '<tr><td colspan="3" class="text-center text-muted">Memuat data...</td></tr>';

        fetch(`/inventory/stocks/rack-details/${productId}/${branchId}/${warehouseId}`)
            .then(response => response.json())
            .then(data => {
                const body = document.getElementById('rackDetailBody');
                body.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    data.data.forEach(item => {
                        body.innerHTML += `
                            <tr>
                                <td>${item.rack?.code ?? '-'}</td>
                                <td>${item.rack?.name ?? '-'}</td>
                                <td class="text-end">${item.qty_available}</td>
                            </tr>`;
                    });
                } else {
                    body.innerHTML =
                        '<tr><td colspan="3" class="text-center text-muted">Tidak ada data rak untuk produk ini.</td></tr>';
                }
            })
            .catch(() => {
                document.getElementById('rackDetailBody').innerHTML =
                    '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data.</td></tr>';
            });

        modal.show();
    }
</script>
@endpush
