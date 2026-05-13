@extends('layouts.app')

@section('title', 'Rack Detail')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.racks.index') }}">Racks</a></li>
    <li class="breadcrumb-item active">Detail</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h4 class="mb-0">Rack Detail</h4>
                    <div class="text-muted small">Stock summary below is derived from the existing rack stock inventory data.</div>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="{{ route('inventory.racks.index') }}" class="btn btn-light">
                        Back
                    </a>
                </div>
            </div>

            <hr class="my-3">

            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="small text-muted">Rack Code</div>
                    <div><span class="badge badge-dark">{{ $rack->code }}</span></div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="small text-muted">Rack Name</div>
                    <div>{{ $rack->name ?? '-' }}</div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="small text-muted">Warehouse</div>
                    <div>{{ $rack->warehouse_name ?? ('WH#' . $rack->warehouse_id) }}</div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="small text-muted">Branch</div>
                    <div>{{ $rack->branch_name ?? ('Branch#' . $rack->warehouse_branch_id) }}</div>
                </div>
                <div class="col-12 mb-2">
                    <div class="small text-muted">Description</div>
                    <div>{{ $rack->description ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                <div>
                    <h5 class="mb-0">Products in This Rack</h5>
                    <div class="text-muted small">Using the same rack-level stock buckets already maintained by Inventory.</div>
                </div>
                <div class="small text-muted mt-2 mt-md-0">
                    Total products: <b>{{ number_format($products->count()) }}</b>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                    </div>
                    <input
                        type="search"
                        id="rack-product-search"
                        class="form-control"
                        placeholder="Search product code, product name, or rack stock..."
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:90px;">Product ID</th>
                            <th>Product</th>
                            <th style="width:140px;" class="text-right">Total Qty</th>
                            <th style="width:140px;" class="text-right">Good Qty</th>
                            <th style="width:140px;" class="text-right">Defect Qty</th>
                            <th style="width:140px;" class="text-right">Damage Qty</th>
                        </tr>
                    </thead>
                    <tbody id="rack-products-table-body">
                        @forelse($products as $product)
                            <tr class="rack-product-row">
                                <td class="align-middle">{{ (int) $product->product_id }}</td>
                                <td class="align-middle">
                                    <div class="font-weight-bold">{{ $product->product_name ?? '-' }}</div>
                                    @php
                                        $subtitleParts = array_values(array_filter([
                                            !empty(trim((string) ($product->product_code ?? ''))) ? trim((string) $product->product_code) : null,
                                            !empty(trim((string) ($product->category_name ?? ''))) ? trim((string) $product->category_name) : null,
                                            'Unit: ' . stock_unit_label($product->product_unit ?? ''),
                                        ]));
                                    @endphp
                                    <div class="small text-muted">{{ !empty($subtitleParts) ? implode(' | ', $subtitleParts) : '-' }}</div>
                                </td>
                                <td class="align-middle text-right">{{ number_format((int) ($product->qty_total ?? 0)) }}</td>
                                <td class="align-middle text-right">{{ number_format((int) ($product->qty_good ?? 0)) }}</td>
                                <td class="align-middle text-right">{{ number_format((int) ($product->qty_defect ?? 0)) }}</td>
                                <td class="align-middle text-right">{{ number_format((int) ($product->qty_damaged ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No product stock is currently linked to this rack.
                                </td>
                            </tr>
                        @endforelse
                        @if($products->isNotEmpty())
                            <tr id="rack-products-no-match-row" class="d-none">
                                <td colspan="6" class="text-center text-muted py-4">
                                    No matching product stock found.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
    (function () {
        var searchInput = document.getElementById('rack-product-search');
        var tableBody = document.getElementById('rack-products-table-body');
        var noMatchRow = document.getElementById('rack-products-no-match-row');

        if (!searchInput || !tableBody) {
            return;
        }

        var rows = Array.prototype.slice.call(tableBody.querySelectorAll('.rack-product-row'));
        var debounceTimer = null;

        function normalizeSearchText(value) {
            return (value || '').toString().toLowerCase();
        }

        function filterRows() {
            var query = normalizeSearchText(searchInput.value).trim();
            var visibleCount = 0;

            rows.forEach(function (row) {
                var matches = !query || normalizeSearchText(row.textContent).indexOf(query) !== -1;
                row.classList.toggle('d-none', !matches);

                if (matches) {
                    visibleCount++;
                }
            });

            if (noMatchRow) {
                noMatchRow.classList.toggle('d-none', visibleCount > 0);
            }
        }

        searchInput.addEventListener('input', function () {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(filterRows, 120);
        });
    })();
</script>
@endpush
